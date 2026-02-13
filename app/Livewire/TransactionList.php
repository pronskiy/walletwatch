<?php

namespace App\Livewire;

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use Livewire\Component;
use Livewire\WithPagination;

class TransactionList extends Component
{
    use WithPagination;

    // Filter properties
    public $search = '';
    public $selectedAccount = '';
    public $selectedCategory = '';
    public $startDate = '';
    public $endDate = '';
    public $minAmount = '';
    public $maxAmount = '';
    public $sortBy = 'date';
    public $sortDirection = 'desc';

    public $perPage = 15;

    protected $queryString = [
        'search' => ['except' => ''],
        'selectedAccount' => ['except' => ''],
        'selectedCategory' => ['except' => ''],
        'startDate' => ['except' => ''],
        'endDate' => ['except' => ''],
        'minAmount' => ['except' => ''],
        'maxAmount' => ['except' => ''],
        'sortBy' => ['except' => 'date'],
        'sortDirection' => ['except' => 'desc'],
    ];

    public function mount()
    {
        // Set default date range to current month
        $this->startDate = now()->startOfMonth()->format('Y-m-d');
        $this->endDate = now()->endOfMonth()->format('Y-m-d');
    }

    public function updating($property)
    {
        // Reset pagination when filters change
        if (in_array($property, ['search', 'selectedAccount', 'selectedCategory', 'startDate', 'endDate', 'minAmount', 'maxAmount'])) {
            $this->resetPage();
        }
    }

    public function sortBy($column)
    {
        if ($this->sortBy === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = 'asc';
        }
        $this->resetPage();
    }

    public function clearFilters()
    {
        $this->search = '';
        $this->selectedAccount = '';
        $this->selectedCategory = '';
        $this->startDate = now()->startOfMonth()->format('Y-m-d');
        $this->endDate = now()->endOfMonth()->format('Y-m-d');
        $this->minAmount = '';
        $this->maxAmount = '';
        $this->resetPage();
    }

    public function getTransactionsProperty()
    {
        $query = Transaction::query()
            ->where('user_id', auth()->id())
            ->with(['account', 'category'])
            ->search($this->search)
            ->dateRange($this->startDate, $this->endDate)
            ->byAccount($this->selectedAccount)
            ->byCategory($this->selectedCategory)
            ->amountRange(
                $this->minAmount !== '' ? floatval($this->minAmount) : null,
                $this->maxAmount !== '' ? floatval($this->maxAmount) : null
            )
            ->orderBy($this->sortBy, $this->sortDirection);

        return $query->paginate($this->perPage);
    }

    public function getFilteredStatsProperty()
    {
        $query = Transaction::query()
            ->where('user_id', auth()->id())
            ->search($this->search)
            ->dateRange($this->startDate, $this->endDate)
            ->byAccount($this->selectedAccount)
            ->byCategory($this->selectedCategory)
            ->amountRange(
                $this->minAmount !== '' ? floatval($this->minAmount) : null,
                $this->maxAmount !== '' ? floatval($this->maxAmount) : null
            );

        $totalIncome = $query->clone()->where('type', 'income')->sum('amount');
        $totalExpenses = abs($query->clone()->where('type', 'expense')->sum('amount'));
        $netAmount = $totalIncome - $totalExpenses;

        return [
            'total_transactions' => $query->count(),
            'total_income' => $totalIncome,
            'total_expenses' => $totalExpenses,
            'net_amount' => $netAmount,
        ];
    }

    public function render()
    {
        $accounts = Account::where('user_id', auth()->id())->get();
        $categories = Category::all();

        return view('livewire.transaction-list', [
            'transactions' => $this->transactions,
            'accounts' => $accounts,
            'categories' => $categories,
            'stats' => $this->filteredStats,
        ]);
    }
}