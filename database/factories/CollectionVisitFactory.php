<?php

namespace Database\Factories;

use App\Enums\CollectionResult;
use App\Enums\CollectionType;
use App\Models\Client;
use App\Models\CollectionVisit;
use App\Models\Installment;
use App\Models\Loan;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<CollectionVisit>
 */
class CollectionVisitFactory extends Factory
{
    public function definition(): array
    {
        $client = Client::factory()->create();
        $loan = Loan::factory()->for($client)->create();
        $installment = Installment::factory()->for($loan)->create();
        $payment = Payment::factory()->for($installment)->for($loan)->create();

        $type = fake()->randomElement(CollectionType::cases());
        $result = fake()->randomElement(CollectionResult::cases());

        return [
            'client_id' => $client->id,
            'loan_id' => $loan->id,
            'installment_id' => $installment->id,
            'payment_id' => $payment->id,
            'type' => $type,
            'result' => $result,
            'visited_at' => Carbon::today(),
            'visited_at_time' => fake()->time('H:i:s'),
            'commitment_date' => fake()->optional()->boolean() ? Carbon::today()->addDays(fake()->numberBetween(1, 30)) : null,
            'commitment_amount' => fake()->optional()->randomFloat(2, 1000, 20_000),
            'notes' => fake()->optional()->sentence(),
            'managed_by' => User::factory(),
        ];
    }

    public function promisedToPay(): static
    {
        return $this->state(fn (array $attributes) => [
            'result' => CollectionResult::PromisedToPay,
        ]);
    }

    public function noAnswer(): static
    {
        return $this->state(fn (array $attributes) => [
            'result' => CollectionResult::NoAnswer,
        ]);
    }

    public function paymentReported(): static
    {
        return $this->state(fn (array $attributes) => [
            'result' => CollectionResult::PaymentReported,
        ]);
    }

    public function withoutManagedBy(): static
    {
        return $this->state(fn (array $attributes) => [
            'managed_by' => null,
        ]);
    }
}
