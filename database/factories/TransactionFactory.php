<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Transaction>
 */
class TransactionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = $this->faker->randomElement(['income', 'expense', 'transfer']);
        $amount = match ($type) {
            'income' => $this->faker->randomFloat(2, 100, 5000),
            'expense' => -$this->faker->randomFloat(2, 5, 500),
            'transfer' => $this->faker->randomFloat(2, 50, 1000),
        };

        return [
            'amount' => $amount,
            'description' => $this->faker->sentence(3),
            'date' => $this->faker->dateTimeBetween('-6 months', 'now'),
            'type' => $type,
            'notes' => $this->faker->optional()->sentence(),
        ];
    }
}
