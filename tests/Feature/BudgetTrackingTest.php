<?php

namespace Tests\Feature;

use App\Livewire\BudgetTracker;
use App\Models\Account;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BudgetTrackingTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Account $account;
    private Category $expenseCategory;
    private Category $incomeCategory;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->account = Account::factory()->create(['user_id' => $this->user->id]);
        
        $this->expenseCategory = Category::create([
            'name' => 'Groceries',
            'icon' => '🛒',
            'type' => 'expense',
            'is_default' => true
        ]);
        
        $this->incomeCategory = Category::create([
            'name' => 'Salary',
            'icon' => '💰',
            'type' => 'income',
            'is_default' => true
        ]);
    }

    /** @test */
    public function it_calculates_budget_vs_spending_correctly()
    {
        // Create a budget for groceries: $500/month
        $budget = Budget::create([
            'user_id' => $this->user->id,
            'category_id' => $this->expenseCategory->id,
            'amount' => 500.00,
            'period' => 'monthly',
            'start_date' => now()->startOfMonth(),
        ]);

        // Create transactions for this month: $200 spent on groceries
        Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->expenseCategory->id,
            'amount' => -100.00, // expense
            'description' => 'Grocery store 1',
            'type' => 'expense',
            'date' => now(),
        ]);

        Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->expenseCategory->id,
            'amount' => -100.00, // expense
            'description' => 'Grocery store 2',
            'type' => 'expense',
            'date' => now(),
        ]);

        $component = Livewire::actingAs($this->user)
            ->test(BudgetTracker::class, [
                'month' => now()->month,
                'year' => now()->year,
            ]);

        $budgetData = $component->instance()->getBudgetData();
        
        $this->assertCount(1, $budgetData);
        $this->assertEquals(500.00, $budgetData[0]['budget_amount']);
        $this->assertEquals(200.00, $budgetData[0]['spent']);
        $this->assertEquals(300.00, $budgetData[0]['remaining']);
        $this->assertEquals(40.0, $budgetData[0]['percentage']);
        $this->assertFalse($budgetData[0]['is_over_budget']);
    }

    /** @test */
    public function it_detects_over_budget_categories()
    {
        // Create a budget for groceries: $100/month (low budget)
        Budget::create([
            'user_id' => $this->user->id,
            'category_id' => $this->expenseCategory->id,
            'amount' => 100.00,
            'period' => 'monthly',
            'start_date' => now()->startOfMonth(),
        ]);

        // Spend $150 (over budget by $50)
        Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->expenseCategory->id,
            'amount' => -150.00,
            'description' => 'Big grocery haul',
            'type' => 'expense',
            'date' => now(),
        ]);

        $component = Livewire::actingAs($this->user)
            ->test(BudgetTracker::class, [
                'month' => now()->month,
                'year' => now()->year,
            ]);

        $budgetData = $component->instance()->getBudgetData();
        
        $this->assertCount(1, $budgetData);
        $this->assertTrue($budgetData[0]['is_over_budget']);
        $this->assertEquals(-50.00, $budgetData[0]['remaining']);
        $this->assertEquals(50.00, $budgetData[0]['over_amount']);
        $this->assertEquals(150.0, $budgetData[0]['percentage']);
    }

    /** @test */
    public function it_only_includes_expenses_in_budget_calculations()
    {
        // Create a budget for groceries
        Budget::create([
            'user_id' => $this->user->id,
            'category_id' => $this->expenseCategory->id,
            'amount' => 200.00,
            'period' => 'monthly',
            'start_date' => now()->startOfMonth(),
        ]);

        // Create expense transaction
        Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->expenseCategory->id,
            'amount' => -100.00,
            'description' => 'Grocery expense',
            'type' => 'expense',
            'date' => now(),
        ]);

        // Create income transaction with same category (should not affect budget)
        Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->expenseCategory->id,
            'amount' => 50.00,
            'description' => 'Grocery refund',
            'type' => 'income',
            'date' => now(),
        ]);

        $component = Livewire::actingAs($this->user)
            ->test(BudgetTracker::class, [
                'month' => now()->month,
                'year' => now()->year,
            ]);

        $budgetData = $component->instance()->getBudgetData();
        
        // Should only count the $100 expense, not the $50 income
        $this->assertEquals(100.00, $budgetData[0]['spent']);
        $this->assertEquals(100.00, $budgetData[0]['remaining']);
    }

    /** @test */
    public function it_filters_transactions_by_month_and_year()
    {
        // Create budget for current month
        Budget::create([
            'user_id' => $this->user->id,
            'category_id' => $this->expenseCategory->id,
            'amount' => 200.00,
            'period' => 'monthly',
            'start_date' => now()->startOfMonth(),
        ]);

        // Transaction in current month
        Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->expenseCategory->id,
            'amount' => -100.00,
            'description' => 'This month expense',
            'type' => 'expense',
            'date' => now(),
        ]);

        // Transaction in previous month (should not count)
        Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->expenseCategory->id,
            'amount' => -50.00,
            'description' => 'Last month expense',
            'type' => 'expense',
            'date' => now()->subMonth(),
        ]);

        $component = Livewire::actingAs($this->user)
            ->test(BudgetTracker::class, [
                'month' => now()->month,
                'year' => now()->year,
            ]);

        $budgetData = $component->instance()->getBudgetData();
        
        // Should only count current month transaction
        $this->assertEquals(100.00, $budgetData[0]['spent']);
    }

    /** @test */
    public function it_calculates_total_budget_summary()
    {
        // Create two budgets
        Budget::create([
            'user_id' => $this->user->id,
            'category_id' => $this->expenseCategory->id,
            'amount' => 300.00,
            'period' => 'monthly',
            'start_date' => now()->startOfMonth(),
        ]);

        $category2 = Category::create([
            'name' => 'Transport',
            'icon' => '🚗',
            'type' => 'expense',
            'is_default' => true
        ]);

        Budget::create([
            'user_id' => $this->user->id,
            'category_id' => $category2->id,
            'amount' => 200.00,
            'period' => 'monthly',
            'start_date' => now()->startOfMonth(),
        ]);

        // Spend on both categories: one under budget, one over budget
        Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->expenseCategory->id,
            'amount' => -100.00, // under budget
            'description' => 'Groceries',
            'type' => 'expense',
            'date' => now(),
        ]);

        Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $category2->id,
            'amount' => -250.00, // over budget
            'description' => 'Gas',
            'type' => 'expense',
            'date' => now(),
        ]);

        $component = Livewire::actingAs($this->user)
            ->test(BudgetTracker::class, [
                'month' => now()->month,
                'year' => now()->year,
            ]);

        $totalSummary = $component->instance()->getTotalBudgetSummary();
        
        $this->assertEquals(500.00, $totalSummary['total_budget']); // 300 + 200
        $this->assertEquals(350.00, $totalSummary['total_spent']); // 100 + 250
        $this->assertEquals(150.00, $totalSummary['total_remaining']); // 500 - 350
        $this->assertEquals(70.0, $totalSummary['total_percentage']); // 350/500 * 100
        $this->assertEquals(1, $totalSummary['over_budget_count']); // only transport is over
    }

    /** @test */
    public function it_shows_no_budgets_message_when_no_budgets_exist()
    {
        Livewire::actingAs($this->user)
            ->test(BudgetTracker::class, [
                'month' => now()->month,
                'year' => now()->year,
            ])
            ->assertSee('No budgets set for')
            ->assertSee('Create some budgets to track your spending!');
    }

    /** @test */
    public function it_updates_when_month_or_year_changes()
    {
        $component = Livewire::actingAs($this->user)
            ->test(BudgetTracker::class, [
                'month' => now()->month,
                'year' => now()->year,
            ]);

        $component->set('month', 12)
                  ->assertSet('month', 12);

        $component->set('year', 2025)
                  ->assertSet('year', 2025);
    }
}