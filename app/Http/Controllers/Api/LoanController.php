<?php

namespace App\Http\Controllers\Api;

use App\Actions\Loans\CancelLoanAction;
use App\Actions\Loans\CreateLoanAction;
use App\Actions\Loans\PrepaymentAction;
use App\Actions\Loans\RefinanceLoanAction;
use App\Actions\Loans\UpdateLoanAction;
use App\Enums\AmortizationModel;
use App\Enums\PaymentFrequency;
use App\Enums\RateType;
use App\Http\Requests\Loans\CancelLoanRequest;
use App\Http\Requests\Loans\PrepaymentRequest;
use App\Http\Requests\Loans\PreviewLoanRequest;
use App\Http\Requests\Loans\RefinanceLoanRequest;
use App\Http\Requests\Loans\StoreLoanRequest;
use App\Http\Requests\Loans\UpdateLoanRequest;
use App\Models\Client;
use App\Models\Loan;
use App\Services\AmortizationService;
use Illuminate\Http\Request;
use InvalidArgumentException;

class LoanController extends Controller
{
    public function index(Request $request)
    {
        $query = Loan::query()->with('client');

        if ($search = $request->string('search')) {
            $query->whereHas('client', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%");
            });
        }

        if ($status = $request->string('status')) {
            $query->where('status', $status);
        }

        $loans = $query->latest()->paginate(15)->withQueryString();

        return $this->success($loans, 'Préstamos listados');
    }

    public function show(Loan $loan)
    {
        $loan->load(['client', 'installments', 'payments.installment']);

        return $this->success($loan, 'Préstamo encontrado');
    }

    public function store(StoreLoanRequest $request, CreateLoanAction $create)
    {
        $loan = $create->execute(
            Client::findOrFail($request->integer('client_id')),
            $request->validated(),
        );

        return $this->success($loan, 'Préstamo creado', 201);
    }

    public function update(UpdateLoanRequest $request, Loan $loan, UpdateLoanAction $update)
    {
        try {
            $loan = $update->execute($loan, $request->validated());
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success($loan, 'Préstamo actualizado');
    }

    public function destroy(Loan $loan, CancelLoanAction $cancel, CancelLoanRequest $request)
    {
        try {
            $cancel->execute($loan, $request->validated('cancellation_reason'));
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success(null, 'Préstamo cancelado');
    }

    public function preview(PreviewLoanRequest $request, AmortizationService $amortization)
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
            return $this->error($e->getMessage(), 422);
        }

        $amounts = array_column($plan['installments'], 'amount');

        return $this->success([
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
            'first_amount' => $amounts[0] ?? null,
            'last_amount' => $amounts[count($amounts) - 1] ?? null,
            'max_amount' => max($amounts),
        ], 'Plan de cuotas calculado');
    }

    public function prepayment(PrepaymentRequest $request, Loan $loan, PrepaymentAction $prepayment)
    {
        try {
            $payment = $prepayment->execute($loan, $request->validated());
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success($payment->load('installment'), 'Prepago registrado', 201);
    }

    public function refinance(RefinanceLoanRequest $request, Loan $loan, RefinanceLoanAction $refinance)
    {
        try {
            $newLoan = $refinance->execute($loan, $request->validated());
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success($newLoan, 'Préstamo refinanciado', 201);
    }
}
