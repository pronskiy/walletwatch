<?php

namespace App\Livewire;

use App\Models\Category;
use Livewire\Component;

class CategoryDropdown extends Component
{
    public $selectedCategory = '';
    public $transactionType = 'expense';
    public $required = true;
    public $placeholder = 'Select a category...';

    public function mount($transactionType = 'expense', $selectedCategory = '', $required = true, $placeholder = 'Select a category...')
    {
        $this->transactionType = $transactionType;
        $this->selectedCategory = $selectedCategory;
        $this->required = $required;
        $this->placeholder = $placeholder;
    }

    public function updatedSelectedCategory($value)
    {
        $this->dispatch('categorySelected', $value);
    }

    public function updatedTransactionType($value)
    {
        // Reset selected category when transaction type changes
        $this->selectedCategory = '';
        $this->dispatch('categorySelected', '');
    }

    public function render()
    {
        $categories = Category::where('type', $this->transactionType)
            ->where('is_default', true)
            ->orderBy('name')
            ->get();

        return view('livewire.category-dropdown', compact('categories'));
    }
}