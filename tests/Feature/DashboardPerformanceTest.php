<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Services\DashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DashboardPerformanceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Account $account1;
    private Account $account2;
    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->account1 = Account::factory()->create([
            'user_id' => $this->user->id,
            'initial_balance' => 1000.00,
        ]);
        $this->account2 = Account::factory()->create([
            'user_id' => $this->user->id,
            'initial_balance' => 2000.00,
        ]);
        $this->category = Category::factory()->create();
    }

    public function test_account_balance_uses_database_aggregation()
    {
        // Create transactions for testing balance calculation
        Transaction::factory()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account1->id,
            'amount' => 500.00,
        ]);
        
        Transaction::factory()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account1->id,
            'amount' => -200.00,
        ]);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $balance = $this->account1->fresh()->getBalanceEfficient();

        $queries = DB::getQueryLog();
        
        // Should use single aggregation query, not load all transactions
        $this->assertEquals(1300.00, $balance); // 1000 + 500 - 200
        $this->assertCount(1, $queries);
        $this->assertStringContainsString('SUM', $queries[0]['query']);
    }

    public function test_transaction_scopes_work_correctly()
    {
        // Create test transactions
        $transaction1 = Transaction::factory()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account1->id,
            'amount' => 100.00,
            'date' => now()->startOfMonth(),
        ]);
        
        $transaction2 = Transaction::factory()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account2->id,
            'amount' => 200.00,
            'date' => now()->startOfMonth()->addDay(),
        ]);
        
        // Test different user's transaction
        $otherUser = User::factory()->create();
        Transaction::factory()->create([
            'user_id' => $otherUser->id,
            'account_id' => $this->account1->id,
            'amount' => 999.00,
            'date' => now()->startOfMonth(),
        ]);

        // Test forUser scope
        $userTransactions = Transaction::forUser($this->user->id)->get();
        $this->assertCount(2, $userTransactions);
        $this->assertTrue($userTransactions->contains($transaction1));
        $this->assertTrue($userTransactions->contains($transaction2));

        // Test forAccount scope
        $account1Transactions = Transaction::forAccount($this->account1->id)->get();
        $this->assertCount(2, $account1Transactions); // One for our user, one for other user
        $this->assertTrue($account1Transactions->contains($transaction1));

        // Test currentMonth scope
        $monthlyTransactions = Transaction::forUser($this->user->id)->currentMonth()->get();
        $this->assertCount(2, $monthlyTransactions);

        // Test sumAmount scope
        $totalAmount = Transaction::forUser($this->user->id)->sumAmount();
        $this->assertEquals(300.00, $totalAmount);
    }

    public function test_monthly_summary_caching_works()
    {
        Cache::flush();
        
        Transaction::factory()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account1->id,
            'amount' => 1000.00,
            'date' => now()->startOfMonth(),
        ]);
        
        Transaction::factory()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account1->id,
            'amount' => -300.00,
            'date' => now()->startOfMonth()->addDay(),
        ]);

        $service = new DashboardService();
        
        // First call should hit database
        DB::flushQueryLog();
        DB::enableQueryLog();
        
        $summary1 = $service->getMonthlySummary($this->user->id, now()->year, now()->format('m'));
        
        $firstCallQueries = count(DB::getQueryLog());
        $this->assertGreaterThan(0, $firstCallQueries);
        
        // Second call should use cache
        DB::flushQueryLog();
        DB::enableQueryLog();
        
        $summary2 = $service->getMonthlySummary($this->user->id, now()->year, now()->format('m'));
        
        $secondCallQueries = count(DB::getQueryLog());
        $this->assertEquals(0, $secondCallQueries, 'Second call should use cache, not database');
        
        // Results should be identical
        $this->assertEquals($summary1, $summary2);
        $this->assertEquals(1000, $summary1['total_income']);
        $this->assertEquals(300, $summary1['total_expenses']);
        $this->assertEquals(700, $summary1['net_amount']);
    }

    public function test_cache_invalidation_on_transaction_create()
    {
        Cache::flush();
        $service = new DashboardService();
        
        // Populate cache
        $service->getMonthlySummary($this->user->id, now()->year, now()->format('m'));
        $service->getAccountsWithBalances($this->user->id);
        
        // Verify cache exists
        $cacheKey = "monthly_summary_{$this->user->id}_" . now()->year . '_' . now()->format('m');
        $this->assertTrue(Cache::has($cacheKey));
        
        // Create new transaction (this should trigger observer and invalidate cache)
        Transaction::factory()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account1->id,
            'amount' => 100.00,
        ]);
        
        // Cache should be invalidated
        $this->assertFalse(Cache::has($cacheKey));
    }
}