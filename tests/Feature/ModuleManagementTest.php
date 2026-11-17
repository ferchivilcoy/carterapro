<?php

use App\Enums\EventType;
use App\Enums\Module;
use App\Enums\Role;
use App\Models\Event;
use App\Models\User;
use App\Services\ModuleManager;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('requires the modules ability to open the screen', function () {
    $supervisor = User::factory()->create(['role' => Role::Supervisor]);

    $this->actingAs($supervisor)
        ->get(route('modules.index'))
        ->assertForbidden();
});

it('shows the module screen to a super administrator', function () {
    $superAdmin = User::factory()->superAdmin()->create();

    $this->actingAs($superAdmin)
        ->get(route('modules.index'))
        ->assertOk()
        ->assertSee('Catálogo de módulos')
        ->assertSee('Siempre activo');
});

it('toggles a module through the screen', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $this->actingAs($superAdmin);

    $this->put(route('modules.update'), [
        'modules' => [
            Module::Events->value => '0',
        ],
    ])->assertRedirect(route('modules.index'));

    expect(app(ModuleManager::class)->enabled(Module::Events))->toBeFalse();

    $event = Event::query()->where('event_type', EventType::ModuleToggled)->sole();

    expect($event->created_by)->toBe($superAdmin->id)
        ->and($event->extra_data['module'])->toBe(Module::Events->value)
        ->and($event->extra_data['enabled_after'])->toBeFalse();
});

it('rejects disabling a core module from the screen', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $this->actingAs($superAdmin);

    $this->from(route('modules.index'))
        ->followingRedirects()
        ->put(route('modules.update'), [
            'modules' => [
                Module::Settings->value => '0',
            ],
        ])
        ->assertOk()
        ->assertSee('No se pudieron guardar los cambios')
        ->assertSee('núcleo del sistema');

    expect(app(ModuleManager::class)->enabled(Module::Settings))->toBeTrue();
});

it('disables a module and its dependents in one save', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $this->actingAs($superAdmin);

    $this->put(route('modules.update'), [
        'modules' => [
            Module::Profitability->value => '0',
            Module::Payments->value => '0',
            Module::Installments->value => '0',
            Module::CollectionVisits->value => '0',
            Module::Contracts->value => '0',
            Module::Loans->value => '0',
        ],
    ])->assertRedirect(route('modules.index'));

    $manager = app(ModuleManager::class);

    expect($manager->enabled(Module::Loans))->toBeFalse()
        ->and($manager->enabled(Module::Payments))->toBeFalse()
        ->and($manager->enabled(Module::Clients))->toBeTrue();
});

it('rejects leaving an enabled dependent behind', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $this->actingAs($superAdmin);

    $this->from(route('modules.index'))
        ->followingRedirects()
        ->put(route('modules.update'), [
            'modules' => [
                Module::Loans->value => '0',
            ],
        ])
        ->assertOk()
        ->assertSee('No se pudieron guardar los cambios')
        ->assertSee('todavía dependen de él');

    expect(app(ModuleManager::class)->enabled(Module::Loans))->toBeTrue();
});

it('rejects unknown module keys from the screen', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $this->actingAs($superAdmin);

    $this->from(route('modules.index'))
        ->followingRedirects()
        ->put(route('modules.update'), [
            'modules' => [
                'not-a-module' => '0',
            ],
        ])
        ->assertOk()
        ->assertSee('No se pudieron guardar los cambios')
        ->assertSee('no existe');
});
