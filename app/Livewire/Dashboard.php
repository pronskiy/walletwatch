<?php

namespace App\Livewire;

use App\Models\Account;
use App\Models\Transaction;
use Livewire\Component;

class Dashboard extends Component
{
    public function render()
    {
        $user = auth()->user();
        
        // Get user's accounts with calculated balances
        $accounts = Account::where('user_id', $user->id)
            ->with('transactions')
            ->get()
            ->map(function ($account) {
                $transactionSum = $account->transactions->sum('amount');
                $account->current_balance = $account->initial_balance + $transactionSum;
                return $account;
            });

        // Total balance across all accounts
        $totalBalance = $accounts->sum('current_balance');

        // Last 10 transactions
        $recentTransactions = Transaction::where('user_id', $user->id)
            ->with(['account', 'category'])
            ->orderBy('date', 'desc')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return view('livewire.dashboard', compact('accounts', 'totalBalance', 'recentTransactions'));
    }
}
