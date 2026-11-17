<?php

use App\Enums\Ability;
use App\Enums\EventType;
use App\Enums\Module;
use App\Enums\Role;
use App\Models\Event;
use App\Models\Setting;
use App\Models\User;
use App\Services\ModuleManager;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('enables every module by default', function () {
    $manager = app(ModuleManager::class);

    foreach (Module::cases() as $module) {
        expect($manager->enabled($module))->toBeTrue();
    }
});

it('maps every ability to a module', function () {
    foreach (Ability::cases() as $ability) {
        expect(Module::forAbility($ability))->not->toBeNull();
    }
});

it('maps view and manage abilities to the same module', function () {
    expect(Module::forAbility(Ability::ClientsView))->toBe(Module::Clients)
        ->and(Module::forAbility(Ability::ClientsManage))->toBe(Module::Clients)
        ->and(Module::forAbility(Ability::ClientPortal))->toBe(Module::Portal);
});

it('persists a disabled module and invalidates the cache', function () {
    $manager = app(ModuleManager::class);

    $manager->disable(Module::Events);

    expect($manager->enabled(Module::Events))->toBeFalse()
        ->and(Setting::get(Module::Events->settingKey()))->toBe('0');

    // Una instancia nueva no debe conservar el mapa anterior en memoria ni en cache.
    $this->app->forgetInstance(ModuleManager::class);

    expect(app(ModuleManager::class)->enabled(Module::Events))->toBeFalse();
});

it('restores a module when enabled again', function () {
    $manager = app(ModuleManager::class);

    $manager->disable(Module::Exports);
    expect($manager->enabled(Module::Exports))->toBeFalse();

    $manager->enable(Module::Exports);

    expect($manager->enabled(Module::Exports))->toBeTrue()
        ->and(Setting::get(Module::Exports->settingKey()))->toBe('1');
});

it('refuses to disable a core module', function () {
    expect(fn () => app(ModuleManager::class)->disable(Module::Settings))
        ->toThrow(DomainException::class, 'núcleo del sistema');
});

it('refuses to disable a module that other enabled modules depend on', function () {
    expect(fn () => app(ModuleManager::class)->disable(Module::Loans))
        ->toThrow(DomainException::class, 'No se puede desactivar Préstamos');
});

it('refuses to enable a module whose dependencies are disabled', function () {
    $manager = app(ModuleManager::class);

    $manager->disable(Module::Profitability);
    $manager->disable(Module::Payments);
    $manager->disable(Module::Installments);

    expect(fn () => $manager->enable(Module::Payments))
        ->toThrow(DomainException::class, 'Activá primero');
});

it('reports the state of every module in a single map', function () {
    $states = app(ModuleManager::class)->states();

    expect($states)->toHaveCount(count(Module::cases()))
        ->and($states)->toHaveKeys(array_map(
            fn (Module $module): string => $module->value,
            Module::cases(),
        ));
});

it('applies several module changes atomically', function () {
    $manager = app(ModuleManager::class);

    $manager->update([
        Module::Profitability->value => false,
        Module::Payments->value => false,
        Module::Installments->value => false,
        Module::CollectionVisits->value => false,
        Module::Contracts->value => false,
        Module::Loans->value => false,
    ]);

    expect($manager->enabled(Module::Loans))->toBeFalse()
        ->and($manager->enabled(Module::Payments))->toBeFalse()
        ->and($manager->enabled(Module::Clients))->toBeTrue();
});

it('refuses to disable a core module through a batch update', function () {
    expect(fn () => app(ModuleManager::class)->update([Module::Settings->value => false]))
        ->toThrow(DomainException::class, 'núcleo del sistema');
});

it('rejects unknown module keys', function () {
    expect(fn () => app(ModuleManager::class)->update(['not-a-module' => false]))
        ->toThrow(InvalidArgumentException::class);
});

it('records an audit event for every module change', function () {
    $actor = User::factory()->create(['role' => Role::Admin]);
    $this->actingAs($actor);

    app(ModuleManager::class)->update([
        Module::Events->value => false,
        Module::Reports->value => false,
    ]);

    $events = Event::query()
        ->where('event_type', EventType::ModuleToggled)
        ->orderBy('id')
        ->get();

    expect($events)->toHaveCount(2)
        ->and($events->pluck('extra_data.module')->all())
        ->toBe([Module::Events->value, Module::Reports->value])
        ->and($events->first()->description)->toBe('Se desactivó el módulo Bitácora.');

    foreach ($events as $event) {
        expect($event->created_by)->toBe($actor->id)
            ->and($event->extra_data['enabled_before'])->toBeTrue()
            ->and($event->extra_data['enabled_after'])->toBeFalse();
    }
});

it('does not record an audit event when the state does not change', function () {
    app(ModuleManager::class)->update([Module::Events->value => true]);

    expect(Event::where('event_type', EventType::ModuleToggled)->count())->toBe(0);
});
