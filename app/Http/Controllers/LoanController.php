<?php

namespace App\Http\Controllers;

use App\Actions\Loans\CancelLoanAction;
use App\Actions\Loans\CreateLoanAction;
use App\Actions\Loans\PrepaymentAction;
use App\Actions\Loans\RefinanceLoanAction;
use App\Actions\Loans\UpdateLoanAction;
use App\Enums\AmortizationModel;
use App\Enums\DisbursementMethod;
use App\Enums\PaymentFrequency;
use App\Enums\PaymentMethod;
use App\Enums\RateType;
use App\Enums\RefinanceRequestStatus;
use App\Http\Requests\Loans\CancelLoanRequest;
use App\Http\Requests\Loans\PrepaymentRequest;
use App\Http\Requests\Loans\PreviewLoanRequest;
use App\Http\Requests\Loans\RefinanceLoanRequest;
use App\Http\Requests\Loans\StoreLoanRequest;
use App\Http\Requests\Loans\UpdateLoanRequest;
use App\Models\Client;
use App\Models\Loan;
use App\Models\RefinanceRequest;
use App\Models\Setting;
use App\Services\AmortizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use InvalidArgumentException;
use RealRashid\SweetAlert\Facades\Alert;
use RuntimeException;

class LoanController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->string('search');
        $status = $request->string('status', 'all');

        $loans = Loan::query()
            ->with('client')
            ->withCount([
                'installments',
                'installments as paid_installments_count' => fn ($query) => $query->where('status', 'paid'),
            ])
            ->filtered($search->toString(), $status->toString())
            ->latest('created_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        // Both filters are normalized to plain strings, because the views
        // compare them against '' and 'all' to decide what to reset and which
        // empty state to show.
        return view('loans.index', [
            'loans' => $loans,
            'search' => $search->toString(),
            'status' => $status->toString(),
        ]);
    }

    public function create(Request $request): View
    {
        $clients = Client::query()->orderBy('name')->get();

        // Every prefill comes from the settings table, falling back to
        // Setting::DEFAULTS, so an unset setting cannot show one value on the
        // panel and another one on this form.
        $defaults = [
            'min_amount' => Setting::getDecimal('min_amount', Setting::DEFAULTS['min_amount']),
            'max_initial_amount' => Setting::getDecimal('max_initial_amount', Setting::DEFAULTS['max_initial_amount']),
            'interest_rate' => Setting::getDecimal('default_interest_rate', Setting::DEFAULTS['default_interest_rate']),
            'rate_type' => Setting::get('default_rate_type', Setting::DEFAULTS['default_rate_type']),
            'amortization_model' => Setting::get('default_amortization_model', Setting::DEFAULTS['default_amortization_model']),
            'installments_count' => Setting::getInt('default_installments_count', Setting::DEFAULTS['default_installments_count']),
            'frequency' => Setting::get('default_frequency', Setting::DEFAULTS['default_frequency']),
            'currency' => Setting::get('currency', Setting::DEFAULTS['currency']),
        ];

        return view('loans.create', [
            'clients' => $clients,
            // Cuenta de cada cliente para el select de cuenta destino, en el
            // mismo formato en que la validación la vuelve a comprobar.
            'clientAccounts' => $clients
                ->mapWithKeys(fn (Client $client) => $client->accountSummary() === null ? [] : [
                    $client->id => [
                        'label' => $client->accountSummary(),
                        'value' => $client->cbu_cvu ?: $client->alias ?: Str::limit($client->accountSummary(), 255),
                    ],
                ])
                ->all(),
            'disbursementMethods' => DisbursementMethod::cases(),
            'rateTypes' => RateType::cases(),
            'frequencies' => PaymentFrequency::cases(),
            // Only the models the AmortizationService can calculate, so the form
            // cannot submit a plan that throws.
            'amortizationModels' => AmortizationModel::implemented(),
            'clientId' => $request->integer('client_id'),
            'startDate' => today()->toDateString(),
            'defaults' => $defaults,
            'limits' => [
                'min' => $defaults['min_amount'],
                'max' => $defaults['max_initial_amount'],
            ],
        ]);
    }

    /**
     * Calcula el plan sin persistir nada, para que la vista previa del
     * formulario muestre exactamente lo que el alta terminaría creando.
     */
    public function preview(PreviewLoanRequest $request, AmortizationService $amortization): JsonResponse
    {
        $data = $request->validated();

        try {
            $plan = $amortization->calculate(
                principal: (float) $data['principal_amount'],
                interestRate: (float) $data['interest_rate'],
                rateType: RateType::from($data['rate_type']),
                model: AmortizationModel::from($data['amortization_model']),
                installments: (int) $data['installments_count'],
                startDate: $data['start_date'],
                frequency: PaymentFrequency::from($data['frequency']),
            );
        } catch (InvalidArgumentException $e) {
            // Red de seguridad: la validación ya rechaza las combinaciones
            // que el servicio no sabe calcular.
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $amounts = array_column($plan['installments'], 'amount');

        return response()->json([
            'installments' => collect($plan['installments'])
                ->map(fn (array $installment, int $index) => [
                    'n' => $index + 1,
                    'due_date' => $installment['due_date'],
                    'amount' => $installment['amount'],
                ])
                ->values()
                ->all(),
            'total_amount' => $plan['total_amount'],
            'interest_amount' => round($plan['total_amount'] - (float) $data['principal_amount'], 2),
            'first_amount' => $amounts[0],
            'last_amount' => $amounts[count($amounts) - 1],
            'max_amount' => max($amounts),
        ]);
    }

    public function store(StoreLoanRequest $request, CreateLoanAction $create): RedirectResponse
    {
        $loan = $create->execute(
            Client::findOrFail($request->integer('client_id')),
            $request->validated(),
        );

        return redirect()
            ->route('loans.show', $loan)
            ->with('loan_success', [
                'type' => 'created',
                'id' => $loan->id,
                'installments' => $loan->installments_count,
                'total' => (float) $loan->total_amount,
            ]);
    }

    public function show(Loan $loan): View
    {
        $loan->load([
            'client',
            'installments',
            'payments.installment',
            'events',
            'refinanceRequests' => fn ($query) => $query->with(['client', 'requester'])
                ->latest('created_at')
                ->orderByDesc('id'),
        ]);

        return view('loans.show', [
            'loan' => $loan,
            'payable' => $loan->nextPendingInstallment(),
            'paidCount' => $loan->paidInstallmentsCount(),
            'remainingCount' => $loan->remainingInstallmentsCount(),
            'refinanceBalance' => (float) $loan->pending_balance,
            'clientAccount' => $loan->client->accountSummary() === null
                ? null
                : [
                    'label' => $loan->client->accountSummary(),
                    'value' => $loan->client->cbu_cvu ?: $loan->client->alias ?: Str::limit($loan->client->accountSummary(), 255),
                ],
            'disbursementMethods' => DisbursementMethod::cases(),
            'rateTypes' => RateType::cases(),
            'frequencies' => PaymentFrequency::cases(),
            'amortizationModels' => AmortizationModel::implemented(),
            'paymentMethods' => PaymentMethod::cases(),
        ]);
    }

    public function prepayment(PrepaymentRequest $request, Loan $loan, PrepaymentAction $prepayment): RedirectResponse
    {
        try {
            $payment = $prepayment->execute($loan, $request->validated());
        } catch (RuntimeException $e) {
            return back()->withInput()->with('loan_error', $e->getMessage());
        }

        return redirect()
            ->route('loans.show', $loan)
            ->with('loan_success', [
                'type' => 'prepaid',
                'id' => $loan->id,
                'amount' => (float) $payment->amount,
            ]);
    }

    public function refinance(RefinanceLoanRequest $request, Loan $loan, RefinanceLoanAction $refinance): RedirectResponse
    {
        try {
            $newLoan = $refinance->execute($loan, $request->validated());
        } catch (RuntimeException $e) {
            return back()->withInput()->with('loan_error', $e->getMessage());
        }

        return redirect()
            ->route('loans.show', $newLoan)
            ->with('loan_success', [
                'type' => 'refinanced',
                'id' => $loan->id,
                'new_id' => $newLoan->id,
            ]);
    }

    /**
     * Da por atendida una solicitud de refinanciación que el cliente levantó
     * desde su portal. Sólo cambia el estado de la tarea: la refinanciación en
     * sí se hace desde el modal del préstamo, nunca desde este endpoint.
     */
    public function attendRefinanceRequest(
        Request $request,
        Loan $loan,
        RefinanceRequest $refinanceRequest,
    ): RedirectResponse {
        $pending = $loan->refinanceRequests()
            ->whereKey($refinanceRequest)
            ->firstOrFail();

        if (! $pending->isPending()) {
            Alert::error('La solicitud ya estaba atendida', 'Alguien del equipo la marcó como atendida antes.');

            return redirect()->route('loans.show', $loan);
        }

        $pending->update([
            'status' => RefinanceRequestStatus::Attended,
            'resolved_at' => now(),
            'resolved_by' => $request->user()?->id,
        ]);

        Alert::success(
            'Solicitud atendida',
            'Se marcó la solicitud de refinanciación del préstamo #'.$loan->id.' como atendida.',
        );

        return redirect()->route('loans.show', $loan);
    }

    public function edit(Loan $loan): View
    {
        $loan->load('client');

        $hasPayments = $loan->payments()->exists();

        return view('loans.edit', [
            'loan' => $loan,
            'hasPayments' => $hasPayments,
            // El cliente del préstamo no cambia: la cuenta de destino se
            // ofrece fija, con el mismo formato que valida el request.
            'clientAccount' => $loan->client->accountSummary() === null
                ? null
                : [
                    'label' => $loan->client->accountSummary(),
                    'value' => $loan->client->cbu_cvu ?: $loan->client->alias ?: Str::limit($loan->client->accountSummary(), 255),
                ],
            'disbursementMethods' => DisbursementMethod::cases(),
            'rateTypes' => RateType::cases(),
            'frequencies' => PaymentFrequency::cases(),
            'amortizationModels' => AmortizationModel::implemented(),
            'limits' => [
                'min' => Setting::getDecimal('min_amount', Setting::DEFAULTS['min_amount']),
                'max' => Setting::getDecimal('max_initial_amount', Setting::DEFAULTS['max_initial_amount']),
            ],
        ]);
    }

    public function update(UpdateLoanRequest $request, Loan $loan, UpdateLoanAction $update): RedirectResponse
    {
        try {
            $loan = $update->execute($loan, $request->validated());
        } catch (RuntimeException $e) {
            return back()->withInput()->with('loan_error', $e->getMessage());
        }

        return redirect()
            ->route('loans.show', $loan)
            ->with('loan_success', [
                'type' => 'updated',
                'id' => $loan->id,
            ]);
    }

    public function cancel(Loan $loan, CancelLoanRequest $request, CancelLoanAction $cancel): RedirectResponse
    {
        try {
            $cancel->execute($loan, $request->validated('cancellation_reason'));
        } catch (RuntimeException $e) {
            return back()->with('loan_error', $e->getMessage());
        }

        return redirect()
            ->route('loans.index')
            ->with('loan_success', ['type' => 'cancelled', 'id' => $loan->id]);
    }
}
