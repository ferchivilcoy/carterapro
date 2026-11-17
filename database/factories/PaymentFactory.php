<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Installment;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    public function definition(): array
    {
        // A payment denormalizes the loan and the client onto its own row, so
        // the installment is created up front and the three ids are filled from
        // the same graph. A factory attribute could not be reused, because
        // creating the installment twice would produce two different loans.
        $installment = Installment::factory()->create();
        $loan = $installment->loan;

        return [
            'installment_id' => $installment->id,
            'loan_id' => $loan->id,
            'client_id' => $loan->client_id,
            // receipt_number carries a unique index, so it is drawn from a
            // per test unique pool.
            'receipt_number' => 'REC-'.fake()->unique()->numerify('########'),
            'amount' => fake()->randomFloat(2, 1_000, 20_000),
            'payment_date' => today()->subDays(fake()->numberBetween(0, 20)),
            'method' => fake()->randomElement(PaymentMethod::cases()),
            'notes' => null,
            'status' => PaymentStatus::Active,
            'cancellation_reason' => null,
        ];
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PaymentStatus::Cancelled,
            'cancellation_reason' => 'Anulado desde la prueba.',
        ]);
    }

    public function paidOn(string $date): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_date' => $date,
        ]);
    }
}
