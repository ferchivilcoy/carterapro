# Plan: Superusuario y activación/desactivación de módulos

> Documento de planificación. **No se modificó código de la aplicación.**
> Fecha: 2026-11-16 · Proyecto: CarteraPro (Laravel 13 / PHP 8.5 / Blade + Bootstrap 5)

---

## 1. Respuesta directa

**Sí, es posible.** El sistema ya tiene la base casi completa:

- Los permisos están centralizados en `Ability` + `Role::grants()` y se aplican con `Gate`.
- **Todos** los módulos del panel están protegidos por un `ability` (`can:...`) en `routes/web.php` y en `routes/api.php`.
- El rol `Admin` ya es un "superusuario funcional": `Gate::before()` en `app/Providers/AppServiceProvider.php` le concede **todas** las habilidades, incluso las que se agreguen en el futuro.
- El menú (`resources/views/layouts/partials/navbar.blade.php`) y el dashboard ya se renderizan con `@can`, así que ocultar módulos es directo.

Lo que **no existe hoy** es:

1. Un catálogo de "módulos" como unidad de configuración (hoy solo hay roles y abilities).
2. Un estado persistido de módulo activo/inactivo.
3. La capacidad de que un usuario (superusuario) active/desactive módulos.
4. Una distinción entre "Administrador" y "Superusuario" (hoy cualquier Admin puede todo, incluida la gestión de usuarios).

**No hace falta ninguna dependencia externa.** Se puede resolver con un enum, un servicio (~100 líneas), un controlador, una vista y ajustes puntuales en `Gate`, navbar y dashboard. Estimación: **2–3 días de desarrollo + pruebas**.

---

## 2. Estado actual relevante del sistema

| Pieza | Archivo | Situación |
|---|---|---|
| Roles | `app/Enums/Role.php` | `admin`, `supervisor`, `cajero`, `cobrador`, `cliente` |
| Habilidades | `app/Enums/Ability.php` | 24 habilidades: `clients.view/manage`, `loans.view/manage`, `settings.manage`, `backup.manage`, `exports.run`, `users.manage`, etc. |
| Autorización | `app/Providers/AppServiceProvider.php` | `Gate::before` deja pasar al Admin; el resto se resuelve con listas blancas por rol |
| Rutas web | `routes/web.php` | Cada grupo usa `can:<ability>`; impresión/exportación tienen su propia ability |
| Rutas API | `routes/api.php` | Replica la misma matriz `view` / `manage` |
| Menú | `resources/views/layouts/partials/navbar.blade.php` | Grupos: Gestión, Caja, Documentos, Análisis, Bitácora, Sistema, Mi cuenta |
| Configuración | `app/Models/Setting.php` + `settings/index.blade.php` | Tabla `key/value` con defaults, pantalla única por grupos |
| Dashboard | `app/Http/Controllers/DashboardController.php` + `resources/views/index.blade.php` | Agrega datos de todos los módulos |
| Auditoría | `app/Models/Event.php` + `app/Enums/EventType.php` | Bitácora de acciones del sistema |
| Tareas programadas | `routes/console.php` | Vencimientos, recordatorios, backup, scoring |

### Consecuencia técnica clave

Como `Gate::before` devuelve `true` para Admin **antes** de evaluar cualquier `Gate::define`, si solo se oculta el menú, un Admin (o cualquiera que conozca la URL) seguiría entrando. **El control de módulos debe aplicarse dentro de la capa Gate**, no solo en la interfaz.

---

## 3. Catálogo propuesto de módulos

| Módulo (key) | Nombre | Habilidades que lo componen | Núcleo / desactivable | Depende de |
|---|---|---|---|---|
| `dashboard` | Escritorio | `dashboard.view` | **Núcleo** (evita lockout) | — |
| `clients` | Clientes | `clients.view`, `clients.manage` | Desactivable | — |
| `loans` | Préstamos | `loans.view`, `loans.manage` | Desactivable | `clients` |
| `installments` | Cuotas | `installments.view` | Desactivable | `loans` |
| `payments` | Cobros | `payments.view`, `payments.manage` | Desactivable | `loans`, `installments` |
| `collection-visits` | Cobranzas | `collection-visits.view/manage` | Desactivable | `clients`, `loans` |
| `cash-movements` | Caja | `cash-movements.view/manage` | Desactivable | — |
| `contracts` | Contratos | `contracts.view/manage` | Desactivable | `clients`, `loans` |
| `client-documents` | Documentos de clientes | `client-documents.view/manage` | Desactivable | `clients` |
| `reports` | Reportes | `reports.view` | Desactivable | datos de otros módulos |
| `profitability` | Rentabilidad | `profitability.view` | Desactivable | `payments`, `loans` |
| `events` | Bitácora | `events.view` | Desactivable | — |
| `portal` | Mi cuenta (cliente) | `clients.portal` | Desactivable (con pantalla de aviso) | `clients` |
| `exports` | Exportaciones | `exports.run` | Desactivable (transversal) | — |
| `settings` | Configuración | `settings.manage` | **Núcleo** | — |
| `users` | Usuarios | `users.manage` | **Núcleo** | — |
| `backup` | Copias de seguridad | `backup.manage` | **Núcleo** | — |
| `modules` | Gestión de módulos | `modules.manage` (nueva) | **Núcleo** | — |

