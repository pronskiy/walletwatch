<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Collection;

class DashboardService
{
    public function getAccountsWithBalances($userId): Collection
    {
        return Account::where('user_id', $userId)
            ->withSum('transactions', 'amount')
            ->get()
            ->map(function ($account) {
                $account->current_balance = $account->transactions_sum_amount ?? 0;
                return $account;
            });
    }

    public function getMonthlySummary($userId, $year, $month): array
    {
        $startOfMonth = now()->setYear($year)->setMonth($month)->startOfMonth();
        $endOfMonth = now()->setYear($year)->setMonth($month)->endOfMonth();

        $transactions = Transaction::where('user_id', $userId)
            ->whereBetween('date', [$startOfMonth, $endOfMonth])
            ->get();

        $income = $transactions->where('type', 'income')->sum('amount');
        $expenses = abs($transactions->where('type', 'expense')->sum('amount'));

        return [
            'income' => $income,
            'expenses' => $expenses,
            'net' => $income - $expenses,
            'transaction_count' => $transactions->count(),
        ];
    }

    public function invalidateUserCache($userId): void
    {
        // For now, this is a placeholder method
        // In a real implementation, this would clear cached dashboard data for the user
        // For example: Cache::forget("dashboard_data_{$userId}");
    }
}