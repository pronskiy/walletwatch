<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Budget>
 */
class BudgetFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'amount' => $this->faker->randomFloat(2, 100, 2000),
            'period' => $this->faker->randomElement(['monthly', 'weekly']),
            'start_date' => $this->faker->dateTimeBetween('-1 month', '+1 month'),
        ];
    }
}
