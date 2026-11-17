<?php

use App\Enums\EventType;
use App\Enums\Module;
use App\Enums\Role;
use App\Models\Event;
use App\Models\User;
use App\Services\ModuleManager;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('forbids a regular administrator from managing modules', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);

    $this->actingAs($admin)
        ->get(route('modules.index'))
        ->assertForbidden();

    expect($admin->can('modules.manage'))->toBeFalse();
});

it('allows a super administrator to manage modules', function () {
    $superAdmin = User::factory()->superAdmin()->create();

    $this->actingAs($superAdmin)
        ->get(route('modules.index'))
        ->assertOk();

    expect($superAdmin->can('modules.manage'))->toBeTrue();
});

it('requires the administrator role for the super administrator flag', function () {
    $user = User::factory()->create([
        'role' => Role::Supervisor,
        'is_super_admin' => true,
    ]);

    expect($user->isSuperAdmin())->toBeFalse();
});

it('promotes an existing account with the command', function () {
    $user = User::factory()->create(['role' => Role::Admin]);

    $this->artisan('users:promote-super-admin', ['email' => $user->email])
        ->assertSuccessful();

    expect($user->fresh()->isSuperAdmin())->toBeTrue()
        ->and(Event::where('event_type', EventType::SuperAdminGranted)->count())->toBe(1);
});

it('promotes an account of another role to administrator', function () {
    $user = User::factory()->create(['role' => Role::Supervisor]);

    $this->artisan('users:promote-super-admin', ['email' => $user->email])
        ->assertSuccessful();

    $user->refresh();

    expect($user->role)->toBe(Role::Admin)
        ->and($user->isSuperAdmin())->toBeTrue();
});

it('is idempotent when the account is already a super administrator', function () {
    $user = User::factory()->superAdmin()->create();

    $this->artisan('users:promote-super-admin', ['email' => $user->email])
        ->assertSuccessful();

    expect(Event::where('event_type', EventType::SuperAdminGranted)->count())->toBe(0);
});

it('fails when the account does not exist', function () {
    $this->artisan('users:promote-super-admin', ['email' => 'nadie@example.com'])
        ->assertFailed();
});

it('rejects the super administrator flag from a regular administrator', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);
    $target = User::factory()->create(['role' => Role::Admin]);
    $this->actingAs($admin);

    $this->from(route('users.edit', $target))
        ->put(route('users.update', $target), [
            'is_super_admin' => '1',
        ])
        ->assertRedirect(route('users.edit', $target))
        ->assertSessionHasErrors('is_super_admin');

    expect($target->fresh()->isSuperAdmin())->toBeFalse();
});

it('lets a super administrator grant the flag to another administrator', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $target = User::factory()->create(['role' => Role::Admin]);
    $this->actingAs($superAdmin);

    $this->put(route('users.update', $target), [
        'is_super_admin' => '1',
    ])->assertRedirect(route('users.index'));

    expect($target->fresh()->isSuperAdmin())->toBeTrue()
        ->and(Event::where('event_type', EventType::SuperAdminGranted)->count())->toBe(1);
});

it('lets a super administrator revoke the flag from another super administrator', function () {
    $actor = User::factory()->superAdmin()->create();
    $target = User::factory()->superAdmin()->create();
    $this->actingAs($actor);

    $this->put(route('users.update', $target), [
        'is_super_admin' => '0',
    ])->assertRedirect(route('users.index'));

    expect($target->fresh()->isSuperAdmin())->toBeFalse()
        ->and(Event::where('event_type', EventType::SuperAdminRevoked)->count())->toBe(1);
});

it('does not let a super administrator remove their own flag', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $this->actingAs($superAdmin);

    $this->from(route('users.edit', $superAdmin))
        ->put(route('users.update', $superAdmin), [
            'is_super_admin' => '0',
        ])
        ->assertRedirect(route('users.edit', $superAdmin));

    expect($superAdmin->fresh()->isSuperAdmin())->toBeTrue();
});

it('keeps a super administrator from losing the administrator role while flagged', function () {
    $actor = User::factory()->superAdmin()->create();
    $target = User::factory()->superAdmin()->create();
    $this->actingAs($actor);

    $this->from(route('users.edit', $target))
        ->put(route('users.update', $target), [
            'role' => Role::Supervisor->value,
            'is_super_admin' => '1',
        ])
        ->assertRedirect(route('users.edit', $target));

    $target->refresh();

    expect($target->role)->toBe(Role::Admin)
        ->and($target->isSuperAdmin())->toBeTrue();
});

it('does not let a regular administrator deactivate the last active super administrator', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $admin = User::factory()->create(['role' => Role::Admin]);
    $this->actingAs($admin);

    $this->from(route('users.index'))
        ->post(route('users.toggle-active', $superAdmin))
        ->assertRedirect(route('users.index'));

    expect($superAdmin->fresh()->active)->toBeTrue();
});

it('lets a regular administrator deactivate a super administrator when another one remains', function () {
    $first = User::factory()->superAdmin()->create();
    User::factory()->superAdmin()->create();
    $admin = User::factory()->create(['role' => Role::Admin]);
    $this->actingAs($admin);

    $this->post(route('users.toggle-active', $first))
        ->assertRedirect(route('users.index'));

    expect($first->fresh()->active)->toBeFalse();
});

it('still lets a super administrator toggle modules after being promoted', function () {
    $user = User::factory()->create(['role' => Role::Admin]);

    $this->artisan('users:promote-super-admin', ['email' => $user->email])
        ->assertSuccessful();

    $this->actingAs($user->fresh())
        ->put(route('modules.update'), [
            'modules' => [
                Module::Events->value => '0',
            ],
        ])
        ->assertRedirect(route('modules.index'));

    expect(app(ModuleManager::class)->enabled(Module::Events))->toBeFalse();
});
