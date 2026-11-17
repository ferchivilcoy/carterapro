<?php

use App\Enums\Module;
use App\Enums\Role;
use App\Models\Client;
use App\Models\User;
use App\Services\ModuleManager;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function portalClient(): User
{
    $client = Client::factory()->create();

    return User::factory()->create([
        'role' => Role::Cliente,
        'client_id' => $client->id,
    ]);
}

it('serves the portal to a client when the module is enabled', function () {
    $client = portalClient();

    $this->actingAs($client)
        ->get(route('portal.index'))
        ->assertOk()
        ->assertSee('Mi cuenta');
});

it('shows a friendly page when the portal module is disabled', function () {
    $client = portalClient();

    app(ModuleManager::class)->disable(Module::Portal);

    $this->actingAs($client)
        ->get(route('portal.index'))
        ->assertStatus(503)
        ->assertSee('Portal no disponible')
        ->assertSee('siguen registrados')
        ->assertSee('Cerrar sesión');
});

it('blocks every portal screen while the module is disabled', function () {
    $client = portalClient();

    app(ModuleManager::class)->disable(Module::Portal);

    $this->actingAs($client);

    $this->get(route('portal.loans'))->assertStatus(503);
    $this->get(route('portal.installments'))->assertStatus(503);
    $this->get(route('portal.profile'))->assertStatus(503);
});

it('restores the portal as soon as the module is enabled again', function () {
    $client = portalClient();
    $manager = app(ModuleManager::class);

    $manager->disable(Module::Portal);

    $this->actingAs($client)->get(route('portal.index'))->assertStatus(503);

    $manager->enable(Module::Portal);

    $this->get(route('portal.index'))->assertOk();
});

it('still forbids the portal to a role without the portal ability', function () {
    $supervisor = User::factory()->create(['role' => Role::Supervisor]);

    $this->actingAs($supervisor)
        ->get(route('portal.index'))
        ->assertForbidden();
});