Reglas:

- **Núcleo = siempre activo.** No aparece con toggle o aparece bloqueado. Sin `settings`, `users`, `modules` y `dashboard` un superusuario podría dejar el sistema inaccesible.
- Las dependencias se validan al desactivar: no se puede apagar un módulo si otro módulo activo depende de él (mensaje que indica cuáles desactivar primero).
- **Desactivar no borra datos.** Solo bloquea acceso, oculta menú y widgets. Reactivar restaura todo al instante. Los backups siguen intactos.

---

## 4. Diseño técnico propuesto

### 4.1. Fuente de verdad: `ModuleManager` + `Setting`

Sin migraciones nuevas. Se guarda un estado por módulo en la tabla `settings` existente:

```
key:   module.clients.enabled
value: '1' | '0'
```

Cualquier clave ausente se interpreta con `Module::defaultEnabled()` (todos activos en una instalación nueva → cero regresiones).

Nuevo servicio `app/Services/ModuleManager.php`:

```php
final class ModuleManager
{
    /** @return array<string, bool> */
    public function states(): array;          // lee todos los module.* de una vez
    public function enabled(Module $module): bool;
    public function disable(Module $module, User $actor): void; // valida núcleo/dependencias + audita
    public function enable(Module $module, User $actor): void;
}
```

- Cachea el mapa completo **una vez por request** y lo invalida con `Cache::forget()` al guardar (la app usa `cache`/`file`; no hay Redis obligatorio).
- Evita el problema de N consultas por cada `@can` del menú.

Nuevo enum `app/Enums/Module.php`:

```php
enum Module: string
{
    case Clients = 'clients';
    // ...

    public function label(): string;
    public function isCore(): bool;
    /** @return array<int, Ability> */
    public function abilities(): array;
    /** @return array<int, Module> */
    public function dependsOn(): array;
    public static function forAbility(Ability $ability): ?self;
}
```

### 4.2. Aplicación del bloqueo (la parte crítica)

En `AppServiceProvider::registerAbilities()`:

```php
Gate::before(function (User $user, string $ability): ?bool {
    $module = Module::forAbility(Ability::tryFrom($ability) ?? null);

    // Módulo apagado => nadie pasa, ni siquiera el Admin.
    if ($module && ! app(ModuleManager::class)->enabled($module)) {
        return false;
    }

    // Las habilidades exclusivas del superusuario no las hereda un Admin común.
    if (in_array($ability, Ability::superAdminOnly(), true)) {
        return null; // resuelve el Gate::define específico
    }

    return $user->isAdmin() ? true : null;
});
```

Con esto **no hay que tocar cada controlador**: las rutas protegidas por `can:` quedan cubiertas automáticamente, tanto en web como en API.

Casos que requieren atención puntual (auditoría de rutas ya hecha):

- `export.*` usa `exports.run` (transversal): si se apaga `loans`, la exportación de préstamos seguiría permitida. Solución: middleware `module:loans` en cada ruta de exportación, o mapear la ruta a su módulo.
- `print.payments`, `print.installments`, etc. usan abilities del módulo: ya quedan cubiertos.
- `backup`, `settings`, `users`: núcleo, sin cambios.
- Rutas sin ability dentro de `auth` (no existen hoy): si se agregan, envolverlas en `module:<key>`.

Middleware opcional de refuerzo `app/Http/Middleware/EnsureModuleEnabled.php` (parámetro `module:key`), útil para exportaciones y para futuras rutas sin ability. Devuelve **404** en web (no revela que el módulo existe) y **403/404 JSON** en API, o 403 con mensaje "Módulo desactivado por el administrador".

### 4.3. Interfaz del superusuario

Opción recomendada para la pantalla: **nueva tarjeta "Módulos" dentro de Configuración** (`settings.index`), visible solo con `modules.manage`.

Contenido:

- Lista de módulos con nombre, descripción, estado (badge Activo/Inactivo) y switch.
- Los módulos núcleo se muestran bloqueados con la leyenda "Siempre activo".
- Si un módulo tiene dependientes activos, el switch se deshabilita y se explica qué desactivar primero.
- Confirmación (SweetAlert ya instalado) antes de aplicar.
- Al guardar: `PUT /settings/modules` → `ModuleManager` valida, persiste, audita y limpia caché.
- Mensaje de advertencia fijo: "Desactivar un módulo no borra datos; solo oculta y bloquea su acceso."

