<?php

use App\Enums\EventType;
use App\Enums\Module;
use App\Enums\Role;
use App\Models\Event;
use App\Models\Setting;
use App\Models\User;
use App\Services\ModuleManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('allows every restrictable module to every role by default', function () {
    $manager = app(ModuleManager::class);

    foreach (Role::restrictableCases() as $role) {
        expect($manager->roleDeniedModules($role))->toBe([]);

        foreach (Module::restrictableCases() as $module) {
            expect($manager->roleAllows($role, $module))->toBeTrue();
        }
    }

    expect(DB::table('settings')->where('key', 'like', 'module_access.%')->count())->toBe(0);
});

it('never restricts the administrator or the client role', function () {
    $manager = app(ModuleManager::class);

    // Una fila huérfana para el Admin no debe tener efecto: el rol no está en
    // el catálogo restringible y conserva su acceso total.
    Setting::set('module_access.admin.reports', '0');
    $manager->forgetCache();

    expect($manager->roleAllows(Role::Admin, Module::Reports))->toBeTrue()
        ->and($manager->roleAllows(Role::Cliente, Module::Clients))->toBeTrue()
        ->and($manager->roleAllows(Role::Cliente, Module::Portal))->toBeTrue();

    $admin = User::factory()->create(['role' => Role::Admin]);

    $this->actingAs($admin)
        ->get(route('reports.index'))
        ->assertOk();
});

it('denies a module to a role at the gate, route and menu level', function () {
    app(ModuleManager::class)->updateRoleAccess([
        Role::Supervisor->value => [Module::Reports->value => false],
    ]);

    $supervisor = User::factory()->create(['role' => Role::Supervisor]);
    $this->actingAs($supervisor);

    expect($supervisor->can('reports.view'))->toBeFalse();

    $this->get(route('reports.index'))->assertForbidden();

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee(route('reports.index'));
});

it('keeps a module available to the other roles when only one is restricted', function () {
    $manager = app(ModuleManager::class);

    $manager->updateRoleAccess([
        Role::Supervisor->value => [Module::Reports->value => false],
    ]);

    expect($manager->roleAllows(Role::Supervisor, Module::Reports))->toBeFalse()
        ->and($manager->roleAllows(Role::Cajero, Module::Reports))->toBeTrue()
        ->and($manager->roleAllows(Role::Cobrador, Module::Reports))->toBeTrue();

    $cajero = User::factory()->create(['role' => Role::Cajero]);

    $this->actingAs($cajero)
        ->get(route('reports.index'))
        ->assertOk();
});

it('propagates a denial to every dependent module', function () {
    $manager = app(ModuleManager::class);

    $manager->updateRoleAccess([
        Role::Cajero->value => [Module::Clients->value => false],
    ]);

    foreach ([
        Module::Clients,
        Module::Loans,
        Module::Installments,
        Module::Payments,
        Module::CollectionVisits,
        Module::Contracts,
        Module::ClientDocuments,
        Module::Profitability,
    ] as $module) {
        expect($manager->roleAllows(Role::Cajero, $module))->toBeFalse();
    }

    expect($manager->roleAllows(Role::Cajero, Module::Reports))->toBeTrue()
        ->and($manager->roleAllows(Role::Cajero, Module::CashMovements))->toBeTrue();

    $cajero = User::factory()->create(['role' => Role::Cajero]);
    $this->actingAs($cajero);

    $this->get(route('clients.index'))->assertForbidden();
    $this->get(route('loans.index'))->assertForbidden();
});

it('blocks the api and the export path with the role restriction', function () {
    $manager = app(ModuleManager::class);

    $manager->updateRoleAccess([
        Role::Supervisor->value => [
            Module::Clients->value => false,
            Module::Loans->value => false,
        ],
    ]);

    $supervisor = User::factory()->create(['role' => Role::Supervisor]);

    $this->actingAs($supervisor);

    // La exportación comparte "exports.run": el Gate la deja pasar porque el
    // módulo Exportaciones sigue activo, y el middleware de módulo aplica la
    // restricción del rol sobre Préstamos.
    $this->get(route('export.loans'))->assertNotFound();

    Sanctum::actingAs($supervisor);

    $this->getJson('/api/clients')->assertForbidden();
    $this->getJson('/api/loans')->assertForbidden();
});

