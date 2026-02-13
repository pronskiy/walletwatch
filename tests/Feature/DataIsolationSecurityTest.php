<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Budget;
use App\Models\Category;
use App\Models\RecurringTransaction;
use App\Models\Tag;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DataIsolationSecurityTest extends TestCase
{
    use RefreshDatabase;

    private User $userA;
    private User $userB;
    private Account $accountA;
    private Account $accountB;
    private Transaction $transactionA;
    private Transaction $transactionB;
    private Budget $budgetA;
    private Budget $budgetB;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create two users with complete separate data
        $this->userA = User::factory()->create(['email' => 'usera@test.com']);
        $this->userB = User::factory()->create(['email' => 'userb@test.com']);
        
        // Create accounts for each user
        $this->accountA = Account::factory()->create([
            'user_id' => $this->userA->id,
            'name' => 'User A Account',
            'initial_balance' => 1000.00
        ]);
        
        $this->accountB = Account::factory()->create([
            'user_id' => $this->userB->id,
            'name' => 'User B Account', 
            'initial_balance' => 2000.00
        ]);
        
        // Create transactions for each user
        $categoryA = Category::factory()->create(['user_id' => $this->userA->id]);
        $categoryB = Category::factory()->create(['user_id' => $this->userB->id]);
        
        $this->transactionA = Transaction::factory()->create([
            'user_id' => $this->userA->id,
            'account_id' => $this->accountA->id,
            'category_id' => $categoryA->id,
            'amount' => 100.00,
            'description' => 'User A Transaction'
        ]);
        
        $this->transactionB = Transaction::factory()->create([
            'user_id' => $this->userB->id,
            'account_id' => $this->accountB->id,
            'category_id' => $categoryB->id,
            'amount' => 200.00,
            'description' => 'User B Transaction'
        ]);
        
        // Create budgets for each user
        $this->budgetA = Budget::factory()->create([
            'user_id' => $this->userA->id,
            'category_id' => $categoryA->id,
            'name' => 'User A Budget'
        ]);
        
        $this->budgetB = Budget::factory()->create([
            'user_id' => $this->userB->id,
            'category_id' => $categoryB->id,
            'name' => 'User B Budget'
        ]);
    }

    /** @test */
    public function user_cannot_access_another_users_account_via_direct_url()
    {
        // User A tries to access User B's account directly
        $response = $this->actingAs($this->userA)->get("/accounts/{$this->accountB->id}");
        
        // Should either get 403 Forbidden, 404 Not Found, or redirect to authorized content
        // This test will FAIL if the route exists and doesn't check authorization
        $this->assertTrue(
            $response->status() === 403 || 
            $response->status() === 404 ||
            !str_contains($response->content(), $this->accountB->name),
            'User A should not be able to access User B\'s account details'
        );
    }

    /** @test */
    public function user_cannot_access_another_users_transactions_via_direct_url()
    {
        // User A tries to access User B's transaction directly
        $response = $this->actingAs($this->userA)->get("/transactions/{$this->transactionB->id}");
        
        $this->assertTrue(
            $response->status() === 403 || 
            $response->status() === 404 ||
            !str_contains($response->content(), $this->transactionB->description),
            'User A should not be able to access User B\'s transaction details'
        );
    }

    /** @test */
    public function user_cannot_edit_another_users_account()
    {
        // User A tries to edit User B's account
        $response = $this->actingAs($this->userA)->put("/accounts/{$this->accountB->id}", [
            'name' => 'Hacked Account Name',
            'initial_balance' => 999999.99
        ]);
        
        // Refresh the model to check if it was modified
        $this->accountB->refresh();
        
        $this->assertTrue(
            $response->status() === 403 || $response->status() === 404,
            'User A should not be able to edit User B\'s account'
        );
        
        $this->assertNotEquals('Hacked Account Name', $this->accountB->name);
        $this->assertNotEquals(999999.99, $this->accountB->initial_balance);
    }

    /** @test */
    public function user_cannot_delete_another_users_data()
    {
        // User A tries to delete User B's account
        $response = $this->actingAs($this->userA)->delete("/accounts/{$this->accountB->id}");
        
        $this->assertTrue(
            $response->status() === 403 || $response->status() === 404,
            'User A should not be able to delete User B\'s account'
        );
        
        // Account should still exist
        $this->assertDatabaseHas('accounts', ['id' => $this->accountB->id]);
    }

    /** @test */
    public function user_cannot_create_transaction_for_another_users_account()
    {
        // User A tries to create a transaction for User B's account
        $response = $this->actingAs($this->userA)->post("/transactions", [
            'account_id' => $this->accountB->id,
            'amount' => 500.00,
            'description' => 'Malicious transaction',
            'type' => 'expense'
        ]);
        
        // This should either fail completely or create transaction under User A's account
        if ($response->status() === 201 || $response->status() === 302) {
            // If it succeeds, make sure it didn't create transaction for User B's account
            $maliciousTransaction = Transaction::where('description', 'Malicious transaction')->first();
            if ($maliciousTransaction) {
                $this->assertNotEquals($this->accountB->id, $maliciousTransaction->account_id);
                $this->assertEquals($this->userA->id, $maliciousTransaction->user_id);
            }
        } else {
            $this->assertTrue(
                $response->status() === 403 || $response->status() === 422,
                'Creating transaction for another user\'s account should be forbidden'
            );
        }
    }

    /** @test */
    public function eloquent_queries_are_properly_scoped_to_authenticated_user()
    {
        $this->actingAs($this->userA);
        
        // These queries should only return User A's data
        $accounts = Account::all();
        $transactions = Transaction::all();
        $budgets = Budget::all();
        
        // If proper scoping is implemented, these should only contain User A's data
        foreach ($accounts as $account) {
            $this->assertEquals($this->userA->id, $account->user_id, 
                'Account query returned data from another user');
        }
        
        foreach ($transactions as $transaction) {
            $this->assertEquals($this->userA->id, $transaction->user_id,
                'Transaction query returned data from another user');
        }
        
        foreach ($budgets as $budget) {
            $this->assertEquals($this->userA->id, $budget->user_id,
                'Budget query returned data from another user');
        }
    }

    /** @test */
    public function user_relationships_only_return_own_data()
    {
        $this->actingAs($this->userA);
        
        // Test that user relationships only return their own data
        $userAAccounts = $this->userA->accounts;
        $userATransactions = $this->userA->transactions;
        $userABudgets = $this->userA->budgets;
        
        $this->assertCount(1, $userAAccounts);
        $this->assertEquals($this->accountA->id, $userAAccounts->first()->id);
        
        $this->assertCount(1, $userATransactions);
        $this->assertEquals($this->transactionA->id, $userATransactions->first()->id);
        
        $this->assertCount(1, $userABudgets);
        $this->assertEquals($this->budgetA->id, $userABudgets->first()->id);
    }

    /** @test */
    public function search_results_are_scoped_to_authenticated_user()
    {
        $this->actingAs($this->userA);
        
        // Search for transactions containing "User" - should only return User A's transaction
        $searchResults = Transaction::where('description', 'like', '%User%')->get();
        
        $this->assertNotEmpty($searchResults, 'Search should return User A\'s transaction');
        
        foreach ($searchResults as $result) {
            $this->assertEquals($this->userA->id, $result->user_id,
                'Search results included another user\'s data');
        }
        
        // Verify User B's transaction is not in results
        $userBTransactionIds = $searchResults->pluck('id')->toArray();
        $this->assertNotContains($this->transactionB->id, $userBTransactionIds,
            'Search results included User B\'s transaction');
    }

    /** @test */
    public function bulk_operations_are_scoped_to_authenticated_user()
    {
        $this->actingAs($this->userA);
        
        // Attempt a bulk delete that might affect all transactions
        $affectedRows = Transaction::where('amount', '>', 0)->delete();
        
        // Should only delete User A's transaction, not User B's
        $this->assertEquals(1, $affectedRows, 'Bulk delete affected wrong number of transactions');
        
        // User B's transaction should still exist
        $this->assertDatabaseHas('transactions', ['id' => $this->transactionB->id]);
    }

    /** @test */
    public function account_balance_calculation_only_includes_own_transactions()
    {
        $this->actingAs($this->userA);
        
        // Get User A's account balance
        $balance = $this->accountA->balance;
        
        // Balance should be initial_balance + User A's transaction amount
        $expectedBalance = $this->accountA->initial_balance + $this->transactionA->amount;
        $this->assertEquals($expectedBalance, $balance,
            'Account balance calculation may include other users\' transactions');
        
        // Create another transaction for User A to test recalculation
        $additionalTransaction = Transaction::factory()->create([
            'user_id' => $this->userA->id,
            'account_id' => $this->accountA->id,
            'amount' => 50.00
        ]);
        
        // Refresh the account and recalculate
        $this->accountA->refresh();
        $this->accountA->load('transactions');
        
        $newExpectedBalance = $this->accountA->initial_balance + 
            $this->transactionA->amount + $additionalTransaction->amount;
            
        $this->assertEquals($newExpectedBalance, $this->accountA->balance,
            'Account balance recalculation may include other users\' transactions');
    }

    /** @test */
    public function api_endpoints_respect_user_authorization()
    {
        // Test API endpoints if they exist
        $endpoints = [
            ['GET', "/api/accounts/{$this->accountB->id}"],
            ['PUT', "/api/accounts/{$this->accountB->id}"],
            ['DELETE', "/api/accounts/{$this->accountB->id}"],
            ['GET', "/api/transactions/{$this->transactionB->id}"],
            ['PUT', "/api/transactions/{$this->transactionB->id}"],
            ['DELETE', "/api/transactions/{$this->transactionB->id}"],
            ['GET', "/api/budgets/{$this->budgetB->id}"],
        ];
        
        foreach ($endpoints as [$method, $endpoint]) {
            $response = $this->actingAs($this->userA)->json($method, $endpoint);
            
            // API endpoints should return 403 or 404, not 200 with data
            $this->assertTrue(
                $response->status() === 403 || 
                $response->status() === 404 ||
                $response->status() === 405, // Method not allowed if endpoint doesn't exist
                "API endpoint {$method} {$endpoint} may expose another user's data"
            );
        }
    }
}