Alternativa: pantalla propia `GET/PUT /modules` (`ModuleController`). Más limpia si se espera que esta función crezca (permisos por rol, historial). **Recomendación: pantalla propia** para no mezclar el formulario de configuración general con una acción sensible; enlazarla desde Configuración y desde el menú Sistema.

### 4.4. Superusuario: dos opciones

#### Opción A (recomendada): flag `is_super_admin` sobre el rol Admin

- Migración: `users.is_super_admin` (boolean, default `false`) + cast en `User`.
- Nuevo método `User::isSuperAdmin(): bool` (requiere `role === Admin` y flag).
- Nueva habilidad `Ability::ModulesManage = 'modules.manage'`.
- `Gate::define('modules.manage', ...)` valida el flag **sin** pasar por el bypass de Admin.
- Un comando Artisan `app/Console/Commands/PromoteSuperAdminCommand.php` (`php artisan users:promote-super-admin {email}`) para crear/ascender el primer superusuario sin tocar la BD a mano.
- Protecciones:
  - No se puede quitar el flag al último superusuario activo.
  - No se puede desactivar/eliminar al último superusuario activo (misma lógica ya existente en `UserController::activeAdminCount()`).
  - Solo un superusuario puede otorgar/quitar el flag.
  - El listado de usuarios muestra el badge "Superusuario".
- Auditoría: `EventType::SuperAdminGranted` / `SuperAdminRevoked`.

**Ventajas:** mínimo impacto en el enum `Role`, no rompe `isAdmin()`, ni las listas de `Role::staffCases()`, ni los formularios actuales. El superusuario sigue siendo un Admin para todo lo demás.

#### Opción B: nuevo caso `Role::SuperAdmin`

- Agregar caso al enum, label, `grants()`, `staffCases()`, `homeRoute()` y todos los `match`.
- `isAdmin()` debe incluir `SuperAdmin`; revisar `UserController`, `UpdateUserRequest`, factories y pruebas.
- Más "explícito" en la UI, pero más superficie de regresión.

**Recomendación:** Opción A. La B solo si a futuro se quieren varios niveles de administración.

---

## 5. Cambios por archivo (estimación)

### Nuevos

| Archivo | Propósito |
|---|---|
| `app/Enums/Module.php` | Catálogo: key, label, abilities, dependencias, núcleo |
| `app/Services/ModuleManager.php` | Estado, cache, validaciones, persistencia y auditoría |
| `app/Http/Controllers/ModuleController.php` | `index`, `update` de módulos |
| `app/Http/Requests/Settings/UpdateModulesRequest.php` | Autorización `modules.manage` + validación de keys y reglas |
| `app/Http/Middleware/EnsureModuleEnabled.php` | Refuerzo por ruta (exports y futuras rutas) |
| `app/Console/Commands/PromoteSuperAdminCommand.php` | Alta del primer superusuario |
| `database/migrations/xxxx_add_is_super_admin_to_users_table.php` | Flag del superusuario |
| `resources/views/settings/modules.blade.php` | Pantalla de toggles |
| `tests/Feature/ModuleManagementTest.php` | Ver sección 7 |
| `tests/Feature/SuperAdminTest.php` | Ver sección 7 |

### Modificados

| Archivo | Cambio |
|---|---|
| `app/Enums/Ability.php` | Agregar `ModulesManage`; helper `superAdminOnly()` |
| `app/Providers/AppServiceProvider.php` | `Gate::before` con módulos + excepción superusuario; registrar `ModuleManager` |
| `app/Models/User.php` | Cast `is_super_admin`, `isSuperAdmin()` |
| `app/Enums/EventType.php` | `ModuleToggled`, `SuperAdminGranted`, `SuperAdminRevoked` (+ labels) |
| `routes/web.php` | Rutas `modules.*`; middleware `module:*` en exportaciones |
| `resources/views/layouts/partials/navbar.blade.php` | `@if(module_enabled(...))` / `@can` por grupo; enlace "Módulos" en Sistema |
| `resources/views/index.blade.php` | Ocultar tarjetas/widgets de módulos apagados |
| `app/Http/Controllers/DashboardController.php` | Evitar consultas y datos de módulos apagados (opcional pero recomendado) |
| `app/Http/Controllers/UserController.php` | Badge/protección del superusuario; impedir auto-degradación |
| `app/Http/Requests/Users/StoreUserRequest.php` / `UpdateUserRequest.php` | Validar `is_super_admin` solo si el actor es superusuario |
| `resources/views/users/index.blade.php` y `partials/form.blade.php` | Mostrar/editar el flag según permisos |
| `routes/console.php` | (Opcional) Saltar recordatorios/scoring si el módulo está apagado |

