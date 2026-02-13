<?php

namespace Tests\Feature;

use App\Livewire\CategoryDropdown;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CategoryDropdownTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test categories
        Category::create(['name' => 'Groceries', 'icon' => '🛒', 'type' => 'expense', 'is_default' => true]);
        Category::create(['name' => 'Salary', 'icon' => '💰', 'type' => 'income', 'is_default' => true]);
        Category::create(['name' => 'Transport', 'icon' => '🚗', 'type' => 'expense', 'is_default' => true]);
        Category::create(['name' => 'Freelance', 'icon' => '💼', 'type' => 'income', 'is_default' => true]);
    }

    /** @test */
    public function it_shows_only_expense_categories_when_transaction_type_is_expense()
    {
        Livewire::test(CategoryDropdown::class, ['transactionType' => 'expense'])
            ->assertSee('Groceries')
            ->assertSee('Transport')
            ->assertDontSee('Salary')
            ->assertDontSee('Freelance');
    }

    /** @test */
    public function it_shows_only_income_categories_when_transaction_type_is_income()
    {
        Livewire::test(CategoryDropdown::class, ['transactionType' => 'income'])
            ->assertSee('Salary')
            ->assertSee('Freelance')
            ->assertDontSee('Groceries')
            ->assertDontSee('Transport');
    }

    /** @test */
    public function it_resets_selected_category_when_transaction_type_changes()
    {
        Livewire::test(CategoryDropdown::class, ['transactionType' => 'expense'])
            ->set('selectedCategory', '1')
            ->assertSet('selectedCategory', '1')
            ->set('transactionType', 'income')
            ->assertSet('selectedCategory', '');
    }

    /** @test */
    public function it_emits_category_selected_event_when_category_is_chosen()
    {
        Livewire::test(CategoryDropdown::class, ['transactionType' => 'expense'])
            ->set('selectedCategory', '1')
            ->assertDispatched('categorySelected', '1');
    }

    /** @test */
    public function it_shows_no_categories_message_when_no_categories_exist_for_type()
    {
        // Remove all categories for testing
        Category::query()->delete();

        Livewire::test(CategoryDropdown::class, ['transactionType' => 'expense'])
            ->assertSee('No categories available for Expense');
    }

    /** @test */
    public function it_shows_icons_with_category_names()
    {
        Livewire::test(CategoryDropdown::class, ['transactionType' => 'expense'])
            ->assertSee('🛒 Groceries')
            ->assertSee('🚗 Transport');
    }
}