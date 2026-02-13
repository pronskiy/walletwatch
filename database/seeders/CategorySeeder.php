<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            ['name' => 'Food', 'icon' => '🍕', 'color' => '#FF6B6B', 'type' => 'expense'],
            ['name' => 'Transport', 'icon' => '🚗', 'color' => '#4ECDC4', 'type' => 'expense'],
            ['name' => 'Salary', 'icon' => '💼', 'color' => '#45B7D1', 'type' => 'income'],
            ['name' => 'Rent', 'icon' => '🏠', 'color' => '#F7B731', 'type' => 'expense'],
            ['name' => 'Entertainment', 'icon' => '🎬', 'color' => '#A55EEA', 'type' => 'expense'],
            ['name' => 'Shopping', 'icon' => '🛍️', 'color' => '#FD79A8', 'type' => 'expense'],
            ['name' => 'Health', 'icon' => '🏥', 'color' => '#00B894', 'type' => 'expense'],
            ['name' => 'Education', 'icon' => '📚', 'color' => '#0984E3', 'type' => 'expense'],
            ['name' => 'Travel', 'icon' => '✈️', 'color' => '#6C5CE7', 'type' => 'expense'],
            ['name' => 'Utilities', 'icon' => '⚡', 'color' => '#FDCB6E', 'type' => 'expense'],
            ['name' => 'Insurance', 'icon' => '🛡️', 'color' => '#E17055', 'type' => 'expense'],
            ['name' => 'Gifts', 'icon' => '🎁', 'color' => '#FF7675', 'type' => 'expense'],
            ['name' => 'Investments', 'icon' => '📈', 'color' => '#00CEC9', 'type' => 'income'],
            ['name' => 'Freelance', 'icon' => '💻', 'color' => '#74B9FF', 'type' => 'income'],
            ['name' => 'Other', 'icon' => '🔄', 'color' => '#636E72', 'type' => 'expense'],
        ];

        foreach ($categories as $category) {
            Category::create(array_merge($category, ['is_default' => true]));
        }
    }
}