> Nota: no se requiere `npm run build`; todo es Blade + controladores. Sí se debe ejecutar `vendor/bin/pint --dirty --format agent` al terminar.

---

## 6. Reglas de seguridad y comportamiento

1. **Apagado ≠ borrado.** No se eliminan tablas, filas ni archivos. Un módulo apagado se puede reactivar y todo sigue ahí.
2. **El bloqueo es del lado servidor.** Ocultar el menú es cosmético; la decisión real está en `Gate::before` y en el middleware de refuerzo.
3. **Módulos núcleo no desactivables.** Evita lockout total: `dashboard`, `settings`, `users`, `modules`, `backup`.
4. **Dependencias.** Desactivar `loans` exige desactivar antes `installments`, `payments`, `collection-visits`, `contracts`. Validado en el servicio, no solo en la vista.
5. **Último superusuario.** No puede perder el flag, ser desactivado ni degradado. Misma filosofía que el "último admin activo" ya implementada.
6. **Auditoría.** Cada toggle genera un `Event` con módulo, estado anterior/nuevo, actor y timestamp. Queda en la Bitácora.
7. **Cache.** `Cache::forget()` al guardar. En pruebas, `Cache::flush()` o store `array` para evitar estados cruzados.
8. **Sesiones abiertas.** El bloqueo se evalúa por request (igual que `EnsureUserIsActive`), así que un usuario con sesión abierta pierde el acceso al módulo de inmediato; no hace falta revocar sesiones.
9. **API.** `routes/api.php` quedó registrado en `bootstrap/app.php` (Fase 3) y hereda la misma matriz de abilities y el estado de los módulos. Las exportaciones requieren el middleware explícito.
10. **Tareas programadas.** `loans:reminders` y `clients:recalc-score` pueden seguir corriendo aunque el módulo esté apagado (los datos se mantienen consistentes). Opcional: condicionarlas con `Schedule::command(...)->when(fn () => ModuleManager::enabled(...))`.
11. **Portal del cliente.** Si se apaga `portal`, el cliente que inicia sesión debe ver una pantalla "Portal no disponible" en lugar de un 403 seco. Decisión pendiente (ver sección 9).
12. **Doble control.** La pantalla de módulos exige `modules.manage` (superusuario) y CSRF estándar; considerar `throttle:10,1` en el `PUT`.

---

## 7. Plan de pruebas (Pest, `php artisan make:test --pest`)

`tests/Feature/SuperAdminTest.php`

- Un Admin común **no** puede acceder a la gestión de módulos (403).
- Un usuario con `is_super_admin` sí puede.
- El comando `users:promote-super-admin` asciende correctamente y es idempotente.
- No se puede quitar el flag/desactivar al último superusuario activo.
- Solo un superusuario puede otorgar el flag.
- Toggle genera un evento de auditoría.

`tests/Feature/ModuleManagementTest.php`

- Con `clients` apagado: `GET /clients` responde 403/404 y el menú no muestra el enlace.
- Nadie, ni el Admin/superusuario, entra a un módulo apagado (la regla clave).
- Reactivar restaura acceso y menú.
- No se puede apagar un módulo núcleo.
- No se puede apagar un módulo con dependientes activos.
- Desactivar no borra datos (`Client::count()` / `Loan::count()` sin cambios).
- La API respeta el apagado (`/api/clients` → 403/404).
- La exportación de un módulo apagado queda bloqueada.
- El dashboard no muestra widgets del módulo apagado y no lanza excepciones.

Casos de no regresión:

- Instalación sin filas `module.*`: todo funciona como hoy (defaults activos).
- `Settings` y `Users` siguen accesibles para Admin.
- Suite existente en verde (`php artisan test --compact`).

---

## 8. Fases de implementación

| Fase | Alcance | Entregable | Estimación |
|---|---|---|---|
| 0 | Confirmar decisiones de la sección 9 | Respuestas del usuario | — |
| 1 | `Module` enum + `ModuleManager` + defaults + cache | Catálogo funcional, sin UI | 3 h |
| 2 | `Gate::before` + `Ability::ModulesManage` + middleware de refuerzo + rutas de exportación | Bloqueo real | 3 h |
| 3 | Pantalla de módulos + `UpdateModulesRequest` + navbar + dashboard | Toggles operativos | 4 h |
| 4 | Flag de superusuario + migración + comando + protecciones en `UserController` | Superusuario real | 3 h |
| 5 | Auditoría + eventos + pantalla de usuarios | Trazabilidad | 2 h |
| 6 | Pruebas Pest + Pint + verificación manual | Suite verde | 4 h |
| 7 | (Opcional) Permisos de módulo por rol o por usuario | Extensión futura | 1–2 días |

