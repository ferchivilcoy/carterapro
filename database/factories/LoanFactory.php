<?php

namespace Database\Factories;

use App\Enums\AmortizationModel;
use App\Enums\LoanStatus;
use App\Enums\PaymentFrequency;
use App\Enums\RateType;
use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Loan>
 */
class LoanFactory extends Factory
{
    public function definition(): array
    {
        $principal = fake()->numberBetween(10_000, 100_000);

        return [
            'client_id' => Client::factory(),
            'principal_amount' => $principal,
            'interest_rate' => 10,
            'rate_type' => RateType::Total,
            'amortization_model' => AmortizationModel::FixedInterest,
            'installments_count' => 6,
            'frequency' => PaymentFrequency::Monthly,
            'start_date' => today(),
            'total_amount' => $principal * 1.6,
            'pending_balance' => $principal * 1.6,
            'status' => LoanStatus::Active,
            'disbursement_method' => null,
            'disbursement_account' => null,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => LoanStatus::Paid,
            'pending_balance' => 0,
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => LoanStatus::Cancelled,
            'pending_balance' => 0,
        ]);
    }
}
