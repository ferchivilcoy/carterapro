<?php

namespace Database\Factories;

use App\Enums\ClientStatus;
use App\Enums\ClientTier;
use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Client>
 */
class ClientFactory extends Factory
{
    /**
     * The CBU/CVU column holds exactly 22 digits, so the fake number is padded
     * and truncated instead of relying on a plain random number.
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'phone' => fake()->numerify('11##########'),
            'email' => fake()->unique()->safeEmail(),
            'document' => fake()->numerify('###########'),
            'address' => fake()->streetAddress().', '.fake()->city(),
            'alias' => fake()->unique()->userName(),
            'cbu_cvu' => fake()->numerify('######################'),
            'bank_wallet' => fake()->randomElement(['Banco Nación', 'Galicia', 'Santander', 'BBVA', 'Mercado Pago']),
            'account_holder' => null,
            'is_own_account' => true,
            'notes' => null,
            'status' => ClientStatus::Active,
            'current_score' => fake()->numberBetween(0, 100),
            'tier' => ClientTier::Initial,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ClientStatus::Inactive,
        ]);
    }

    public function tier(ClientTier $tier): static
    {
        return $this->state(fn (array $attributes) => [
            'tier' => $tier,
        ]);
    }

    public function withoutAccount(): static
    {
        return $this->state(fn (array $attributes) => [
            'alias' => null,
            'cbu_cvu' => null,
            'bank_wallet' => null,
            'account_holder' => null,
        ]);
    }
}
