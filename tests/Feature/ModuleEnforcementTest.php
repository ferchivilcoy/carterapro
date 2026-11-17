<?php

use App\Enums\Ability;
use App\Enums\Module;
use App\Enums\Role;
use App\Models\User;
use App\Services\ModuleManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('denies a disabled module to the administrator at the gate level', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);
    $this->actingAs($admin);

    app(ModuleManager::class)->disable(Module::Events);

    expect($admin->can(Ability::EventsView->value))->toBeFalse()
        ->and($admin->can(Ability::ClientsView->value))->toBeTrue();
});

it('blocks the module route even for the administrator', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);
    $this->actingAs($admin);

    app(ModuleManager::class)->disable(Module::Events);

    $this->get(route('events.index'))->assertForbidden();
});

it('restores access when the module is enabled again', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);
    $this->actingAs($admin);

    $manager = app(ModuleManager::class);

    $manager->disable(Module::Events);
    $this->get(route('events.index'))->assertForbidden();

    $manager->enable(Module::Events);
    $this->get(route('events.index'))->assertOk();
});

it('hides the menu entry of a disabled module', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);
    $this->actingAs($admin);

    app(ModuleManager::class)->disable(Module::Events);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee(route('events.index'));
});

it('blocks the export of a disabled module with a not found response', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);
    $this->actingAs($admin);

    app(ModuleManager::class)->disable(Module::CashMovements);

    $this->get(route('export.caja'))->assertNotFound();
});

it('blocks every export when the exports module is disabled', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);
    $this->actingAs($admin);

    app(ModuleManager::class)->disable(Module::Exports);

    $this->get(route('export.loans'))->assertForbidden();
});

it('hides dashboard widgets of a disabled module', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);
    $this->actingAs($admin);

    app(ModuleManager::class)->disable(Module::Events);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('Actividad reciente');
});

it('renders the dashboard when several modules are disabled', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);
    $this->actingAs($admin);

    app(ModuleManager::class)->update([
        Module::Profitability->value => false,
        Module::Payments->value => false,
        Module::Installments->value => false,
        Module::CollectionVisits->value => false,
        Module::Contracts->value => false,
        Module::Loans->value => false,
        Module::Events->value => false,
        Module::CashMovements->value => false,
        Module::Reports->value => false,
        Module::Exports->value => false,
        Module::ClientDocuments->value => false,
        Module::Portal->value => false,
    ]);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('Evolución de ingresos')
        ->assertDontSee('Bandeja de cobranza')
        ->assertDontSee('Actividad reciente');
});

it('serves the api routes now that api.php is registered', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);

    Sanctum::actingAs($admin);

    $this->getJson('/api/clients')->assertOk();
});

it('blocks the api of a disabled module', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);
    $manager = app(ModuleManager::class);

    $manager->disable(Module::Profitability);
    $manager->disable(Module::Payments);

    Sanctum::actingAs($admin);

    $this->getJson('/api/payments')->assertForbidden();
});
