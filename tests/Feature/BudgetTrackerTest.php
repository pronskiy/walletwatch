<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BudgetTrackerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2024-02-15'); // Set a fixed test date
    }

    public function test_budget_tracker_displays_budget_data_correctly()
    {
        // Create a user and authenticate
        $user = User::factory()->create();
        $this->actingAs($user);

        // Create categories
        $groceries = Category::factory()->create(['name' => 'Groceries', 'icon' => '🛒']);
        $entertainment = Category::factory()->create(['name' => 'Entertainment', 'icon' => '🎬']);

        // Create budgets
        Budget::factory()->create([
            'user_id' => $user->id,
            'category_id' => $groceries->id,
            'amount' => 500.00,
            'period' => 'monthly',
            'start_date' => Carbon::now()->startOfMonth(),
        ]);

        Budget::factory()->create([
            'user_id' => $user->id,
            'category_id' => $entertainment->id,
            'amount' => 200.00,
            'period' => 'monthly',
            'start_date' => Carbon::now()->startOfMonth(),
        ]);

        // Create an account
        $account = Account::factory()->create(['user_id' => $user->id]);

        // Create transactions for current month
        Transaction::factory()->create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'category_id' => $groceries->id,
            'amount' => -150.00, // Expense
            'type' => 'expense',
            'date' => Carbon::now(),
        ]);

        Transaction::factory()->create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'category_id' => $entertainment->id,
            'amount' => -80.00, // Expense
            'type' => 'expense',
            'date' => Carbon::now(),
        ]);

        // Test the component
        $component = Livewire::test('budget-tracker');

        // Check that budgets are displayed
        $component->assertSee('Groceries')
                  ->assertSee('Entertainment')
                  ->assertSee('$150.00 / $500.00') // Groceries spending
                  ->assertSee('$80.00 / $200.00')  // Entertainment spending
                  ->assertSee('Remaining: $350.00') // Groceries remaining
                  ->assertSee('Remaining: $120.00'); // Entertainment remaining
    }

    public function test_budget_tracker_shows_over_budget_warning()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $category = Category::factory()->create(['name' => 'Shopping', 'icon' => '🛍️']);
        
        Budget::factory()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'amount' => 100.00,
            'period' => 'monthly',
            'start_date' => Carbon::now()->startOfMonth(),
        ]);

        $account = Account::factory()->create(['user_id' => $user->id]);

        // Create expense that goes over budget
        Transaction::factory()->create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'amount' => -150.00, // More than budget
            'type' => 'expense',
            'date' => Carbon::now(),
        ]);

        $component = Livewire::test('budget-tracker');

        $component->assertSee('Over Budget')
                  ->assertSee('$150.00 / $100.00')
                  ->assertSee('Over by: $50.00');
    }

    public function test_budget_tracker_only_counts_current_month_transactions()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $category = Category::factory()->create(['name' => 'Food', 'icon' => '🍔']);
        
        Budget::factory()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'amount' => 300.00,
            'period' => 'monthly',
            'start_date' => Carbon::now()->startOfMonth(),
        ]);

        $account = Account::factory()->create(['user_id' => $user->id]);

        // Current month transaction
        Transaction::factory()->create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'amount' => -100.00,
            'type' => 'expense',
            'date' => Carbon::now(),
        ]);

        // Last month transaction (should be ignored)
        Transaction::factory()->create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'amount' => -200.00,
            'type' => 'expense',
            'date' => Carbon::now()->subMonth(),
        ]);

        $component = Livewire::test('budget-tracker');

        // Should only show current month spending
        $component->assertSee('$100.00 / $300.00')
                  ->assertSee('Remaining: $200.00');
    }

    public function test_budget_tracker_shows_empty_state_when_no_budgets()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $component = Livewire::test('budget-tracker');

        $component->assertSee('No budgets set')
                  ->assertSee('Create monthly budgets');
    }
}