**Total MVP (fases 1–6): ~19 h de trabajo efectivo (2–3 días).**

### Orden de despliegue seguro

1. Deploy de enum/servicio/defaults (todo sigue activo; cambio invisible).
2. Deploy del enforcement y la pantalla; nadie sin `modules.manage` la ve.
3. Crear el primer superusuario con el comando.
4. Recién entonces probar apagados en un entorno de staging.
5. En producción: backup previo y apagar módulos de a uno.

### Rollback

- Revertir el flag: `UPDATE settings SET value='1' WHERE key LIKE 'module.%';` (o `Setting::set`).
- Revertir código: las migraciones son aditivas (solo agrega columna), no hay pérdida de datos.
- El sistema sigue operativo con módulos apagados; no hay acoplamiento a datos.

---

## 9. Decisiones abiertas (requieren tu confirmación antes de codificar)

1. **¿Superusuario separado del Administrador?**
   - A) Sí: flag `is_super_admin`; solo él activa/desactiva módulos y administra superusuarios. *(Recomendado — implementado en Fase 4)*
   - B) No: el rol Admin actual ya puede togglear módulos. *(Más simple, pero cualquier Admin puede apagar el sistema)*
   - C) Nuevo rol `SuperAdmin` en el enum. *(Más invasivo)*

2. **¿Los toggles son globales o por rol/usuario?**
   - A) Globales para toda la instalación. *(Recomendado para el MVP)*
   - B) Globales + excepciones por rol (ej.: Cajero sin Rentabilidad).
   - C) Por usuario.

3. **¿El módulo `portal` (Mi cuenta del cliente) es desactivable?**
   - A) Sí, con pantalla "Portal no disponible". *(Recomendado — implementado en Fase 6)*
   - B) No, es núcleo (los clientes siempre pueden ver su estado de cuenta).

4. **¿Dónde vive la pantalla?**
   - A) Pantalla propia `Sistema → Módulos`. *(Recomendado)*
   - B) Tarjeta dentro de Configuración.

5. **¿Se bloquean también las tareas programadas de un módulo apagado?**
   - A) No: los datos siguen actualizándose (vencimientos, scoring) aunque el módulo esté oculto. *(Recomendado)*
   - B) Sí: si `loans` está apagado, no corren vencimientos ni recordatorios.

6. **¿El dashboard se adapta o se deja completo?**
   - A) Ocultar widgets de módulos apagados y evitar sus consultas. *(Recomendado)*
   - B) Dejar el dashboard intacto (solo ocultar accesos).

---

## 10. Criterios de aceptación del MVP

- [x] Existe un superusuario que puede entrar a "Módulos" y activar/desactivar módulos desactivables.
- [x] Un Admin común (sin flag) **no** puede entrar a "Módulos".
- [x] Un módulo apagado desaparece del menú y responde 403/404 en web y API, **incluso para el superusuario**.
- [x] Reactivar restaura el acceso de inmediato, sin reiniciar la aplicación ni perder datos.
- [x] Los módulos núcleo no se pueden apagar.
- [x] No se puede apagar un módulo con dependientes activos.
- [x] No se puede dejar el sistema sin superusuario activo.
- [x] Cada cambio queda registrado en la Bitácora con actor, módulo y estado anterior/nuevo. *(Fase 5)*
- [x] Una instalación sin configuración previa funciona exactamente como hoy.
- [x] Un rol interno puede quedar sin un módulo activo y pierde Gate, menú, web, API y exportaciones de ese módulo. *(Fase 7)*
- [x] Restringir un rol nunca otorga permisos ni afecta al Administrador, al Cliente ni a los módulos núcleo. *(Fase 7)*
- [x] Cada cambio de acceso por rol queda auditado con rol, módulo y estado anterior/nuevo. *(Fase 7)*
- [x] `vendor/bin/pest` y `vendor/bin/pint --dirty --format agent` en verde.

---

## 11. Alternativas descartadas (y por qué)

| Alternativa | Motivo para no usarla |
|---|---|
| `spatie/laravel-permission` | Excelente para permisos por rol, pero no resuelve "módulos globales"; agrega dependencia (requiere aprobación) y duplicaría el sistema `Ability` ya existente. |
| `laravel/pennant` | Pensado para feature flags de código (rollouts, A/B), no para apagar módulos completos con menú y rutas; igualmente agrega dependencia. |
| Solo ocultar en el navbar | Inseguro: cualquiera con la URL entra. No cumple el requisito. |
| Borrar/renombrar rutas al apagar | Genera errores de `route()` en vistas y rompe enlaces guardados; no reversible en caliente. |
| Módulos como sub-aplicaciones separadas | Innecesario para este tamaño; multiplica deploy y autenticación. |

---

## 12. Nota final

