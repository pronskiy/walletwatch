<?php

namespace Tests\Feature;

use App\Livewire\TransactionForm;
use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TransactionCategoryValidationTest extends TestCase
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
    public function it_validates_that_category_exists()
    {
        Livewire::actingAs($this->user)
            ->test(TransactionForm::class)
            ->set('amount', '100.00')
            ->set('description', 'Test transaction')
            ->set('type', 'expense')
            ->set('account_id', $this->account->id)
            ->set('category_id', '9999') // Non-existent category
            ->set('date', now()->format('Y-m-d'))
            ->call('save')
            ->assertHasErrors(['category_id' => 'exists']);
    }

    /** @test */
    public function it_validates_that_category_type_matches_transaction_type()
    {
        Livewire::actingAs($this->user)
            ->test(TransactionForm::class)
            ->set('amount', '100.00')
            ->set('description', 'Test transaction')
            ->set('type', 'expense')
            ->set('account_id', $this->account->id)
            ->set('category_id', $this->incomeCategory->id) // Income category for expense transaction
            ->set('date', now()->format('Y-m-d'))
            ->call('save')
            ->assertHasErrors('category_id');
    }

    /** @test */
    public function it_allows_expense_category_for_expense_transaction()
    {
        Livewire::actingAs($this->user)
            ->test(TransactionForm::class)
            ->set('amount', '100.00')
            ->set('description', 'Test transaction')
            ->set('type', 'expense')
            ->set('account_id', $this->account->id)
            ->set('category_id', $this->expenseCategory->id)
            ->set('date', now()->format('Y-m-d'))
            ->call('save')
            ->assertHasNoErrors('category_id');
    }

    /** @test */
    public function it_allows_income_category_for_income_transaction()
    {
        Livewire::actingAs($this->user)
            ->test(TransactionForm::class)
            ->set('amount', '1000.00')
            ->set('description', 'Salary')
            ->set('type', 'income')
            ->set('account_id', $this->account->id)
            ->set('category_id', $this->incomeCategory->id)
            ->set('date', now()->format('Y-m-d'))
            ->call('save')
            ->assertHasNoErrors('category_id');
    }

    /** @test */
    public function it_requires_category_id()
    {
        Livewire::actingAs($this->user)
            ->test(TransactionForm::class)
            ->set('amount', '100.00')
            ->set('description', 'Test transaction')
            ->set('type', 'expense')
            ->set('account_id', $this->account->id)
            ->set('category_id', '') // Empty category
            ->set('date', now()->format('Y-m-d'))
            ->call('save')
            ->assertHasErrors(['category_id' => 'required']);
    }

    /** @test */
    public function it_creates_transaction_with_correct_category_when_valid()
    {
        $this->assertDatabaseCount('transactions', 0);

        Livewire::actingAs($this->user)
            ->test(TransactionForm::class)
            ->set('amount', '100.00')
            ->set('description', 'Grocery shopping')
            ->set('type', 'expense')
            ->set('account_id', $this->account->id)
            ->set('category_id', $this->expenseCategory->id)
            ->set('date', now()->format('Y-m-d'))
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseCount('transactions', 1);
        
        $transaction = Transaction::first();
        $this->assertEquals($this->expenseCategory->id, $transaction->category_id);
        $this->assertEquals('Grocery shopping', $transaction->description);
        $this->assertEquals('expense', $transaction->type);
    }

    /** @test */
    public function it_resets_category_when_transaction_type_changes()
    {
        Livewire::actingAs($this->user)
            ->test(TransactionForm::class)
            ->set('category_id', $this->expenseCategory->id)
            ->assertSet('category_id', $this->expenseCategory->id)
            ->set('type', 'income')
            ->assertSet('category_id', '');
    }
}