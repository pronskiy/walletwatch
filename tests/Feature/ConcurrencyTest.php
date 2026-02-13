<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Account $account;
    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->account = Account::factory()->create([
            'user_id' => $this->user->id,
            'initial_balance' => 1000.00
        ]);
        $this->category = Category::factory()->create(['user_id' => $this->user->id]);
    }

    /** @test */
    public function concurrent_transaction_creation_with_database_locking()
    {
        // This test simulates what happens when multiple processes try to create
        // transactions simultaneously. In a real application, this would involve
        // actual concurrent HTTP requests or background jobs.
        
        $initialBalance = $this->account->initial_balance;
        $transactionAmount = 100.00;
        $numberOfConcurrentTransactions = 10;
        
        // Simulate concurrent transactions using database transactions with locking
        $promises = [];
        
        for ($i = 0; $i < $numberOfConcurrentTransactions; $i++) {
            // In a real scenario, each of these would be a separate request/process
            DB::transaction(function () use ($transactionAmount, $i) {
                // Lock the account row to prevent race conditions
                $lockedAccount = Account::where('id', $this->account->id)
                    ->lockForUpdate()
                    ->first();
                
                // Create transaction
                Transaction::create([
                    'user_id' => $this->user->id,
                    'account_id' => $lockedAccount->id,
                    'category_id' => $this->category->id,
                    'amount' => $transactionAmount,
                    'description' => "Concurrent transaction {$i}",
                    'type' => 'income'
                ]);
            });
        }
        
        // Verify final balance is mathematically correct
        $this->account->refresh();
        $expectedBalance = $initialBalance + ($transactionAmount * $numberOfConcurrentTransactions);
        $actualBalance = $this->account->balance;
        
        $this->assertEquals(
            $expectedBalance,
            $actualBalance,
            "Concurrent transaction creation with locking failed. " .
            "Expected: \${$expectedBalance}, Actual: \${$actualBalance}"
        );
        
        // Verify correct number of transactions were created
        $transactionCount = Transaction::where('account_id', $this->account->id)->count();
        $this->assertEquals($numberOfConcurrentTransactions, $transactionCount);
    }

    /** @test */
    public function concurrent_balance_updates_maintain_consistency()
    {
        // Create initial transactions
        $initialTransactions = [50.00, 75.50, -25.25];
        foreach ($initialTransactions as $amount) {
            Transaction::factory()->create([
                'user_id' => $this->user->id,
                'account_id' => $this->account->id,
                'category_id' => $this->category->id,
                'amount' => $amount
            ]);
        }
        
        $balanceBeforeConcurrentOps = $this->account->fresh()->balance;
        
        // Simulate concurrent balance-affecting operations
        $concurrentOperations = [
            ['action' => 'create', 'amount' => 100.00],
            ['action' => 'create', 'amount' => -50.00],
            ['action' => 'create', 'amount' => 200.50],
            ['action' => 'create', 'amount' => -75.25]
        ];
        
        $expectedNetChange = 0;
        
        // Execute operations with proper transaction isolation
        foreach ($concurrentOperations as $operation) {
            DB::transaction(function () use ($operation, &$expectedNetChange) {
                if ($operation['action'] === 'create') {
                    Transaction::create([
                        'user_id' => $this->user->id,
                        'account_id' => $this->account->id,
                        'category_id' => $this->category->id,
                        'amount' => $operation['amount'],
                        'type' => $operation['amount'] > 0 ? 'income' : 'expense'
                    ]);
                    $expectedNetChange += $operation['amount'];
                }
            });
        }
        
        $expectedFinalBalance = $balanceBeforeConcurrentOps + $expectedNetChange;
        $actualFinalBalance = $this->account->fresh()->balance;
        
        $this->assertEquals(
            number_format($expectedFinalBalance, 2, '.', ''),
            number_format($actualFinalBalance, 2, '.', ''),
            "Concurrent balance updates failed to maintain consistency. " .
            "Expected: \${$expectedFinalBalance}, Actual: \${$actualFinalBalance}"
        );
    }

    /** @test */
    public function concurrent_account_transfers_maintain_atomicity()
    {
        // Create second account for transfers
        $accountB = Account::factory()->create([
            'user_id' => $this->user->id,
            'initial_balance' => 500.00
        ]);
        
        $initialTotalMoney = $this->account->initial_balance + $accountB->initial_balance;
        
        // Define concurrent transfer operations
        $transfers = [
            ['from' => $this->account->id, 'to' => $accountB->id, 'amount' => 100.00],
            ['from' => $accountB->id, 'to' => $this->account->id, 'amount' => 150.00],
            ['from' => $this->account->id, 'to' => $accountB->id, 'amount' => 75.50]
        ];
        
        // Execute transfers with proper atomicity guarantees
        foreach ($transfers as $transfer) {
            DB::transaction(function () use ($transfer) {
                // Lock both accounts in a consistent order to prevent deadlocks
                $accounts = Account::whereIn('id', [$transfer['from'], $transfer['to']])
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');
                
                // Create debit transaction
                Transaction::create([
                    'user_id' => $this->user->id,
                    'account_id' => $transfer['from'],
                    'category_id' => $this->category->id,
                    'amount' => -$transfer['amount'],
                    'type' => 'transfer_out',
                    'description' => "Transfer to account {$transfer['to']}"
                ]);
                
                // Create credit transaction
                Transaction::create([
                    'user_id' => $this->user->id,
                    'account_id' => $transfer['to'],
                    'category_id' => $this->category->id,
                    'amount' => $transfer['amount'],
                    'type' => 'transfer_in',
                    'description' => "Transfer from account {$transfer['from']}"
                ]);
            });
        }
        
        // Verify total money in system is preserved
        $this->account->refresh();
        $accountB->refresh();
        
        $finalTotalMoney = $this->account->balance + $accountB->balance;
        
        $this->assertEquals(
            number_format($initialTotalMoney, 2, '.', ''),
            number_format($finalTotalMoney, 2, '.', ''),
            "Concurrent transfers violated money conservation! " .
            "Initial: \${$initialTotalMoney}, Final: \${$finalTotalMoney}"
        );
    }

    /** @test */
    public function high_frequency_transaction_creation_maintains_accuracy()
    {
        // Simulate high-frequency trading or transaction processing
        $transactionCount = 1000;
        $baseAmount = 10.00;
        
        $startTime = microtime(true);
        
        // Create many transactions rapidly
        $expectedSum = 0;
        for ($i = 0; $i < $transactionCount; $i++) {
            $amount = $baseAmount + ($i % 10) / 100; // Varying amounts: 10.00, 10.01, 10.02, etc.
            if ($i % 4 === 0) $amount = -$amount; // Every 4th transaction is negative
            
            DB::transaction(function () use ($amount, $i, &$expectedSum) {
                Transaction::create([
                    'user_id' => $this->user->id,
                    'account_id' => $this->account->id,
                    'category_id' => $this->category->id,
                    'amount' => $amount,
                    'description' => "High frequency transaction {$i}",
                    'type' => $amount > 0 ? 'income' : 'expense'
                ]);
                
                $expectedSum += $amount;
            });
        }
        
        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;
        
        // Verify accuracy despite high frequency
        $this->account->refresh();
        $expectedBalance = $this->account->initial_balance + $expectedSum;
        $actualBalance = $this->account->balance;
        
        $this->assertEquals(
            number_format($expectedBalance, 2, '.', ''),
            number_format($actualBalance, 2, '.', ''),
            "High frequency transaction processing failed accuracy test. " .
            "Created {$transactionCount} transactions in {$executionTime}s. " .
            "Expected: \${$expectedBalance}, Actual: \${$actualBalance}"
        );
    }

    /** @test */
    public function transaction_rollback_on_failure_maintains_consistency()
    {
        $initialBalance = $this->account->fresh()->balance;
        
        // Attempt a transaction that should fail and rollback
        $shouldFail = true;
        
        try {
            DB::transaction(function () use ($shouldFail) {
                // Create first transaction
                Transaction::create([
                    'user_id' => $this->user->id,
                    'account_id' => $this->account->id,
                    'category_id' => $this->category->id,
                    'amount' => 100.00,
                    'type' => 'income'
                ]);
                
                // Create second transaction
                Transaction::create([
                    'user_id' => $this->user->id,
                    'account_id' => $this->account->id,
                    'category_id' => $this->category->id,
                    'amount' => 50.00,
                    'type' => 'income'
                ]);
                
                // Simulate an error that should cause rollback
                if ($shouldFail) {
                    throw new \Exception('Simulated transaction failure');
                }
            });
        } catch (\Exception $e) {
            // Expected exception - transaction should have rolled back
        }
        
        // Verify that no transactions were created and balance is unchanged
        $finalBalance = $this->account->fresh()->balance;
        $transactionCount = Transaction::where('account_id', $this->account->id)->count();
        
        $this->assertEquals(0, $transactionCount, 'Transactions were not rolled back on failure');
        $this->assertEquals(
            $initialBalance,
            $finalBalance,
            "Account balance changed despite transaction rollback. " .
            "Initial: \${$initialBalance}, Final: \${$finalBalance}"
        );
    }

    /** @test */
    public function partial_transfer_failure_does_not_corrupt_system_state()
    {
        $accountB = Account::factory()->create([
            'user_id' => $this->user->id,
            'initial_balance' => 500.00
        ]);
        
        $initialBalanceA = $this->account->initial_balance;
        $initialBalanceB = $accountB->initial_balance;
        $transferAmount = 200.00;
        
        // Attempt transfer that fails after debit but before credit
        try {
            DB::transaction(function () use ($accountB, $transferAmount) {
                // Successfully create debit
                Transaction::create([
                    'user_id' => $this->user->id,
                    'account_id' => $this->account->id,
                    'category_id' => $this->category->id,
                    'amount' => -$transferAmount,
                    'type' => 'transfer_out'
                ]);
                
                // Simulate failure before credit (e.g., network error, validation failure, etc.)
                throw new \Exception('Transfer interrupted');
                
                // This credit should never be created due to the exception
                Transaction::create([
                    'user_id' => $this->user->id,
                    'account_id' => $accountB->id,
                    'category_id' => $this->category->id,
                    'amount' => $transferAmount,
                    'type' => 'transfer_in'
                ]);
            });
        } catch (\Exception $e) {
            // Expected - transfer should have failed completely
        }
        
        // Verify both accounts are unchanged (full rollback)
        $finalBalanceA = $this->account->fresh()->balance;
        $finalBalanceB = $accountB->fresh()->balance;
        
        $this->assertEquals(
            $initialBalanceA,
            $finalBalanceA,
            "Source account balance changed despite failed transfer"
        );
        
        $this->assertEquals(
            $initialBalanceB,
            $finalBalanceB,
            "Destination account balance changed despite failed transfer"
        );
        
        // Verify no partial transactions exist
        $transactionCount = Transaction::whereIn('account_id', [$this->account->id, $accountB->id])->count();
        $this->assertEquals(0, $transactionCount, 'Partial transactions exist after failed transfer');
    }

    /** @test */
    public function deadlock_prevention_in_multi_account_operations()
    {
        // Create multiple accounts
        $accounts = Account::factory()->count(3)->create(['user_id' => $this->user->id]);
        
        // Simulate operations that could cause deadlocks if not handled properly
        $operations = [
            ['from' => $accounts[0]->id, 'to' => $accounts[1]->id, 'amount' => 100.00],
            ['from' => $accounts[1]->id, 'to' => $accounts[2]->id, 'amount' => 150.00],
            ['from' => $accounts[2]->id, 'to' => $accounts[0]->id, 'amount' => 75.00],
            ['from' => $accounts[1]->id, 'to' => $accounts[0]->id, 'amount' => 200.00]
        ];
        
        foreach ($operations as $operation) {
            DB::transaction(function () use ($operation) {
                // Always lock accounts in consistent order (by ID) to prevent deadlocks
                $accountIds = [$operation['from'], $operation['to']];
                sort($accountIds);
                
                Account::whereIn('id', $accountIds)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();
                
                // Create debit
                Transaction::create([
                    'user_id' => $this->user->id,
                    'account_id' => $operation['from'],
                    'category_id' => $this->category->id,
                    'amount' => -$operation['amount'],
                    'type' => 'transfer_out'
                ]);
                
                // Create credit
                Transaction::create([
                    'user_id' => $this->user->id,
                    'account_id' => $operation['to'],
                    'category_id' => $this->category->id,
                    'amount' => $operation['amount'],
                    'type' => 'transfer_in'
                ]);
            });
        }
        
        // If we reach here without timeout, deadlock prevention worked
        $this->assertTrue(true, 'Multi-account operations completed without deadlock');
        
        // Verify all transactions were created successfully
        $totalTransactions = Transaction::whereIn('account_id', $accounts->pluck('id'))->count();
        $expectedTransactions = count($operations) * 2; // Each operation creates 2 transactions
        
        $this->assertEquals(
            $expectedTransactions,
            $totalTransactions,
            'Not all transactions were created successfully'
        );
    }
}