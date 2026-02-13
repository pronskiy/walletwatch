<?php

namespace App\Livewire;

use App\Models\Budget;
use App\Models\Category;
use App\Models\Transaction;
use Carbon\Carbon;
use Livewire\Component;

class BudgetTracker extends Component
{
    public function render()
    {
        $user = auth()->user();
        $currentMonth = Carbon::now()->startOfMonth();
        $nextMonth = Carbon::now()->addMonth()->startOfMonth();

        // Get budgets for current month
        $budgets = Budget::where('user_id', $user->id)
            ->where('period', 'monthly')
            ->where('start_date', '<=', $currentMonth)
            ->with('category')
            ->get()
            ->keyBy('category_id');

        // Get all categories that have budgets
        $categories = Category::whereIn('id', $budgets->keys())->get();

        // Calculate spending for each category in current month
        $budgetData = [];
        
        foreach ($categories as $category) {
            $budget = $budgets[$category->id];
            
            // Get total spending for this category in current month
            $spending = Transaction::where('user_id', $user->id)
                ->where('category_id', $category->id)
                ->where('type', 'expense')
                ->where('date', '>=', $currentMonth)
                ->where('date', '<', $nextMonth)
                ->sum('amount');

            // Convert to positive for display (expenses are typically negative)
            $spending = abs($spending);
            
            $remaining = $budget->amount - $spending;
            $percentage = $budget->amount > 0 ? ($spending / $budget->amount) * 100 : 0;
            $isOverBudget = $spending > $budget->amount;

            $budgetData[] = [
                'category' => $category,
                'budget' => $budget,
                'spent' => $spending,
                'remaining' => $remaining,
                'percentage' => min($percentage, 100), // Cap at 100% for display
                'isOverBudget' => $isOverBudget,
            ];
        }

        // Sort by percentage spent (highest first)
        usort($budgetData, function ($a, $b) {
            return $b['percentage'] <=> $a['percentage'];
        });

        return view('livewire.budget-tracker', [
            'budgetData' => $budgetData,
            'currentMonth' => $currentMonth,
        ]);
    }
}