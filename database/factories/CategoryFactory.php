<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Category>
 */
class CategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->randomElement(['Food', 'Transport', 'Entertainment', 'Shopping', 'Health']),
            'icon' => $this->faker->optional()->randomElement(['🍕', '🚗', '🎬', '🛍️', '🏥']),
            'color' => $this->faker->hexColor(),
            'type' => $this->faker->randomElement(['income', 'expense']),
            'is_default' => false,
        ];
    }
}