El diseño propuesto reutiliza exactamente los patrones que el proyecto ya usa (enums, `Gate`, `Setting`, `Event`, Blade + Bootstrap) y mantiene el principio actual de "denegado por defecto" para roles no administradores. La única decisión estructural es **quién** es superusuario y **cuánta** granularidad se quiere; el resto es implementación directa y de bajo riesgo.

---

## 13. Bitácora de implementación

### Fase 1 — completada (2026-11-16)

- `app/Enums/Module.php`: catálogo de 18 módulos con `label()`, `isCore()`, `abilities()`, `dependsOn()`, `defaultEnabled()`, `settingKey()` y `forAbility()`.
- `app/Services/ModuleManager.php`: estado persistido en `settings` (`module.<key>.enabled`), mapa cacheado, invalidación al guardar y validación de núcleo/dependencias con `DomainException`.
- `app/Enums/Ability.php`: se agregó `ModulesManage = 'modules.manage'` (inerte hasta la Fase 4).
- `app/Providers/AppServiceProvider.php`: `ModuleManager` registrado como singleton.
- `tests/Feature/ModuleManagerTest.php`: 9 pruebas en verde (defaults, mapeo, persistencia, cache, núcleo, dependencias).

**Comportamiento:** una instalación sin filas `module.*` mantiene todos los módulos activos. No hubo cambios visibles.

### Fase 2 — completada (2026-11-16)

- `AppServiceProvider::registerAbilities()`: `Gate::before` ahora consulta el módulo antes de conceder el bypass al Administrador. Un módulo apagado devuelve `false` para **todas** sus abilities, incluido el Admin.
- `app/Http/Middleware/EnsureModuleEnabled.php`: refuerzo por ruta con alias `module:<key>`; responde 404 para no revelar la existencia del módulo.
- `bootstrap/app.php`: alias `module` registrado.
- `routes/web.php`: las 7 exportaciones llevan `module:<key>` además de `can:exports.run`, porque esa ability es transversal y no permite inferir el módulo.
- `tests/Feature/ModuleEnforcementTest.php`: 6 pruebas en verde (Gate para Admin, ruta 403, reactivación, menú oculto, exportación 404, bloqueo global de exportaciones).

**Comportamiento:** el bloqueo ya es real del lado servidor. El menú oculta automáticamente el enlace porque usa `@can`; la adaptación de widgets del dashboard queda para la Fase 3.

### Fase 3 — completada (2026-11-16)

- `app/Http/Controllers/ModuleController.php`: `index` arma el catálogo con estado, dependencias y dependientes; `update` traduce el formulario y delega en el servicio.
- `app/Http/Requests/Settings/UpdateModulesRequest.php`: autoriza con `modules.manage`, valida el arreglo y rechaza claves que no existen en el catálogo.
- `ModuleManager::update()`: aplicación **atómica** de varios cambios. Valida la configuración final, no el orden, así se puede apagar un módulo y sus dependientes en un solo guardado. `setEnabled()` ahora delega en `update()`.
- `Module::description()`: textos breves para la pantalla.
- `resources/views/modules/index.blade.php`: tabla con descripción, dependencias, módulos que lo requieren, estado y switch. Los módulos núcleo se muestran bloqueados.
- `routes/web.php`: `GET/PUT modules` detrás de `can:modules.manage` y `throttle:10,1`.
- `resources/views/layouts/partials/navbar.blade.php`: enlace **Sistema → Módulos** y `modules.*` dentro del grupo activo.
- `app/Http/Controllers/DashboardController.php`: sólo ejecuta las consultas de los módulos activos y entrega `enabledModules` a la vista.
- `resources/views/index.blade.php`: oculta tarjetas y secciones de módulos apagados (ingresos, cartera, cobranza, atención, bitácora, top de clientes y refinanciaciones).
- `bootstrap/app.php`: **`routes/api.php` registrado**. La API queda operativa con sus 20 rutas y el mismo `Gate`; probado con `/api/clients` y con un módulo apagado.
- Pruebas: **29 tests, 123 assertions en verde** (12 de `ModuleManager`, 7 de pantalla y 10 de enforcement/API/dashboard). Los casos de rechazo usan `followingRedirects()` y verifican que el banner de error se renderiza. `view:cache` y Pint OK.

**Comportamiento:** el superusuario/administrador ya puede activar y desactivar módulos desde la interfaz. El guardado es atómico, los módulos núcleo quedan protegidos y el dashboard se adapta al estado.

### Hallazgo de la Fase 2 — resuelto en Fase 3

`routes/api.php` existía pero no estaba registrado en `bootstrap/app.php`. En la Fase 3 se registró con `api: __DIR__.'/../routes/api.php'`; la API ya responde y aplica el estado de los módulos a través del `Gate`.

### Fase 4 — completada (2026-11-16)

