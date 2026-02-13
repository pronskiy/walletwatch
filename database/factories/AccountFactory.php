<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Account>
 */
class AccountFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->randomElement(['Main Checking', 'Savings', 'Emergency Fund', 'Travel Fund']),
            'type' => $this->faker->randomElement(['checking', 'savings', 'credit']),
            'currency' => 'USD',
            'initial_balance' => $this->faker->randomFloat(2, 0, 10000),
        ];
    }
}
