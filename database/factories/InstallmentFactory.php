<?php

namespace Database\Factories;

use App\Enums\InstallmentStatus;
use App\Models\Installment;
use App\Models\Loan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Installment>
 */
class InstallmentFactory extends Factory
{
    public function definition(): array
    {
        $amount = fake()->randomFloat(2, 1000, 20_000);

        return [
            'loan_id' => Loan::factory(),
            // The (loan_id, installment_number) pair is unique, so the number is
            // drawn from a per test unique pool. Pass an explicit number when a
            // test needs a known ordering.
            'installment_number' => fake()->unique()->numberBetween(1, 10_000),
            'due_date' => today()->addDays(30),
            'expected_amount' => $amount,
            'paid_amount' => 0,
            'pending_balance' => $amount,
            'status' => InstallmentStatus::Pending,
        ];
    }

    public function overdue(int $days = 5): static
    {
        return $this->state(fn (array $attributes) => [
            'due_date' => today()->subDays($days),
            'status' => InstallmentStatus::Overdue,
        ]);
    }

    public function dueToday(): static
    {
        return $this->state(fn (array $attributes) => [
            'due_date' => today(),
            'status' => InstallmentStatus::Pending,
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'paid_amount' => $attributes['expected_amount'],
            'pending_balance' => 0,
            'status' => InstallmentStatus::Paid,
        ]);
    }
}
