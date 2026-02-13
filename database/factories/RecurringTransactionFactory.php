<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\RecurringTransaction>
 */
class RecurringTransactionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'amount' => $this->faker->randomFloat(2, 10, 1000),
            'description' => $this->faker->sentence(3),
            'frequency' => $this->faker->randomElement(['daily', 'weekly', 'monthly', 'yearly']),
            'next_due_date' => $this->faker->dateTimeBetween('now', '+1 year'),
            'is_active' => $this->faker->boolean(80),
        ];
    }
}