it('persists denials as a deny-list and removes the row when access returns', function () {
    $manager = app(ModuleManager::class);

    $manager->updateRoleAccess([
        Role::Cajero->value => [Module::Reports->value => false],
    ]);

    expect(Setting::query()->where('key', 'module_access.cajero.reports')->value('value'))->toBe('0')
        ->and($manager->roleAllows(Role::Cajero, Module::Reports))->toBeFalse();

    $manager->updateRoleAccess([
        Role::Cajero->value => [Module::Reports->value => true],
    ]);

    expect(Setting::query()->where('key', 'module_access.cajero.reports')->exists())->toBeFalse()
        ->and($manager->roleAllows(Role::Cajero, Module::Reports))->toBeTrue();
});

it('hides the widgets of a module denied to the role on the dashboard', function () {
    $manager = app(ModuleManager::class);

    $manager->updateRoleAccess([
        Role::Cajero->value => [Module::Payments->value => false],
    ]);

    $cajero = User::factory()->create(['role' => Role::Cajero]);

    $this->actingAs($cajero)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('Ingresos del mes');
});

it('rejects an invalid matrix without saving anything', function () {
    $manager = app(ModuleManager::class);

    expect(fn () => $manager->updateRoleAccess([
        Role::Supervisor->value => [Module::Reports->value => false],
        Role::Cajero->value => ['not-a-module' => false],
    ]))->toThrow(InvalidArgumentException::class);

    expect(DB::table('settings')->where('key', 'like', 'module_access.%')->count())->toBe(0)
        ->and(Event::where('event_type', EventType::ModuleAccessChanged)->count())->toBe(0)
        ->and($manager->roleAllows(Role::Supervisor, Module::Reports))->toBeTrue();
});

it('rejects roles and modules outside the restrictable catalogue', function () {
    $manager = app(ModuleManager::class);

    expect(fn () => $manager->updateRoleAccess([
        Role::Admin->value => [Module::Reports->value => false],
    ]))->toThrow(InvalidArgumentException::class, 'Rol no restringible');

    expect(fn () => $manager->updateRoleAccess([
        Role::Cajero->value => [Module::Settings->value => false],
    ]))->toThrow(InvalidArgumentException::class, 'Módulo no restringible');
});

it('lets a super administrator save the matrix and audits every change', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $this->actingAs($superAdmin);

    $this->put(route('modules.role-access.update'), [
        'access' => [
            Role::Supervisor->value => [Module::Reports->value => '0'],
            Role::Cajero->value => [Module::Reports->value => '1'],
        ],
    ])->assertRedirect(route('modules.index'));

    expect(app(ModuleManager::class)->roleAllows(Role::Supervisor, Module::Reports))->toBeFalse();

    $event = Event::query()->where('event_type', EventType::ModuleAccessChanged)->sole();

    expect($event->created_by)->toBe($superAdmin->id)
        ->and($event->extra_data['role'])->toBe(Role::Supervisor->value)
        ->and($event->extra_data['module'])->toBe(Module::Reports->value)
        ->and($event->extra_data['allowed_before'])->toBeTrue()
        ->and($event->extra_data['allowed_after'])->toBeFalse()
        ->and($event->description)->toBe('Se quitó el módulo Reportes al rol Supervisor.');
});

it('forbids a regular administrator from changing the role matrix', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);
    $this->actingAs($admin);

    $this->put(route('modules.role-access.update'), [
        'access' => [
            Role::Cajero->value => [Module::Reports->value => '0'],
        ],
    ])->assertForbidden();

    expect(DB::table('settings')->where('key', 'like', 'module_access.%')->count())->toBe(0);
});

it('shows the access matrix on the modules screen', function () {
    $superAdmin = User::factory()->superAdmin()->create();

    $this->actingAs($superAdmin)
        ->get(route('modules.index'))
        ->assertOk()
        ->assertSee('Accesos por rol')
        ->assertSee('Supervisor')
        ->assertSee('Cajero')
        ->assertSee('Cobrador')
        ->assertSee('Las restricciones')
        ->assertSee('sólo quitan permisos', false);
});

it('rejects a non-restrictable role from the screen', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $this->actingAs($superAdmin);

    $this->from(route('modules.index'))
        ->followingRedirects()
        ->put(route('modules.role-access.update'), [
            'access' => [
                Role::Admin->value => [Module::Reports->value => '0'],
            ],
        ])
        ->assertOk()
        ->assertSee('no admite restricciones');
});
