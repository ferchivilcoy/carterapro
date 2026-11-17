<?php

namespace App\Enums;

/**
 * Catálogo de módulos funcionales del sistema. Cada módulo agrupa las
 * habilidades que lo protegen y declara de qué otros módulos depende, para que
 * el estado activo/inactivo se valide de forma consistente.
 *
 * Los módulos núcleo no pueden desactivarse: sin ellos el sistema quedaría
 * inaccesible incluso para un superusuario.
 */
enum Module: string
{
    case Dashboard = 'dashboard';
    case Clients = 'clients';
    case Loans = 'loans';
    case Installments = 'installments';
    case Payments = 'payments';
    case CollectionVisits = 'collection-visits';
    case CashMovements = 'cash-movements';
    case Contracts = 'contracts';
    case ClientDocuments = 'client-documents';
    case Reports = 'reports';
    case Profitability = 'profitability';
    case Events = 'events';
    case Portal = 'portal';
    case Exports = 'exports';
    case Settings = 'settings';
    case Users = 'users';
    case Backup = 'backup';
    case Modules = 'modules';

    public function label(): string
    {
        return match ($this) {
            self::Dashboard => 'Escritorio',
            self::Clients => 'Clientes',
            self::Loans => 'Préstamos',
            self::Installments => 'Cuotas',
            self::Payments => 'Cobros',
            self::CollectionVisits => 'Cobranzas',
            self::CashMovements => 'Caja',
            self::Contracts => 'Contratos',
            self::ClientDocuments => 'Documentos de clientes',
            self::Reports => 'Reportes',
            self::Profitability => 'Rentabilidad',
            self::Events => 'Bitácora',
            self::Portal => 'Mi cuenta',
            self::Exports => 'Exportaciones',
            self::Settings => 'Configuración',
            self::Users => 'Usuarios',
            self::Backup => 'Copias de seguridad',
            self::Modules => 'Gestión de módulos',
        };
    }

    /**
     * Un módulo núcleo siempre está disponible: no se puede desactivar desde
     * la pantalla de módulos y no depende de configuración alguna.
     */
    public function isCore(): bool
    {
        return in_array($this, [
            self::Dashboard,
            self::Settings,
            self::Users,
            self::Backup,
            self::Modules,
        ], true);
    }

    /**
     * Un módulo puede restringirse por rol cuando no es núcleo y no es el
     * portal del cliente: los roles internos nunca acceden al portal y el rol
     * Cliente queda exento de las restricciones.
     */
    public function isRestrictable(): bool
    {
        return ! $this->isCore() && $this !== self::Portal;
    }