- Migración `add_is_super_admin_to_users_table`: columna `users.is_super_admin` (boolean, default `false`). Las cuentas existentes quedan como administradores comunes.
- Migración `update_events_type_constraint_for_super_admin_events`: reemplaza el CHECK de `events` en MySQL para aceptar los eventos nuevos. En SQLite no hay constraint que actualizar.
- `app/Models/User.php`: cast `is_super_admin`, `isSuperAdmin()` (exige rol Admin + flag) y atributo en `Fillable`.
- `database/factories/UserFactory.php`: estado `superAdmin()`.
- `app/Enums/Ability.php`: `superAdminOnly()` / `isSuperAdminOnly()`; `modules.manage` es la primera habilidad reservada.
- `app/Providers/AppServiceProvider.php`: `Gate::before` ya no concede las habilidades reservadas al Admin común; el `Gate::define` de cada una exige `isSuperAdmin()`. El bloqueo por módulo apagado sigue evaluándose primero.
- `app/Console/Commands/PromoteSuperAdminCommand.php`: `php artisan users:promote-super-admin {email}`. Es idempotente, asciende el rol a Administrador si hace falta, audita la concesión y avisa si la cuenta está inactiva.
- `StoreUserRequest` / `UpdateUserRequest`: `is_super_admin` es un campo prohibido para quien no sea superusuario.
- `app/Http/Controllers/UserController.php`: un superusuario debe conservar el rol Admin; nadie puede quitarse su propio flag; el último superusuario activo no puede perder el flag ni el rol; tampoco puede ser desactivado (ni siquiera por un Admin común). Los cambios de flag generan `SuperAdminGranted` / `SuperAdminRevoked`.
- `app/Enums/EventType.php` y `app/Models/Event.php`: tipos y descripciones de auditoría para la concesión/revocación.
- Vistas: badge **Superusuario** en el listado y checkbox de la condición en el formulario, visible solo para superusuarios.
- Pruebas: `tests/Feature/SuperAdminTest.php` (15 pruebas) y `ModuleManagementTest` actualizado para actuar como superusuario. Suite completa: **46 tests, 45 en verde**; el único fallo es el caso preexistente de `ExampleTest`.

**Comportamiento:** un Admin común conserva todo su acceso operativo, pero ya no puede abrir `Sistema → Módulos`. El primer superusuario se crea desde la consola; a partir de ahí los demás se otorgan desde Usuarios.

**Despliegue:** ejecutar `php artisan migrate` y luego `php artisan users:promote-super-admin correo@dominio.com` una sola vez por instalación.

### Fase 5 — completada (2026-11-16)

- `app/Enums/EventType.php`: nuevo `ModuleToggled` con label **Cambio de módulo**.
- `app/Models/Event.php`: `describeModuleToggled()` redacta «Se activó/desactivó el módulo …» a partir de `extra_data`.
- `app/Services/ModuleManager.php`: cada cambio de estado escribe un `Event` **dentro de la misma transacción** que el `Setting`. Guarda `module`, `module_label`, `enabled_before`, `enabled_after`, `created_by` y el timestamp. Un guardado en bloque genera un evento por módulo; un update sin cambios no genera nada.
- Migración `update_events_type_constraint_for_module_toggled_events`: amplía el CHECK de `events` en MySQL derivando la lista de `EventType::cases()`; SQLite no aplica.
- Vistas: iconos propios para los eventos nuevos en la Bitácora y en la tarjeta de actividad del escritorio. El filtro por tipo ya se arma desde el enum, así que los tipos nuevos aparecen automáticamente.
- Pruebas: auditoría por cambio, actor, estado anterior/nuevo, descripción y ausencia de eventos cuando no hay cambio; también se verifica el actor en el toggle desde la pantalla. Suite completa: **48 tests, 47 en verde**, 182 assertions; el único fallo es el preexistente de `ExampleTest`.

**Comportamiento:** `Sistema → Bitácora` muestra cada activación/desactivación con su actor y permite filtrar por **Cambio de módulo**. El registro es transaccional: no puede quedar un evento de un cambio que no se persistió.

### Fase 6 — completada (2026-11-16)

**Portal "Mi cuenta" desactivado**
- `app/Http/Middleware/EnsurePortalAvailable.php` (alias `portal`): si el módulo `portal` está apagado responde la vista amigable con estado **503** antes de evaluar `can:clients.portal`. Se aplicó a las 11 rutas del portal, incluida la impresión del estado de cuenta y las acciones POST/PUT.
- `resources/views/portal/unavailable.blade.php`: explica que el acceso está desactivado temporalmente, que los datos siguen registrados y ofrece cerrar sesión. El cliente ya no recibe un 403 seco.
- El login sigue enviando al cliente a `portal.index`; si el módulo está apagado, ahora ve esta pantalla. Reactivar el módulo restaura el portal de inmediato.

