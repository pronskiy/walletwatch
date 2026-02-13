<?php

namespace App\Livewire;

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use Livewire\Component;

class TransactionForm extends Component
{
    public $amount = '';
    public $description = '';
    public $date = '';
    public $type = 'expense';
    public $account_id = '';
    public $category_id = '';
    public $notes = '';

    public function mount()
    {
        $this->date = now()->format('Y-m-d');
    }

    #[\Livewire\Attributes\On('categorySelected')]
    public function handleCategorySelected($categoryId)
    {
        $this->category_id = $categoryId;
    }

    public function updatedType($value)
    {
        // Reset category when type changes since categories are type-specific
        $this->category_id = '';
    }

    protected function rules()
    {
        return [
            'amount' => 'required|numeric|min:0.01',
            'description' => 'required|string|max:255',
            'date' => 'required|date',
            'type' => 'required|in:income,expense,transfer',
            'account_id' => 'required|exists:accounts,id',
            'category_id' => [
                'required',
                'exists:categories,id',
                function ($attribute, $value, $fail) {
                    if ($value) {
                        $category = Category::find($value);
                        if ($category && $category->type !== $this->type) {
                            $fail('The selected category does not match the transaction type.');
                        }
                    }
                },
            ],
            'notes' => 'nullable|string|max:1000',
        ];
    }

    protected function messages()
    {
        return [
            'amount.required' => 'Please enter an amount.',
            'amount.numeric' => 'Amount must be a valid number.',
            'amount.min' => 'Amount must be greater than 0.',
            'description.required' => 'Please enter a description.',
            'account_id.required' => 'Please select an account.',
            'category_id.required' => 'Please select a category.',
            'category_id.exists' => 'The selected category is invalid.',
        ];
    }

    public function save()
    {
        $this->validate();

        $amount = $this->amount;
        if ($this->type === 'expense') {
            $amount = -abs($amount);
        }

        Transaction::create([
            'amount' => $amount,
            'description' => $this->description,
            'date' => $this->date,
            'type' => $this->type,
            'account_id' => $this->account_id,
            'category_id' => $this->category_id,
            'user_id' => auth()->id(),
            'notes' => $this->notes,
        ]);

        session()->flash('message', 'Transaction created successfully!');

        // Reset form
        $this->reset();
        $this->date = now()->format('Y-m-d');

        $this->dispatch('transactionCreated');
    }

    public function render()
    {
        $accounts = Account::where('user_id', auth()->id())
            ->orderBy('name')
            ->get();

        return view('livewire.transaction-form', compact('accounts'));
    }
}