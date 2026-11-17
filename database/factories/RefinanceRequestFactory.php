<?php

namespace Database\Factories;

use App\Enums\RefinanceRequestStatus;
use App\Models\Client;
use App\Models\Loan;
use App\Models\RefinanceRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RefinanceRequest>
 */
class RefinanceRequestFactory extends Factory
{
    public function definition(): array
    {
        // Sólo el préstamo es independiente: la ficha del cliente se pasa con
        // ->for($client) para que ambos lados apunten al mismo expediente.
        return [
            'client_id' => Client::factory(),
            'loan_id' => Loan::factory(),
            'message' => fake()->sentence(),
            'status' => RefinanceRequestStatus::Pending,
            'resolved_at' => null,
            'created_by' => null,
            'resolved_by' => null,
        ];
    }

    public function attended(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => RefinanceRequestStatus::Attended,
            'resolved_at' => now(),
        ]);
    }
}