**Pruebas de integración y verificación**
- `tests/Feature/PortalUnavailableTest.php`: portal activo, pantalla 503, bloqueo de todas las pantallas del portal, reactivación inmediata y 403 para roles sin `clients.portal`.
- `tests/Feature/ModuleIntegrationTest.php`: bloqueo consistente en menú, web, API y exportación; **desactivar no borra datos** (Client/Loan intactos) y reactivar restaura el acceso; módulos núcleo disponibles para un Admin; instalación sin filas `module.*` funciona como siempre.
- Verificación manual: `route:list -v` confirma el orden `Authenticate → EnsurePortalAvailable → Authorize:clients.portal` en las 11 rutas del portal; `view:cache` compila todas las vistas.
- Suite completa: **57 tests, 57 en verde**, 212 assertions. Pint OK.

### Corrección de `ExampleTest` (2026-11-16)

- El test de scaffold esperaba 200 en `/`, pero la raíz está dentro del grupo `auth` y redirige al login. Se ajustó la expectativa a `assertRedirect(route('login'))`. **La suite completa queda en verde.**

### Fase 7 — completada (2026-11-16)

**Modelo de permisos por rol (mínimo privilegio)**
- `Role::restrictableCases()` / `Role::isRestrictable()`: sólo `Supervisor`, `Cajero` y `Cobrador` admiten restricciones. El `Admin` conserva el bypass total y el `Cliente` sólo usa el portal.
- `Module::restrictableCases()` / `Module::isRestrictable()`: todos los módulos no núcleo excepto `portal`. Las restricciones **sólo deniegan**; nunca agregan una habilidad que el rol no tenga.
- Persistencia en `settings` como deny-list: `module_access.<rol>.<módulo> = '0'`. La ausencia de fila significa permitido, así que una instalación nueva se comporta como siempre.
- `app/Services/ModuleManager.php`: cache `modules.role_access` junto al cache de estados, `roleAllows()`, `roleDenied()`, `roleDeniedModules()`, `statesForRole()` y `updateRoleAccess()` atómico. `forgetCache()` invalida ambos mapas.
- Propagación de dependencias: si un rol pierde `clients`, también pierde `loans`, `installments`, `payments`, `collection-visits`, `contracts`, `client-documents` y `profitability`. Se calcula al leer, de modo que reactivar el módulo base no deja filas derivadas huérfanas.

**Enforcement**
- `AppServiceProvider::registerAbilities()`: después del estado global, `Gate::before` consulta `roleAllows()` para el rol del usuario. Un módulo denegado responde 403 en web/API y desaparece del menú (`@can`).
- `EnsureModuleEnabled`: además del estado global, aplica la restricción del rol. Cubre las exportaciones, que comparten `exports.run` y no heredan el módulo desde la habilidad.
- `DashboardController`: `enabledModules` usa `statesForRole()`, así que no consulta ni muestra widgets de módulos denegados al rol.

**Pantalla y auditoría**
- `resources/views/modules/index.blade.php`: matriz **módulos × roles** debajo del catálogo, con un switch por celda y la aclaración de que las restricciones sólo quitan permisos.
- Ruta `PUT modules/roles` (`modules.role-access.update`) con `can:modules.manage` y throttling; `UpdateModuleAccessRequest` valida claves de rol/módulo contra el catálogo restringible.
- `EventType::ModuleAccessChanged` (**Cambio de acceso por rol**): un evento por par rol/módulo modificado, dentro de la transacción del `Setting`, con `role`, `role_label`, `module`, `module_label`, `allowed_before`, `allowed_after` y actor. Rehabilitar un módulo borra la fila y también audita el cambio.
- Migración `update_events_type_constraint_for_module_access_events`: amplía el CHECK de `events` en MySQL derivando `EventType::cases()`; en SQLite no aplica.

**Pruebas**
- `tests/Feature/ModuleRoleAccessTest.php` (14 pruebas): defaults sin filas, Admin/Cliente exentos, denegación en Gate/ruta/menú, independencia entre roles, cascada de dependientes, API y exportación, deny-list persistida y reversible, dashboard adaptado, validación atómica sin escrituras parciales, rechazo de roles/módulos fuera del catálogo, guardado desde la pantalla con auditoría, 403 para Admin común y matriz visible.
- Suite completa: **71 tests, 71 en verde**, 313 assertions. Pint OK, `view:cache` OK y migración local aplicada.

**Despliegue:** `php artisan migrate` para ampliar el CHECK de `events` en MySQL. No hay datos que migrar: la matriz arranca con todos los módulos permitidos para todos los roles.

### Pendiente

- Sin pendientes funcionales. La Fase 7 se implementó con permisos por rol; una restricción por usuario individual queda como extensión futura si se necesita.