    /**
     * Módulos que aparecen en la matriz de accesos por rol.
     *
     * @return array<int, self>
     */
    public static function restrictableCases(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $module): bool => $module->isRestrictable(),
        ));
    }

    public function description(): string
    {
        return match ($this) {
            self::Dashboard => 'Resumen operativo, cobros del día y estado de la cartera.',
            self::Clients => 'Alta, edición y ficha de clientes.',
            self::Loans => 'Otorgamiento, seguimiento y refinanciación de préstamos.',
            self::Installments => 'Calendario de cuotas, vencimientos y mora.',
            self::Payments => 'Registro, verificación y anulación de cobros.',
            self::CollectionVisits => 'Gestión manual de cobranzas y contactos.',
            self::CashMovements => 'Ingresos, egresos y cierre diario de caja.',
            self::Contracts => 'Generación y descarga de contratos en PDF.',
            self::ClientDocuments => 'Documentación respaldatoria de cada cliente.',
            self::Reports => 'Cartera, mora, caja y cobranzas por cobrador.',
            self::Profitability => 'Ingresos, costos y resultado del negocio.',
            self::Events => 'Bitácora de auditoría de las acciones del sistema.',
            self::Portal => 'Acceso de los clientes a su cuenta, cuotas y pagos.',
            self::Exports => 'Descarga de listados en CSV.',
            self::Settings => 'Parámetros generales y valores por defecto.',
            self::Users => 'Cuentas del equipo, roles y accesos.',
            self::Backup => 'Copias de seguridad de la base de datos.',
            self::Modules => 'Activación y desactivación de módulos del sistema.',
        };
    }

    /**
     * Habilidades que quedan cubiertas por el módulo. Apagar el módulo niega
     * todas ellas en la capa Gate, tanto en el panel como en la API.
     *
     * @return array<int, Ability>
     */
    public function abilities(): array
    {
        return match ($this) {
            self::Dashboard => [Ability::DashboardView],
            self::Clients => [Ability::ClientsView, Ability::ClientsManage],
            self::Loans => [Ability::LoansView, Ability::LoansManage],
            self::Installments => [Ability::InstallmentsView],
            self::Payments => [Ability::PaymentsView, Ability::PaymentsManage],
            self::CollectionVisits => [Ability::CollectionVisitsView, Ability::CollectionVisitsManage],
            self::CashMovements => [Ability::CashMovementsView, Ability::CashMovementsManage],
            self::Contracts => [Ability::ContractsView, Ability::ContractsManage],
            self::ClientDocuments => [Ability::ClientDocumentsView, Ability::ClientDocumentsManage],
            self::Reports => [Ability::ReportsView],
            self::Profitability => [Ability::ProfitabilityView],
            self::Events => [Ability::EventsView],
            self::Portal => [Ability::ClientPortal],
            self::Exports => [Ability::ExportsRun],
            self::Settings => [Ability::SettingsManage],
            self::Users => [Ability::UsersManage],
            self::Backup => [Ability::BackupManage],
            self::Modules => [Ability::ModulesManage],
        };
    }

    /**
     * Módulos que deben estar activos para que este funcione. Se valida en
     * ambos sentidos: no se apaga un módulo con dependientes activos y no se
     * activa uno cuyas dependencias estén apagadas.
     *
     * @return array<int, self>
     */
    public function dependsOn(): array
    {
        return match ($this) {
            self::Loans => [self::Clients],
            self::Installments => [self::Loans],
            self::Payments => [self::Loans, self::Installments],
            self::CollectionVisits => [self::Clients, self::Loans],
            self::Contracts => [self::Clients, self::Loans],
            self::ClientDocuments => [self::Clients],
            self::Profitability => [self::Loans, self::Payments],
            self::Portal => [self::Clients],
            default => [],
        };
    }

    /**
     * Todos los módulos arrancan activos en una instalación nueva. El estado
     * persistido sólo guarda la excepción (módulo apagado), de modo que una
     * base sin filas de módulos se comporta como el sistema actual.
     */
    public function defaultEnabled(): bool
    {
        return true;
    }

    /**
     * Clave de la tabla settings donde se persiste el estado del módulo.
     */
    public function settingKey(): string
    {
        return 'module.'.$this->value.'.enabled';
    }

    /**
     * Módulo al que pertenece una habilidad. Devuelve null para habilidades
     * que no formen parte de ningún módulo.
     */
    public static function forAbility(Ability $ability): ?self
    {
        return match ($ability) {
            Ability::DashboardView => self::Dashboard,
            Ability::ClientsView, Ability::ClientsManage => self::Clients,
            Ability::LoansView, Ability::LoansManage => self::Loans,
            Ability::InstallmentsView => self::Installments,
            Ability::PaymentsView, Ability::PaymentsManage => self::Payments,
            Ability::CollectionVisitsView, Ability::CollectionVisitsManage => self::CollectionVisits,
            Ability::CashMovementsView, Ability::CashMovementsManage => self::CashMovements,
            Ability::ContractsView, Ability::ContractsManage => self::Contracts,
            Ability::ClientDocumentsView, Ability::ClientDocumentsManage => self::ClientDocuments,
            Ability::ReportsView => self::Reports,
            Ability::ProfitabilityView => self::Profitability,
            Ability::EventsView => self::Events,
            Ability::ClientPortal => self::Portal,
            Ability::ExportsRun => self::Exports,
            Ability::SettingsManage => self::Settings,
            Ability::UsersManage => self::Users,
            Ability::BackupManage => self::Backup,
            Ability::ModulesManage => self::Modules,
            default => null,
        };
    }
}
