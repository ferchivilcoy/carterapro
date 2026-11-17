<?php

namespace App\Actions\Loans;

use App\Enums\AmortizationModel;
use App\Enums\DisbursementMethod;
use App\Enums\EventType;
use App\Enums\InstallmentStatus;
use App\Enums\LoanStatus;
use App\Enums\PaymentFrequency;
use App\Enums\RateType;
use App\Models\Client;
use App\Models\Event;
use App\Models\Loan;
use App\Services\AmortizationService;
use App\Services\ClientScoreService;
use Illuminate\Support\Facades\DB;

class CreateLoanAction
{
    public function __construct(
        private readonly AmortizationService $amortization,
        private readonly ClientScoreService $scoreService,
    ) {}

    public function execute(Client $client, array $data): Loan
    {
        return DB::transaction(function () use ($client, $data) {
            $plan = $this->amortization->calculate(
                principal: (float) $data['principal_amount'],
                interestRate: (float) $data['interest_rate'],
                rateType: RateType::from($data['rate_type']),
                model: AmortizationModel::from($data['amortization_model']),
                installments: (int) $data['installments_count'],
                startDate: $data['start_date'],
                frequency: PaymentFrequency::from($data['frequency']),
            );

            $loan = Loan::create([
                'client_id' => $client->id,
                'refinanced_from_id' => $data['refinanced_from_id'] ?? null,
                'principal_amount' => $data['principal_amount'],
                'interest_rate' => $data['interest_rate'],
                'rate_type' => $data['rate_type'],
                'amortization_model' => $data['amortization_model'],
                'installments_count' => $data['installments_count'],
                'frequency' => $data['frequency'],
                'start_date' => $data['start_date'],
                'total_amount' => $plan['total_amount'],
                'pending_balance' => $plan['total_amount'],
                'status' => LoanStatus::Active,
                'disbursement_method' => $data['disbursement_method'] ?? DisbursementMethod::Cash->value,
                'disbursement_account' => $data['disbursement_account'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);

            foreach ($plan['installments'] as $index => $installment) {
                $loan->installments()->create([
                    'installment_number' => $index + 1,
                    'due_date' => $installment['due_date'],
                    'expected_amount' => $installment['amount'],
                    'paid_amount' => 0,
                    'pending_balance' => $installment['amount'],
                    'status' => InstallmentStatus::Pending,
                ]);
            }

            Event::create([
                'event_type' => EventType::InstallmentGenerated,
                'client_id' => $client->id,
                'loan_id' => $loan->id,
                'extra_data' => [
                    'installments' => $data['installments_count'],
                    'total_amount' => $plan['total_amount'],
                ],
                'created_at' => now(),
                'created_by' => auth()->id(),
            ]);

            Event::create([
                'event_type' => EventType::LoanCreated,
                'client_id' => $client->id,
                'loan_id' => $loan->id,
                'extra_data' => [
                    'principal' => $data['principal_amount'],
                    'installments' => $data['installments_count'],
                    'disbursement' => $data['disbursement_method'] ?? DisbursementMethod::Cash->value,
                    'disbursement_account' => $data['disbursement_account'] ?? null,
                ],
                'created_at' => now(),
                'created_by' => auth()->id(),
            ]);

            $this->scoreService->update($client);

            return $loan->load('installments');
        });
    }
}
