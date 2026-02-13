<?php

namespace App\Livewire;

use App\Models\Budget;
use App\Models\Category;
use App\Models\Transaction;
use Carbon\Carbon;
use Livewire\Component;

class BudgetTracker extends Component
{
    public $month;
    public $year;

    public function mount($month = null, $year = null)
    {
        $this->month = $month ?? now()->month;
        $this->year = $year ?? now()->year;
    }

    public function updatedMonth()
    {
        $this->render();
    }

    public function updatedYear()
    {
        $this->render();
    }

    public function getBudgetData()
    {
        $userId = auth()->id();
        $startDate = Carbon::create($this->year, $this->month, 1)->startOfMonth();
        $endDate = Carbon::create($this->year, $this->month, 1)->endOfMonth();

        // Get all budgets for the user for this period
        $budgets = Budget::where('user_id', $userId)
            ->where('period', 'monthly')
            ->with(['category'])
            ->get();

        $budgetData = [];

        foreach ($budgets as $budget) {
            // Calculate spent amount for this category this month
            $spent = abs(Transaction::where('user_id', $userId)
                ->where('category_id', $budget->category_id)
                ->where('type', 'expense')
                ->whereBetween('date', [$startDate, $endDate])
                ->sum('amount'));

            $budgetAmount = $budget->amount;
            $remaining = $budgetAmount - $spent;
            $percentage = $budgetAmount > 0 ? ($spent / $budgetAmount) * 100 : 0;
            $isOverBudget = $spent > $budgetAmount;

            $budgetData[] = [
                'budget' => $budget,
                'category' => $budget->category,
                'budget_amount' => $budgetAmount,
                'spent' => $spent,
                'remaining' => $remaining,
                'percentage' => $percentage,
                'is_over_budget' => $isOverBudget,
                'over_amount' => $isOverBudget ? $spent - $budgetAmount : 0,
            ];
        }

        // Sort by over budget first, then by percentage spent
        usort($budgetData, function ($a, $b) {
            if ($a['is_over_budget'] && !$b['is_over_budget']) return -1;
            if (!$a['is_over_budget'] && $b['is_over_budget']) return 1;
            return $b['percentage'] <=> $a['percentage'];
        });

        return $budgetData;
    }

    public function getTotalBudgetSummary()
    {
        $budgetData = $this->getBudgetData();
        
        $totalBudget = array_sum(array_column($budgetData, 'budget_amount'));
        $totalSpent = array_sum(array_column($budgetData, 'spent'));
        $totalRemaining = $totalBudget - $totalSpent;
        $totalPercentage = $totalBudget > 0 ? ($totalSpent / $totalBudget) * 100 : 0;
        $overBudgetCount = count(array_filter($budgetData, fn($item) => $item['is_over_budget']));

        return [
            'total_budget' => $totalBudget,
            'total_spent' => $totalSpent,
            'total_remaining' => $totalRemaining,
            'total_percentage' => min($totalPercentage, 100),
            'over_budget_count' => $overBudgetCount,
        ];
    }

    public function render()
    {
        $budgetData = $this->getBudgetData();
        $totalSummary = $this->getTotalBudgetSummary();

        $monthName = Carbon::create($this->year, $this->month, 1)->format('F Y');

        return view('livewire.budget-tracker', compact('budgetData', 'totalSummary', 'monthName'));
    }
}