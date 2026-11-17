<?php

use App\Enums\Ability;
use App\Http\Controllers\Api\ClientController as ApiClientController;
use App\Http\Controllers\Api\InstallmentController as ApiInstallmentController;
use App\Http\Controllers\Api\LoanController as ApiLoanController;
use App\Http\Controllers\Api\PaymentController as ApiPaymentController;
use App\Http\Middleware\EnsureUserIsActive;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes (Sanctum)
|--------------------------------------------------------------------------
|
| Estas rutas se autentican con tokens de Laravel Sanctum (auth:sanctum) y
| reutilizan las mismas Actions y FormRequests que el panel Blade, de modo
| que la lógica de negocio (cascada de cobro, amortización, score, etc.)
| se define en un solo lugar.
|
| La matriz de abilities replica la del panel: "view" para leer y "manage"
| para toda mutación. Sin esa capa, un token de un rol con menos permisos
| abriría toda la API.
|
*/

Route::middleware(['auth:sanctum', EnsureUserIsActive::class])->name('api.')->group(function () {

    // Clientes
    Route::get('/clients', [ApiClientController::class, 'index'])
        ->middleware('can:'.Ability::ClientsView->value)
        ->name('clients.index');
    Route::get('/clients/{client}', [ApiClientController::class, 'show'])
        ->middleware('can:'.Ability::ClientsView->value)
        ->name('clients.show');
    Route::middleware('can:'.Ability::ClientsManage->value)->group(function () {
        Route::post('/clients', [ApiClientController::class, 'store'])->name('clients.store');
        Route::put('/clients/{client}', [ApiClientController::class, 'update'])->name('clients.update');
        Route::delete('/clients/{client}', [ApiClientController::class, 'destroy'])->name('clients.destroy');
    });

    // Préstamos
    Route::get('/loans', [ApiLoanController::class, 'index'])
        ->middleware('can:'.Ability::LoansView->value)
        ->name('loans.index');
    Route::get('/loans/{loan}', [ApiLoanController::class, 'show'])
        ->middleware('can:'.Ability::LoansView->value)
        ->name('loans.show');
    Route::middleware('can:'.Ability::LoansManage->value)->group(function () {
        Route::post('/loans', [ApiLoanController::class, 'store'])->name('loans.store');
        Route::put('/loans/{loan}', [ApiLoanController::class, 'update'])->name('loans.update');
        Route::delete('/loans/{loan}', [ApiLoanController::class, 'destroy'])->name('loans.destroy');
        Route::post('/loans/preview', [ApiLoanController::class, 'preview'])->name('loans.preview');
        Route::post('/loans/{loan}/prepayment', [ApiLoanController::class, 'prepayment'])->name('loans.prepayment');
        Route::post('/loans/{loan}/refinance', [ApiLoanController::class, 'refinance'])->name('loans.refinance');
    });

    // Cuotas
    Route::middleware('can:'.Ability::InstallmentsView->value)->group(function () {
        Route::get('/installments', [ApiInstallmentController::class, 'index'])->name('installments.index');
        // "due-today" va antes que {installment} para que no sea capturado
        // como clave de modelo.
        Route::get('/installments/due-today', [ApiInstallmentController::class, 'dueToday'])->name('installments.due-today');
        Route::get('/installments/{installment}', [ApiInstallmentController::class, 'show'])->name('installments.show');
    });

    // Pagos
    Route::middleware('can:'.Ability::PaymentsView->value)->group(function () {
        Route::get('/payments', [ApiPaymentController::class, 'index'])->name('payments.index');
        Route::get('/payments/{payment}', [ApiPaymentController::class, 'show'])->name('payments.show');
    });
    Route::middleware('can:'.Ability::PaymentsManage->value)->group(function () {
        Route::post('/payments', [ApiPaymentController::class, 'store'])->name('payments.store');
        Route::delete('/payments/{payment}', [ApiPaymentController::class, 'cancel'])->name('payments.cancel');
    });

});
