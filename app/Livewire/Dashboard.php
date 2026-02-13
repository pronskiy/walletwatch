<?php

namespace App\Livewire;

use App\Models\Transaction;
use App\Services\DashboardService;
use Livewire\Component;

class Dashboard extends Component
{
    private DashboardService $dashboardService;

    public function boot(DashboardService $dashboardService)
    {
        $this->dashboardService = $dashboardService;
    }

    public function render()
    {
        $user = auth()->user();
        
        // Use efficient service to get accounts with balances
        $accounts = $this->dashboardService->getAccountsWithBalances($user->id);

        // Total balance across all accounts
        $totalBalance = $accounts->sum('current_balance');

        // Last 10 transactions with minimal eager loading
        $recentTransactions = Transaction::forUser($user->id)
            ->with(['account:id,name', 'category:id,name,icon'])
            ->orderBy('date', 'desc')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        // Monthly summary for current month
        $monthlySummary = $this->dashboardService->getMonthlySummary(
            $user->id,
            now()->year,
            now()->format('m')
        );

        return view('livewire.dashboard', compact(
            'accounts',
            'totalBalance', 
            'recentTransactions',
            'monthlySummary'
        ));
    }
}
