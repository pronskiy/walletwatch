<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Transaction;
use Illuminate\Support\Facades\Cache;

class DashboardService
{
    /**
     * Get monthly summary with caching
     */
    public function getMonthlySummary(int $userId, string $year, string $month): array
    {
        $cacheKey = "monthly_summary_{$userId}_{$year}_{$month}";
        
        return Cache::remember($cacheKey, 3600, function () use ($userId, $year, $month) {
            return Transaction::getMonthlySummary($userId, $year, $month);
        });
    }

    /**
     * Get account balances with efficient queries and caching
     */
    public function getAccountsWithBalances(int $userId): \Illuminate\Database\Eloquent\Collection
    {
        $cacheKey = "account_balances_{$userId}";
        
        // Get account balances from database aggregation
        $balances = Cache::remember($cacheKey, 1800, function () use ($userId) {
            return Transaction::getAccountBalances($userId);
        });

        // Get accounts with only needed fields
        $accounts = Account::where('user_id', $userId)
            ->select('id', 'name', 'type', 'currency', 'initial_balance')
            ->get();

        // Calculate current balances efficiently
        $accounts->each(function ($account) use ($balances) {
            $transactionSum = $balances[$account->id] ?? 0;
            $account->current_balance = $account->initial_balance + $transactionSum;
        });

        return $accounts;
    }

    /**
     * Invalidate cache when transactions are modified
     */
    public function invalidateUserCache(int $userId): void
    {
        $currentYear = now()->year;
        $currentMonth = now()->format('m');
        
        // Clear account balances cache
        Cache::forget("account_balances_{$userId}");
        
        // Clear monthly summary cache for current and previous month
        Cache::forget("monthly_summary_{$userId}_{$currentYear}_{$currentMonth}");
        
        $previousMonth = now()->subMonth();
        Cache::forget("monthly_summary_{$userId}_{$previousMonth->year}_{$previousMonth->format('m')}");
    }
}