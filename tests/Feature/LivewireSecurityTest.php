<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LivewireSecurityTest extends TestCase
{
    use RefreshDatabase;

    private User $userA;
    private User $userB;
    private Account $accountA;
    private Account $accountB;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->userA = User::factory()->create(['email' => 'usera@test.com']);
        $this->userB = User::factory()->create(['email' => 'userb@test.com']);
        
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
    }

    /** @test */
    public function livewire_components_reject_foreign_model_ids_during_hydration()
    {
        $this->actingAs($this->userA);
        
        // Test if TransactionForm component exists and try to hydrate with foreign account
        if (class_exists('\App\Livewire\TransactionForm')) {
            $component = Livewire::test(\App\Livewire\TransactionForm::class);
            
            // Try to set a foreign account ID
            $response = $component->set('account_id', $this->accountB->id);
            
            // Component should either:
            // 1. Reject the foreign ID
            // 2. Reset to null/valid account
            // 3. Show validation error
            $this->assertTrue(
                $component->get('account_id') !== $this->accountB->id ||
                $component->has('errors'),
                'Livewire component accepted foreign account ID'
            );
        }
    }

    /** @test */
    public function livewire_component_cannot_create_transaction_with_foreign_account()
    {
        $this->actingAs($this->userA);
        
        // Test TransactionForm component if it exists
        if (class_exists('\App\Livewire\TransactionForm')) {
            $component = Livewire::test(\App\Livewire\TransactionForm::class);
            
            // Try to submit transaction with foreign account
            $component
                ->set('account_id', $this->accountB->id)
                ->set('amount', 100.00)
                ->set('description', 'Malicious transaction')
                ->set('type', 'expense')
                ->call('save');
            
            // Check that no transaction was created for User B's account
            $maliciousTransaction = Transaction::where('description', 'Malicious transaction')
                ->where('account_id', $this->accountB->id)
                ->first();
                
            $this->assertNull($maliciousTransaction, 
                'Livewire component created transaction for foreign account');
                
            // If transaction was created, it should be for User A's account only
            $validTransaction = Transaction::where('description', 'Malicious transaction')
                ->where('user_id', $this->userA->id)
                ->first();
                
            if ($validTransaction) {
                $this->assertNotEquals($this->accountB->id, $validTransaction->account_id);
            }
        }
    }

    /** @test */
    public function livewire_component_hydration_with_manipulated_properties()
    {
        $this->actingAs($this->userA);
        
        // Test CategoryDropdown component if it exists
        if (class_exists('\App\Livewire\CategoryDropdown')) {
            $foreignCategory = Category::factory()->create(['user_id' => $this->userB->id]);
            
            $component = Livewire::test(\App\Livewire\CategoryDropdown::class);
            
            // Try to set foreign category ID
            $component->set('selected_category_id', $foreignCategory->id);
            
            // Component should reject foreign category
            $categories = $component->get('categories') ?? collect();
            $foreignCategoryIds = $categories->pluck('id')->toArray();
            
            $this->assertNotContains($foreignCategory->id, $foreignCategoryIds,
                'CategoryDropdown exposed foreign user\'s category');
        }
    }

    /** @test */
    public function livewire_component_cannot_access_foreign_data_through_relationships()
    {
        $this->actingAs($this->userA);
        
        // Create transaction for User B with category
        $categoryB = Category::factory()->create(['user_id' => $this->userB->id]);
        $transactionB = Transaction::factory()->create([
            'user_id' => $this->userB->id,
            'account_id' => $this->accountB->id,
            'category_id' => $categoryB->id
        ]);
        
        // If a component loads transactions with relationships
        if (class_exists('\App\Livewire\TransactionList')) {
            $component = Livewire::test(\App\Livewire\TransactionList::class);
            
            // Get all loaded transactions
            $transactions = $component->get('transactions') ?? collect();
            
            foreach ($transactions as $transaction) {
                // Ensure no foreign transactions are loaded
                $this->assertEquals($this->userA->id, $transaction['user_id'] ?? $transaction->user_id,
                    'TransactionList component loaded foreign user\'s transaction');
                    
                // If categories are eager loaded, they should only be user's categories
                if (isset($transaction['category']) || (is_object($transaction) && $transaction->relationLoaded('category'))) {
                    $category = $transaction['category'] ?? $transaction->category;
                    if ($category) {
                        $this->assertEquals($this->userA->id, $category['user_id'] ?? $category->user_id,
                            'TransactionList loaded foreign user\'s category through relationship');
                    }
                }
            }
        }
    }

    /** @test */
    public function livewire_component_mass_updates_are_scoped_to_user()
    {
        $this->actingAs($this->userA);
        
        // Create multiple transactions for both users
        $transactionA1 = Transaction::factory()->create([
            'user_id' => $this->userA->id,
            'account_id' => $this->accountA->id,
            'amount' => 100.00,
            'description' => 'Transaction A1'
        ]);
        
        $transactionA2 = Transaction::factory()->create([
            'user_id' => $this->userA->id,
            'account_id' => $this->accountA->id,
            'amount' => 200.00,
            'description' => 'Transaction A2'
        ]);
        
        $transactionB1 = Transaction::factory()->create([
            'user_id' => $this->userB->id,
            'account_id' => $this->accountB->id,
            'amount' => 300.00,
            'description' => 'Transaction B1'
        ]);
        
        // Test bulk operations component if it exists
        if (class_exists('\App\Livewire\BulkTransactionManager')) {
            $component = Livewire::test(\App\Livewire\BulkTransactionManager::class);
            
            // Try bulk delete
            $component->call('deleteAll');
            
            // User B's transaction should still exist
            $this->assertDatabaseHas('transactions', [
                'id' => $transactionB1->id,
                'user_id' => $this->userB->id
            ]);
            
            // User A's transactions should be deleted (if that's the intended behavior)
            // or component should have properly scoped the operation
        }
    }

    /** @test */
    public function livewire_component_validation_prevents_foreign_id_injection()
    {
        $this->actingAs($this->userA);
        
        // Test form validation with foreign IDs
        if (class_exists('\App\Livewire\TransactionForm')) {
            $component = Livewire::test(\App\Livewire\TransactionForm::class);
            
            // Try to inject foreign account ID and submit
            $component
                ->set('account_id', $this->accountB->id)
                ->set('amount', 50.00)
                ->set('description', 'Test transaction')
                ->call('save');
            
            // Should have validation errors for account_id
            $this->assertTrue(
                $component->hasErrors('account_id') ||
                $component->get('account_id') !== $this->accountB->id,
                'Component validation did not prevent foreign account ID injection'
            );
        }
    }

    /** @test */
    public function livewire_component_computed_properties_are_user_scoped()
    {
        $this->actingAs($this->userA);
        
        // Create categories for both users
        $categoryA = Category::factory()->create(['user_id' => $this->userA->id, 'name' => 'User A Category']);
        $categoryB = Category::factory()->create(['user_id' => $this->userB->id, 'name' => 'User B Category']);
        
        // Test component computed properties
        if (class_exists('\App\Livewire\CategoryDropdown')) {
            $component = Livewire::test(\App\Livewire\CategoryDropdown::class);
            
            // Get computed categories
            $categories = $component->get('categories') ?? collect();
            
            // Should only contain User A's categories
            foreach ($categories as $category) {
                $this->assertEquals($this->userA->id, $category['user_id'] ?? $category->user_id,
                    'Computed categories property included foreign user\'s data');
            }
            
            // Specifically check that User B's category is not included
            $categoryNames = is_array($categories) ? 
                collect($categories)->pluck('name')->toArray() :
                $categories->pluck('name')->toArray();
                
            $this->assertNotContains('User B Category', $categoryNames,
                'Computed categories included foreign user\'s category');
        }
    }

    /** @test */
    public function livewire_component_state_cannot_be_manipulated_to_access_foreign_data()
    {
        $this->actingAs($this->userA);
        
        // Test direct property manipulation attack
        if (class_exists('\App\Livewire\TransactionForm')) {
            // Try to manipulate component state to access foreign data
            $response = $this->post('/livewire/message/transaction-form', [
                'fingerprint' => [
                    'id' => 'transaction-form',
                    'name' => 'transaction-form',
                    'locale' => 'en',
                    'path' => '/',
                    'method' => 'GET'
                ],
                'serverMemo' => [
                    'checksum' => 'test',
                    'htmlHash' => 'test',
                    'data' => [
                        'account_id' => $this->accountB->id, // Foreign account ID
                    ]
                ],
                'updates' => [
                    [
                        'type' => 'syncInput',
                        'payload' => [
                            'name' => 'account_id',
                            'value' => $this->accountB->id
                        ]
                    ]
                ]
            ]);
            
            // Response should either reject the update or sanitize the foreign ID
            // This is a low-level test of Livewire's security handling
            $this->assertTrue(
                $response->status() >= 400 ||
                !str_contains($response->content(), (string)$this->accountB->id),
                'Livewire allowed direct state manipulation with foreign ID'
            );
        }
    }
}