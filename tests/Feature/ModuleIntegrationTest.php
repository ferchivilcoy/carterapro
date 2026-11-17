<?php

use App\Enums\Module;
use App\Enums\Role;
use App\Models\Client;
use App\Models\Loan;
use App\Models\User;
use App\Services\ModuleManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/**
 * Apaga Préstamos junto con todos sus dependientes en una sola operación,
 * que es la única forma válida de hacerlo.
 */
function disableLoansChain(ModuleManager $manager): void
{
    $manager->update([
        Module::Profitability->value => false,
        Module::Payments->value => false,
        Module::Installments->value => false,
        Module::CollectionVisits->value => false,
        Module::Contracts->value => false,
        Module::Loans->value => false,
    ]);
}

it('blocks a disabled module consistently across menu, web, api and export', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $this->actingAs($superAdmin);

    disableLoansChain(app(ModuleManager::class));

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee(route('loans.index'));

    $this->get(route('loans.index'))->assertForbidden();
    $this->get(route('export.loans'))->assertNotFound();

    Sanctum::actingAs($superAdmin);

    $this->getJson('/api/loans')->assertForbidden();
});

it('does not delete data when a module is disabled and restores access on enable', function () {
    $client = Client::factory()->create();
    $loan = Loan::factory()->for($client)->create();

    $manager = app(ModuleManager::class);
    disableLoansChain($manager);

    expect(Client::count())->toBe(1)
        ->and(Loan::count())->toBe(1)
        ->and($loan->fresh()->pending_balance)->toEqual($loan->pending_balance);

    $manager->enable(Module::Loans);

    $admin = User::factory()->create(['role' => Role::Admin]);

    $this->actingAs($admin)
        ->get(route('loans.index'))
        ->assertOk();
});

it('keeps the core screens available to a regular administrator', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);

    app(ModuleManager::class)->update([
        Module::Events->value => false,
        Module::Reports->value => false,
        Module::Exports->value => false,
    ]);

    $this->actingAs($admin);

    $this->get(route('dashboard'))->assertOk();
    $this->get(route('settings.index'))->assertOk();
    $this->get(route('users.index'))->assertOk();
});

it('behaves like a fresh installation when no module settings exist', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);

    expect(DB::table('settings')
        ->where('key', 'like', 'module.%')
        ->count())->toBe(0);

    $this->actingAs($admin);

    $this->get(route('dashboard'))->assertOk();
    $this->get(route('clients.index'))->assertOk();
    $this->get(route('loans.index'))->assertOk();
    $this->get(route('payments.index'))->assertOk();
});
