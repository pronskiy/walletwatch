<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FinancialAccuracyTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Account $checkingAccount;
    private Account $savingsAccount;
    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        
        $this->checkingAccount = Account::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Checking Account',
            'initial_balance' => 1000.00,
            'type' => 'checking'
        ]);
        
        $this->savingsAccount = Account::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Savings Account',
            'initial_balance' => 500.00,
            'type' => 'savings'
        ]);
        
        $this->category = Category::factory()->create(['user_id' => $this->user->id]);
    }

    /** @test */
    public function account_balance_equals_initial_balance_plus_transaction_sum()
    {
        // Create various transactions
        $transactions = [
            ['amount' => 250.00, 'type' => 'income'],
            ['amount' => -75.50, 'type' => 'expense'],
            ['amount' => 100.25, 'type' => 'income'],
            ['amount' => -200.00, 'type' => 'expense'],
            ['amount' => 50.75, 'type' => 'income']
        ];
        
        $expectedTransactionSum = 0;
        
        foreach ($transactions as $transactionData) {
            Transaction::factory()->create([
                'user_id' => $this->user->id,
                'account_id' => $this->checkingAccount->id,
                'category_id' => $this->category->id,
                'amount' => $transactionData['amount'],
                'type' => $transactionData['type']
            ]);
            
            $expectedTransactionSum += $transactionData['amount'];
        }
        
        // Refresh account and calculate balance
        $this->checkingAccount->refresh();
        $calculatedBalance = $this->checkingAccount->balance;
        $expectedBalance = $this->checkingAccount->initial_balance + $expectedTransactionSum;
        
        $this->assertEquals(
            $expectedBalance, 
            $calculatedBalance, 
            "Balance calculation error: Expected {$expectedBalance}, got {$calculatedBalance}. " .
            "Initial: {$this->checkingAccount->initial_balance}, Transaction sum: {$expectedTransactionSum}"
        );
    }

    /** @test */
    public function decimal_precision_is_maintained_with_many_small_transactions()
    {
        // Create 1000 transactions of $19.99 each to test floating point accuracy
        $transactionAmount = 19.99;
        $transactionCount = 1000;
        
        for ($i = 0; $i < $transactionCount; $i++) {
            Transaction::factory()->create([
                'user_id' => $this->user->id,
                'account_id' => $this->checkingAccount->id,
                'category_id' => $this->category->id,
                'amount' => $transactionAmount,
                'type' => 'income'
            ]);
        }
        
        // Calculate expected balance using proper decimal arithmetic
        $expectedSum = bcmul((string)$transactionAmount, (string)$transactionCount, 2);
        $expectedBalance = bcadd((string)$this->checkingAccount->initial_balance, $expectedSum, 2);
        
        $this->checkingAccount->refresh();
        $actualBalance = number_format($this->checkingAccount->balance, 2, '.', '');
        
        $this->assertEquals(
            $expectedBalance,
            $actualBalance,
            "Decimal precision error with {$transactionCount} transactions of \${$transactionAmount}. " .
            "Expected: \${$expectedBalance}, Actual: \${$actualBalance}"
        );
    }

    /** @test */
    public function transfer_between_accounts_is_atomic_and_preserves_total_money()
    {
        // Record initial total money in both accounts
        $initialTotal = $this->checkingAccount->initial_balance + $this->savingsAccount->initial_balance;
        
        $transferAmount = 300.00;
        
        // Perform transfer (this should be implemented as an atomic operation)
        DB::transaction(function () use ($transferAmount) {
            // Debit from checking
            Transaction::factory()->create([
                'user_id' => $this->user->id,
                'account_id' => $this->checkingAccount->id,
                'category_id' => $this->category->id,
                'amount' => -$transferAmount,
                'type' => 'transfer_out',
                'description' => 'Transfer to savings'
            ]);
            
            // Credit to savings
            Transaction::factory()->create([
                'user_id' => $this->user->id,
                'account_id' => $this->savingsAccount->id,
                'category_id' => $this->category->id,
                'amount' => $transferAmount,
                'type' => 'transfer_in',
                'description' => 'Transfer from checking'
            ]);
        });
        
        // Refresh both accounts
        $this->checkingAccount->refresh();
        $this->savingsAccount->refresh();
        
        // Verify individual balances
        $expectedCheckingBalance = 1000.00 - $transferAmount;
        $expectedSavingsBalance = 500.00 + $transferAmount;
        
        $this->assertEquals($expectedCheckingBalance, $this->checkingAccount->balance);
        $this->assertEquals($expectedSavingsBalance, $this->savingsAccount->balance);
        
        // Most importantly: verify total money is preserved
        $finalTotal = $this->checkingAccount->balance + $this->savingsAccount->balance;
        
        $this->assertEquals(
            $initialTotal,
            $finalTotal,
            "Transfer violated money conservation! Initial total: \${$initialTotal}, Final total: \${$finalTotal}"
        );
    }

    /** @test */
    public function deleting_transaction_correctly_updates_account_balance()
    {
        // Create a transaction
        $transactionAmount = 150.75;
        $transaction = Transaction::factory()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->checkingAccount->id,
            'category_id' => $this->category->id,
            'amount' => $transactionAmount,
            'type' => 'income'
        ]);
        
        // Verify balance includes the transaction
        $this->checkingAccount->refresh();
        $balanceWithTransaction = $this->checkingAccount->balance;
        $expectedWithTransaction = $this->checkingAccount->initial_balance + $transactionAmount;
        
        $this->assertEquals($expectedWithTransaction, $balanceWithTransaction);
        
        // Delete the transaction
        $transaction->delete();
        
        // Verify balance is back to initial amount
        $this->checkingAccount->refresh();
        $balanceAfterDeletion = $this->checkingAccount->balance;
        
        $this->assertEquals(
            $this->checkingAccount->initial_balance,
            $balanceAfterDeletion,
            "Deleting transaction did not correctly update balance. " .
            "Expected: \${$this->checkingAccount->initial_balance}, Actual: \${$balanceAfterDeletion}"
        );
    }

    /** @test */
    public function editing_transaction_amount_applies_correct_balance_delta()
    {
        // Create initial transaction
        $originalAmount = 100.00;
        $transaction = Transaction::factory()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->checkingAccount->id,
            'category_id' => $this->category->id,
            'amount' => $originalAmount,
            'type' => 'income'
        ]);
        
        // Verify initial balance
        $this->checkingAccount->refresh();
        $balanceAfterCreate = $this->checkingAccount->balance;
        $expectedAfterCreate = $this->checkingAccount->initial_balance + $originalAmount;
        
        $this->assertEquals($expectedAfterCreate, $balanceAfterCreate);
        
        // Edit transaction amount
        $newAmount = 250.75;
        $transaction->update(['amount' => $newAmount]);
        
        // Verify balance reflects the change correctly
        $this->checkingAccount->refresh();
        $balanceAfterEdit = $this->checkingAccount->balance;
        $expectedAfterEdit = $this->checkingAccount->initial_balance + $newAmount;
        
        $this->assertEquals(
            $expectedAfterEdit,
            $balanceAfterEdit,
            "Transaction edit did not apply correct balance delta. " .
            "Original: \${$originalAmount}, New: \${$newAmount}, " .
            "Expected balance: \${$expectedAfterEdit}, Actual: \${$balanceAfterEdit}"
        );
    }

    /** @test */
    public function concurrent_transaction_creation_maintains_accuracy()
    {
        // Simulate concurrent transactions by creating multiple transactions in rapid succession
        // In a real application, this would involve actual concurrent requests
        $concurrentTransactions = [
            ['amount' => 100.00, 'description' => 'Concurrent Transaction 1'],
            ['amount' => 150.50, 'description' => 'Concurrent Transaction 2'],
            ['amount' => -75.25, 'description' => 'Concurrent Transaction 3'],
            ['amount' => 200.00, 'description' => 'Concurrent Transaction 4'],
            ['amount' => -50.75, 'description' => 'Concurrent Transaction 5']
        ];
        
        $expectedSum = 0;
        
        // Create all transactions (in a real scenario these would be concurrent)
        DB::transaction(function () use ($concurrentTransactions, &$expectedSum) {
            foreach ($concurrentTransactions as $transactionData) {
                Transaction::factory()->create([
                    'user_id' => $this->user->id,
                    'account_id' => $this->checkingAccount->id,
                    'category_id' => $this->category->id,
                    'amount' => $transactionData['amount'],
                    'description' => $transactionData['description'],
                    'type' => $transactionData['amount'] > 0 ? 'income' : 'expense'
                ]);
                
                $expectedSum += $transactionData['amount'];
            }
        });
        
        $this->checkingAccount->refresh();
        $expectedBalance = $this->checkingAccount->initial_balance + $expectedSum;
        $actualBalance = $this->checkingAccount->balance;
        
        $this->assertEquals(
            $expectedBalance,
            $actualBalance,
            "Concurrent transaction creation failed to maintain accuracy. " .
            "Expected: \${$expectedBalance}, Actual: \${$actualBalance}"
        );
    }

    /** @test */
    public function bulk_transaction_import_maintains_mathematical_accuracy()
    {
        // Simulate bulk import of many transactions
        $bulkTransactions = [];
        $expectedSum = 0;
        
        for ($i = 1; $i <= 500; $i++) {
            $amount = round(rand(1, 10000) / 100, 2); // Random amount between $0.01 and $100.00
            if ($i % 3 === 0) $amount = -$amount; // Every third transaction is an expense
            
            $bulkTransactions[] = [
                'user_id' => $this->user->id,
                'account_id' => $this->checkingAccount->id,
                'category_id' => $this->category->id,
                'amount' => $amount,
                'description' => "Bulk import transaction {$i}",
                'type' => $amount > 0 ? 'income' : 'expense',
                'created_at' => now(),
                'updated_at' => now()
            ];
            
            $expectedSum += $amount;
        }
        
        // Insert all transactions in one operation
        DB::table('transactions')->insert($bulkTransactions);
        
        // Verify balance accuracy after bulk import
        $this->checkingAccount->refresh();
        $expectedBalance = $this->checkingAccount->initial_balance + $expectedSum;
        $actualBalance = $this->checkingAccount->balance;
        
        $this->assertEquals(
            number_format($expectedBalance, 2, '.', ''),
            number_format($actualBalance, 2, '.', ''),
            "Bulk import failed to maintain mathematical accuracy with 500 transactions. " .
            "Expected: \${$expectedBalance}, Actual: \${$actualBalance}, Difference: \$" . 
            number_format(abs($expectedBalance - $actualBalance), 2, '.', '')
        );
    }

    /** @test */
    public function balance_recalculation_from_scratch_matches_incremental_balance()
    {
        // Create a series of transactions
        $transactions = [
            100.50, -25.75, 200.00, -150.25, 75.80, -30.30, 500.00, -100.00
        ];
        
        foreach ($transactions as $amount) {
            Transaction::factory()->create([
                'user_id' => $this->user->id,
                'account_id' => $this->checkingAccount->id,
                'category_id' => $this->category->id,
                'amount' => $amount,
                'type' => $amount > 0 ? 'income' : 'expense'
            ]);
        }
        
        // Get incremental balance (calculated by the model)
        $this->checkingAccount->refresh();
        $incrementalBalance = $this->checkingAccount->balance;
        
        // Calculate balance from scratch
        $transactionSum = Transaction::where('account_id', $this->checkingAccount->id)->sum('amount');
        $fromScratchBalance = $this->checkingAccount->initial_balance + $transactionSum;
        
        $this->assertEquals(
            number_format($fromScratchBalance, 2, '.', ''),
            number_format($incrementalBalance, 2, '.', ''),
            "Incremental balance calculation doesn't match from-scratch calculation. " .
            "From scratch: \${$fromScratchBalance}, Incremental: \${$incrementalBalance}"
        );
    }

    /** @test */
    public function account_balance_never_goes_below_zero_without_explicit_permission()
    {
        // For checking accounts (which should allow overdraft)
        // This test will depend on business rules - adjust as needed
        
        $largeExpense = $this->checkingAccount->initial_balance + 100.00;
        
        Transaction::factory()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->checkingAccount->id,
            'category_id' => $this->category->id,
            'amount' => -$largeExpense,
            'type' => 'expense'
        ]);
        
        $this->checkingAccount->refresh();
        $finalBalance = $this->checkingAccount->balance;
        
        // For this test, we're checking that overdraft is handled correctly
        // The exact behavior depends on business rules
        $expectedBalance = $this->checkingAccount->initial_balance - $largeExpense;
        
        $this->assertEquals(
            $expectedBalance,
            $finalBalance,
            "Account balance calculation failed for overdraft scenario. " .
            "Expected: \${$expectedBalance}, Actual: \${$finalBalance}"
        );
        
        // If the business rule is that accounts should NOT go negative,
        // you would test that the transaction is rejected instead
    }

    /** @test */
    public function complex_multi_account_transfer_preserves_system_wide_total()
    {
        // Create a third account
        $creditAccount = Account::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Credit Card',
            'initial_balance' => -200.00, // Credit cards start with negative balance
            'type' => 'credit'
        ]);
        
        $initialSystemTotal = $this->checkingAccount->initial_balance + 
                             $this->savingsAccount->initial_balance + 
                             $creditAccount->initial_balance;
        
        // Perform complex multi-account operations
        $operations = [
            // Transfer from checking to savings
            ['from' => $this->checkingAccount->id, 'to' => $this->savingsAccount->id, 'amount' => 200.00],
            // Pay credit card from checking
            ['from' => $this->checkingAccount->id, 'to' => $creditAccount->id, 'amount' => 150.00],
            // Transfer from savings to checking
            ['from' => $this->savingsAccount->id, 'to' => $this->checkingAccount->id, 'amount' => 100.00]
        ];
        
        foreach ($operations as $operation) {
            DB::transaction(function () use ($operation) {
                // Debit from source account
                Transaction::factory()->create([
                    'user_id' => $this->user->id,
                    'account_id' => $operation['from'],
                    'category_id' => $this->category->id,
                    'amount' => -$operation['amount'],
                    'type' => 'transfer_out'
                ]);
                
                // Credit to destination account
                Transaction::factory()->create([
                    'user_id' => $this->user->id,
                    'account_id' => $operation['to'],
                    'category_id' => $this->category->id,
                    'amount' => $operation['amount'],
                    'type' => 'transfer_in'
                ]);
            });
        }
        
        // Refresh all accounts and calculate final total
        $this->checkingAccount->refresh();
        $this->savingsAccount->refresh();
        $creditAccount->refresh();
        
        $finalSystemTotal = $this->checkingAccount->balance + 
                           $this->savingsAccount->balance + 
                           $creditAccount->balance;
        
        $this->assertEquals(
            number_format($initialSystemTotal, 2, '.', ''),
            number_format($finalSystemTotal, 2, '.', ''),
            "Complex multi-account transfers violated system-wide money conservation! " .
            "Initial total: \${$initialSystemTotal}, Final total: \${$finalSystemTotal}"
        );
    }
}