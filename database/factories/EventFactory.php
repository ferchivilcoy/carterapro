<?php

namespace Database\Factories;

use App\Enums\EventType;
use App\Models\Client;
use App\Models\Event;
use App\Models\Installment;
use App\Models\Loan;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    public function definition(): array
    {
        // The event model opts out of timestamps, so created_at is written here.
        // Every foreign key is nullable and independent, so the defaults stay
        // detached and the states below attach the row to the graph it belongs
        // to. That keeps a listing test from building a whole loan per event.
        return [
            'event_type' => fake()->randomElement(EventType::cases()),
            'client_id' => null,
            'loan_id' => null,
            'installment_id' => null,
            'payment_id' => null,
            'extra_data' => null,
            'created_at' => now()->subMinutes(fake()->numberBetween(0, 5_000)),
            'created_by' => null,
        ];
    }

    public function ofType(EventType $type): static
    {
        return $this->state(fn (array $attributes) => [
            'event_type' => $type,
        ]);
    }

    public function forClient(Client|int $client): static
    {
        return $this->state(fn (array $attributes) => [
            'client_id' => $client instanceof Client ? $client->id : $client,
        ]);
    }

    public function forLoan(Loan|int $loan): static
    {
        return $this->state(fn (array $attributes) => [
            'loan_id' => $loan instanceof Loan ? $loan->id : $loan,
        ]);
    }

    public function forInstallment(Installment|int $installment): static
    {
        return $this->state(fn (array $attributes) => [
            'installment_id' => $installment instanceof Installment ? $installment->id : $installment,
        ]);
    }

    public function forPayment(Payment|int $payment): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_id' => $payment instanceof Payment ? $payment->id : $payment,
        ]);
    }

    public function recordedBy(User|int $user): static
    {
        return $this->state(fn (array $attributes) => [
            'created_by' => $user instanceof User ? $user->id : $user,
        ]);
    }
}
