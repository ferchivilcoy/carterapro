<?php

use App\Enums\Ability;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\CashMovements\CashMovementController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\ClientDocuments\ClientDocumentController;
use App\Http\Controllers\ClientPortalController;
use App\Http\Controllers\CollectionVisits\CollectionVisitController;
use App\Http\Controllers\Contracts\ContractController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\ExecutiveDashboardController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\InstallmentController;
use App\Http\Controllers\LoanController;
use App\Http\Controllers\ModuleController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PrintController;
use App\Http\Controllers\Profitability\ProfitabilityController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\UserController;
use App\Http\Middleware\RedirectClientsToPortal;
use Illuminate\Support\Facades\Route;

Route::get('login', [LoginController::class, 'create'])
    ->middleware('guest')
    ->name('login');
Route::post('login', [LoginController::class, 'store'])
    ->middleware('guest')
    ->name('login.store');
// Se acepta GET además de POST porque el logout también se invoca desde la
// barra de direcciones. El POST sigue siendo la vía preferida y conserva la
// protección CSRF que aporta el middleware "web".
Route::match(['get', 'post'], 'logout', [LoginController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

// Recuperación de contraseña: se construye sobre el broker "users" y el
// nombre "password.reset" es el que usa la notificación del correo para armar
// el enlace. Los POST llevan throttle por IP además del límite por usuario del
// broker, para que nadie use el formulario como generador de correos.
Route::get('forgot-password', [PasswordResetController::class, 'request'])
    ->middleware('guest')
    ->name('password.request');
Route::post('forgot-password', [PasswordResetController::class, 'email'])
    ->middleware(['guest', 'throttle:6,1'])
    ->name('password.email');
Route::get('reset-password/{token}', [PasswordResetController::class, 'reset'])
    ->middleware('guest')
    ->name('password.reset');
Route::post('reset-password', [PasswordResetController::class, 'update'])
    ->middleware(['guest', 'throttle:6,1'])
    ->name('password.update');

Route::middleware('auth')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])
        ->middleware([
            // Runs before the ability check: a Cliente reaching the site root
            // is a navigation case, not an authorization failure, so it goes
            // to its portal instead of getting a 403.
            RedirectClientsToPortal::class,
            'can:'.Ability::DashboardView->value,
        ])
        ->name('dashboard');

    Route::get('payments/print', [PrintController::class, 'payments'])
        ->middleware('can:'.Ability::PaymentsView->value)
        ->name('print.payments');
    Route::get('installments/print', [PrintController::class, 'installments'])
        ->middleware('can:'.Ability::InstallmentsView->value)
        ->name('print.installments');
    Route::get('print/due-today', [PrintController::class, 'dueToday'])
        ->middleware('can:'.Ability::InstallmentsView->value)
        ->name('print.due-today');

    // Declared before the resources so "export" is never bound as a route model
    // key ({loan}, {payment}). The module middleware is required here because
    // these routes share the transversal "exports.run" ability, so the Gate
    // cannot infer which module they belong to.
    Route::get('loans/export', [ExportController::class, 'loans'])
        ->middleware(['can:'.Ability::ExportsRun->value, 'module:loans'])
        ->name('export.loans');
    Route::get('payments/export', [ExportController::class, 'payments'])
        ->middleware(['can:'.Ability::ExportsRun->value, 'module:payments'])
        ->name('export.payments');
    Route::get('installments/export', [ExportController::class, 'installments'])
        ->middleware(['can:'.Ability::ExportsRun->value, 'module:installments'])
        ->name('export.installments');
    Route::get('clients/export', [ExportController::class, 'clients'])
        ->middleware(['can:'.Ability::ExportsRun->value, 'module:clients'])
        ->name('export.clients');
    Route::get('cobranzas/export', [ExportController::class, 'cobranzas'])
        ->middleware(['can:'.Ability::ExportsRun->value, 'module:collection-visits'])
        ->name('export.cobranzas');
    Route::get('caja/export', [ExportController::class, 'caja'])
        ->middleware(['can:'.Ability::ExportsRun->value, 'module:cash-movements'])
        ->name('export.caja');
    Route::get('rentabilidad/export', [ExportController::class, 'rentabilidad'])
        ->middleware(['can:'.Ability::ExportsRun->value, 'module:profitability'])
        ->name('export.rentabilidad');

    // The manage group is declared first on purpose: "clients/create" has to
    // be registered before "clients/{client}" or the literal path would be
    // captured by the dynamic segment and answer 404.
    Route::middleware('can:'.Ability::ClientsManage->value)->group(function () {
        Route::resource('clients', ClientController::class)
            ->only(['create', 'store', 'edit', 'update', 'destroy']);
    });

    Route::middleware('can:'.Ability::ClientsView->value)->group(function () {
        Route::resource('clients', ClientController::class)->only(['index', 'show']);
        Route::get('clients/{client}/print/statement', [PrintController::class, 'clientStatement'])
            ->name('print.clients.statement');
    });

    Route::middleware('can:'.Ability::LoansManage->value)->group(function () {
        Route::post('loans/preview', [LoanController::class, 'preview'])->name('loans.preview');
        Route::resource('loans', LoanController::class)
            ->only(['create', 'store', 'edit', 'update']);
        Route::post('loans/{loan}/cancel', [LoanController::class, 'cancel'])->name('loans.cancel');
        Route::post('loans/{loan}/refinance', [LoanController::class, 'refinance'])->name('loans.refinance');
        Route::post('loans/{loan}/prepayment', [LoanController::class, 'prepayment'])->name('loans.prepayment');
        // Solicitud que el cliente levantó desde su portal: marcarla como
        // atendida es una gestión del equipo, no una refinanciación automática.
        Route::post('loans/{loan}/refinance-requests/{refinanceRequest}/attend', [LoanController::class, 'attendRefinanceRequest'])
            ->name('loans.refinance-requests.attend');
    });

    Route::middleware('can:'.Ability::LoansView->value)->group(function () {
        Route::resource('loans', LoanController::class)->only(['index', 'show']);
        Route::get('loans/{loan}/print', [PrintController::class, 'loanPlan'])
            ->name('print.loans.plan');
    });

    Route::resource('installments', InstallmentController::class)
        ->only(['index'])
        ->middleware('can:'.Ability::InstallmentsView->value);

    // Regla de la matriz: "view" sólo para leer, "manage" para toda mutación.
    // El grupo manage se declara primero para que "payments/create" no sea
    // capturado por "payments/{payment}".
    Route::middleware('can:'.Ability::PaymentsManage->value)->group(function () {
        Route::resource('payments', PaymentController::class)->only(['create', 'store']);
        Route::post('payments/{payment}/cancel', [PaymentController::class, 'cancel'])->name('payments.cancel');
        Route::post('payments/{payment}/verify', [PaymentController::class, 'verify'])->name('payments.verify');
    });

    Route::middleware('can:'.Ability::PaymentsView->value)->group(function () {
        Route::resource('payments', PaymentController::class)->only(['index', 'show']);
        Route::get('payments/{payment}/print', [PrintController::class, 'paymentReceipt'])
            ->name('print.payments.receipt');
        Route::get('payments/{payment}/comprobante', [PaymentController::class, 'comprobante'])
            ->name('payments.comprobante');
    });

    Route::get('events', [EventController::class, 'index'])
        ->middleware('can:'.Ability::EventsView->value)
        ->name('events.index');
    Route::get('events/print', [PrintController::class, 'events'])
        ->middleware('can:'.Ability::EventsView->value)
        ->name('print.events');

    // Collection visits: manual management from WhatsApp Web (no official API).
    Route::middleware('can:'.Ability::CollectionVisitsView->value)->group(function () {
        Route::get('collection-visits', [CollectionVisitController::class, 'index'])->name('collection-visits.index');
        Route::get('collection-visits/create', [CollectionVisitController::class, 'create'])->name('collection-visits.create');
    });
    Route::post('collection-visits', [CollectionVisitController::class, 'store'])
        ->middleware('can:'.Ability::CollectionVisitsManage->value)
        ->name('collection-visits.store');

    // Cash movements: income and expense movements, daily closing.
    Route::get('cash-movements', [CashMovementController::class, 'index'])
        ->middleware('can:'.Ability::CashMovementsView->value)
        ->name('cash-movements.index');
    Route::middleware('can:'.Ability::CashMovementsManage->value)->group(function () {
        Route::post('cash-movements', [CashMovementController::class, 'store'])->name('cash-movements.store');
        Route::post('cash-movements/close', [CashMovementController::class, 'close'])->name('cash-movements.close');
    });

    // Loan contracts in PDF.
    Route::middleware('can:'.Ability::ContractsView->value)->group(function () {
        Route::get('contracts', [ContractController::class, 'index'])->name('contracts.index');
        Route::get('contracts/{contract}', [ContractController::class, 'show'])->name('contracts.show');
        Route::get('contracts/{contract}/download', [ContractController::class, 'download'])->name('contracts.download');
    });
    Route::post('contracts/generate', [ContractController::class, 'generate'])
        ->middleware('can:'.Ability::ContractsManage->value)
        ->name('contracts.generate');

    // Client documents (DNI, pay stub, etc.).
    Route::middleware('can:'.Ability::ClientDocumentsView->value)->group(function () {
        Route::get('client-documents', [ClientDocumentController::class, 'index'])->name('client-documents.index');
        Route::get('client-documents/{document}/download', [ClientDocumentController::class, 'download'])->name('client-documents.download');
    });
    Route::middleware('can:'.Ability::ClientDocumentsManage->value)->group(function () {
        Route::post('client-documents', [ClientDocumentController::class, 'store'])->name('client-documents.store');
        Route::delete('client-documents/{document}', [ClientDocumentController::class, 'destroy'])->name('client-documents.destroy');
    });

    // Profitability report.
    Route::get('profitability', [ProfitabilityController::class, 'index'])
        ->middleware('can:'.Ability::ProfitabilityView->value)
        ->name('profitability.index');

    // Reportes de decisión: cartera, mora, caja y cobranzas por cobrador.
    Route::middleware('can:'.Ability::ReportsView->value)->group(function () {
        Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('reports/portfolio', [ReportController::class, 'cartera'])->name('reports.portfolio');
        Route::get('reports/delinquency', [ReportController::class, 'mora'])->name('reports.delinquency');
        Route::get('reports/daily-cash', [ReportController::class, 'cajaDiaria'])->name('reports.daily-cash');
        Route::get('reports/collections-collector', [ReportController::class, 'cobranzasCobrador'])->name('reports.collections-collector');
        Route::get('reports/executive', [ExecutiveDashboardController::class, 'index'])->name('reports.executive');
    });

    Route::get('settings', [SettingController::class, 'index'])
        ->middleware('can:'.Ability::SettingsManage->value)
        ->name('settings.index');
    Route::get('settings/logo', [SettingController::class, 'logo'])
        ->middleware('can:'.Ability::SettingsManage->value)
        ->name('settings.logo');
    Route::put('settings', [SettingController::class, 'update'])
        ->middleware('can:'.Ability::SettingsManage->value)
        ->name('settings.update');

    // Activación/desactivación de módulos. La validación de núcleo y
    // dependencias vive en ModuleManager, no en la vista.
    Route::middleware('can:'.Ability::ModulesManage->value)->group(function () {
        Route::get('modules', [ModuleController::class, 'index'])->name('modules.index');
        Route::put('modules', [ModuleController::class, 'update'])
            ->middleware('throttle:10,1')
            ->name('modules.update');
        Route::put('modules/roles', [ModuleController::class, 'updateRoleAccess'])
            ->middleware('throttle:10,1')
            ->name('modules.role-access.update');
    });

    Route::post('backup', [BackupController::class, 'run'])
        ->middleware('can:'.Ability::BackupManage->value)
        ->name('backup.run');
    Route::get('backup/{name}', [BackupController::class, 'download'])
        ->middleware('can:'.Ability::BackupManage->value)
        ->name('backup.download');

    Route::resource('users', UserController::class)
        ->only(['index', 'create', 'store', 'edit', 'update'])
        ->middleware('can:'.Ability::UsersManage->value);

    // Users are never deleted (the audit trail keeps them forever); an
    // administrator only deactivates/reactivates the account.
    Route::post('users/{user}/toggle-active', [UserController::class, 'toggleActive'])
        ->middleware('can:'.Ability::UsersManage->value)
        ->name('users.toggle-active');

    // Portal "Mi cuenta" del rol Cliente: sólo lee los datos del cliente
    // vinculado a la cuenta, nunca los de terceros.
    Route::middleware(['portal', 'can:'.Ability::ClientPortal->value])->group(function () {
        Route::get('my-account', [ClientPortalController::class, 'index'])
            ->name('portal.index');
        Route::get('my-account/loans', [ClientPortalController::class, 'loans'])
            ->name('portal.loans');
        Route::get('my-account/loans/{loan}', [ClientPortalController::class, 'showLoan'])
            ->name('portal.loans.show');
        Route::get('my-account/installments', [ClientPortalController::class, 'installments'])
            ->name('portal.installments');
        Route::get('my-account/payments', [ClientPortalController::class, 'payments'])
            ->name('portal.payments');
        Route::get('my-account/payments/{payment}/receipt', [ClientPortalController::class, 'receipt'])
            ->name('portal.payments.receipt');
        Route::get('my-account/statement', [ClientPortalController::class, 'statement'])
            ->name('portal.statement');
        Route::post('my-account/loans/{loan}/refinancing', [ClientPortalController::class, 'storeRefinanceRequest'])
            ->name('portal.loans.refinance-request');
        Route::put('my-account/contact', [ClientPortalController::class, 'updateContact'])
            ->name('portal.contact.update');
        Route::get('my-account/profile', [ClientPortalController::class, 'profile'])
            ->name('portal.profile');
        Route::put('my-account/profile', [ClientPortalController::class, 'updateProfile'])
            ->name('portal.profile.update');
    });
});
