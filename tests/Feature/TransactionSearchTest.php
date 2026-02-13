<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TransactionSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_transaction_model_search_scope()
    {
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id]);
        $category = Category::factory()->create();

        // Create test transactions
        Transaction::factory()->create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'description' => 'Grocery shopping at Walmart',
            'notes' => 'Weekly groceries',
        ]);

        Transaction::factory()->create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'description' => 'Gas station fill-up',
            'notes' => 'Monthly fuel cost',
        ]);

        Transaction::factory()->create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'description' => 'Coffee shop',
            'notes' => 'Morning coffee',
        ]);

        // Test description search
        $results = Transaction::search('Grocery')->get();
        $this->assertCount(1, $results);
        $this->assertEquals('Grocery shopping at Walmart', $results->first()->description);

        // Test notes search
        $results = Transaction::search('Monthly')->get();
        $this->assertCount(1, $results);
        $this->assertEquals('Gas station fill-up', $results->first()->description);

        // Test partial match
        $results = Transaction::search('shop')->get();
        $this->assertCount(2, $results); // Matches both "shopping" and "shop"
    }

    public function test_transaction_model_date_range_scope()
    {
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id]);

        // Create transactions with different dates
        Transaction::factory()->create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'date' => '2024-01-15',
        ]);

        Transaction::factory()->create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'date' => '2024-02-15',
        ]);

        Transaction::factory()->create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'date' => '2024-03-15',
        ]);

        // Test date range filtering
        $results = Transaction::dateRange('2024-01-01', '2024-02-28')->get();
        $this->assertCount(2, $results);

        // Test start date only
        $results = Transaction::dateRange('2024-02-01', null)->get();
        $this->assertCount(2, $results);

        // Test end date only
        $results = Transaction::dateRange(null, '2024-01-31')->get();
        $this->assertCount(1, $results);
    }

    public function test_transaction_model_amount_range_scope()
    {
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id]);

        // Create transactions with different amounts
        Transaction::factory()->create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'amount' => -50.00, // Expense
        ]);

        Transaction::factory()->create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'amount' => 100.00, // Income
        ]);

        Transaction::factory()->create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'amount' => -25.00, // Expense
        ]);

        // Test amount range filtering
        $results = Transaction::amountRange(-60, -20)->get();
        $this->assertCount(2, $results); // Both expenses

        // Test minimum amount only
        $results = Transaction::amountRange(50, null)->get();
        $this->assertCount(1, $results); // Income only

        // Test maximum amount only
        $results = Transaction::amountRange(null, -40)->get();
        $this->assertCount(1, $results); // -50.00 expense only
    }

    public function test_transaction_list_component_renders()
    {
        $user = User::factory()->create();
        Account::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user);

        $component = Livewire::test('transaction-list');

        $component->assertSee('Transactions')
                  ->assertSee('Search and filter')
                  ->assertSee('Filters')
                  ->assertSee('Search')
                  ->assertSee('Account')
                  ->assertSee('Category');
    }

    public function test_transaction_list_component_search_functionality()
    {
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id]);
        $category = Category::factory()->create(['name' => 'Food']);

        // Create test transactions
        Transaction::factory()->create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'description' => 'Pizza restaurant',
            'date' => now(),
        ]);

        Transaction::factory()->create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'description' => 'Gas station',
            'date' => now(),
        ]);

        $this->actingAs($user);

        $component = Livewire::test('transaction-list');

        // Test search
        $component->set('search', 'Pizza')
                  ->assertSee('Pizza restaurant')
                  ->assertDontSee('Gas station');

        // Clear search
        $component->set('search', '')
                  ->assertSee('Pizza restaurant')
                  ->assertSee('Gas station');
    }

    public function test_transaction_list_component_date_filtering()
    {
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id]);

        // Create transactions with different dates
        Transaction::factory()->create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'description' => 'January transaction',
            'date' => '2024-01-15',
        ]);

        Transaction::factory()->create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'description' => 'February transaction',
            'date' => '2024-02-15',
        ]);

        $this->actingAs($user);

        $component = Livewire::test('transaction-list');

        // Test date range filtering
        $component->set('startDate', '2024-01-01')
                  ->set('endDate', '2024-01-31')
                  ->assertSee('January transaction')
                  ->assertDontSee('February transaction');
    }

    public function test_transaction_list_component_account_filtering()
    {
        $user = User::factory()->create();
        $account1 = Account::factory()->create(['user_id' => $user->id, 'name' => 'Checking']);
        $account2 = Account::factory()->create(['user_id' => $user->id, 'name' => 'Savings']);

        // Create transactions in different accounts
        Transaction::factory()->create([
            'user_id' => $user->id,
            'account_id' => $account1->id,
            'description' => 'Checking transaction',
            'date' => now(),
        ]);

        Transaction::factory()->create([
            'user_id' => $user->id,
            'account_id' => $account2->id,
            'description' => 'Savings transaction',
            'date' => now(),
        ]);

        $this->actingAs($user);

        $component = Livewire::test('transaction-list');

        // Test account filtering
        $component->set('selectedAccount', $account1->id)
                  ->assertSee('Checking transaction')
                  ->assertDontSee('Savings transaction');
    }

    public function test_transaction_list_component_category_filtering()
    {
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id]);
        $foodCategory = Category::factory()->create(['name' => 'Food']);
        $gasCategory = Category::factory()->create(['name' => 'Gas']);

        // Create transactions in different categories
        Transaction::factory()->create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'category_id' => $foodCategory->id,
            'description' => 'Restaurant',
            'date' => now(),
        ]);

        Transaction::factory()->create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'category_id' => $gasCategory->id,
            'description' => 'Gas station',
            'date' => now(),
        ]);

        $this->actingAs($user);

        $component = Livewire::test('transaction-list');

        // Test category filtering
        $component->set('selectedCategory', $foodCategory->id)
                  ->assertSee('Restaurant')
                  ->assertDontSee('Gas station');
    }

    public function test_transaction_list_component_amount_filtering()
    {
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id]);

        // Create transactions with different amounts
        Transaction::factory()->create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'description' => 'Small expense',
            'amount' => -10.00,
            'date' => now(),
        ]);

        Transaction::factory()->create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'description' => 'Large expense',
            'amount' => -100.00,
            'date' => now(),
        ]);

        $this->actingAs($user);

        $component = Livewire::test('transaction-list');

        // Test minimum amount filtering
        $component->set('minAmount', -50)
                  ->assertSee('Small expense')
                  ->assertDontSee('Large expense');

        // Test maximum amount filtering  
        $component->set('minAmount', '')
                  ->set('maxAmount', -50)
                  ->assertSee('Large expense')
                  ->assertDontSee('Small expense');
    }

    public function test_transaction_list_component_sorting()
    {
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id]);

        // Create transactions with different dates and amounts
        $transaction1 = Transaction::factory()->create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'description' => 'First transaction',
            'amount' => -50.00,
            'date' => now(),
        ]);

        $transaction2 = Transaction::factory()->create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'description' => 'Second transaction',
            'amount' => -25.00,
            'date' => now()->subDay(),
        ]);

        $this->actingAs($user);

        $component = Livewire::test('transaction-list');

        // Default sorting should be date desc (newest first)
        $component->assertSeeInOrder(['First transaction', 'Second transaction']);

        // Test sorting by amount
        $component->call('sortBy', 'amount');
        // After sorting by amount ascending, -50.00 comes before -25.00
        $component->assertSeeInOrder(['First transaction', 'Second transaction']);
    }

    public function test_transaction_list_component_clear_filters()
    {
        $user = User::factory()->create();
        Account::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user);

        $component = Livewire::test('transaction-list');

        // Set some filters
        $component->set('search', 'test')
                  ->set('minAmount', 50)
                  ->set('maxAmount', 100);

        // Clear filters
        $component->call('clearFilters');

        // Check filters are cleared
        $this->assertEquals('', $component->get('search'));
        $this->assertEquals('', $component->get('minAmount'));
        $this->assertEquals('', $component->get('maxAmount'));
    }

    public function test_transaction_list_component_calculates_stats()
    {
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id]);

        // Create test transactions
        Transaction::factory()->create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'amount' => 500.00, // Income
            'type' => 'income',
            'date' => now(),
        ]);

        Transaction::factory()->create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'amount' => -200.00, // Expense
            'type' => 'expense',
            'date' => now(),
        ]);

        Transaction::factory()->create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'amount' => -100.00, // Expense
            'type' => 'expense',
            'date' => now(),
        ]);

        $this->actingAs($user);

        $component = Livewire::test('transaction-list');

        // Check stats calculation
        $stats = $component->get('filteredStats');
        $this->assertEquals(3, $stats['total_transactions']);
        $this->assertEquals(500.00, $stats['total_income']);
        $this->assertEquals(300.00, $stats['total_expenses']); // Absolute value
        $this->assertEquals(200.00, $stats['net_amount']); // 500 - 300
    }
}