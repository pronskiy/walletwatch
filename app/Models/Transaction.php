<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class Transaction extends Model
{
    /** @use HasFactory<\Database\Factories\TransactionFactory> */
    use HasFactory;

    protected $fillable = [
        'amount',
        'description',
        'date',
        'type',
        'account_id',
        'category_id',
        'user_id',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'date' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }

    /**
     * Scope to filter transactions for a specific user
     */
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to filter transactions for a specific account
     */
    public function scopeForAccount(Builder $query, int $accountId): Builder
    {
        return $query->where('account_id', $accountId);
    }

    /**
     * Scope to filter transactions by date range
     */
    public function scopeInDateRange(Builder $query, string $startDate, string $endDate): Builder
    {
        return $query->whereBetween('date', [$startDate, $endDate]);
    }

    /**
     * Scope to get transactions for current month
     */
    public function scopeCurrentMonth(Builder $query): Builder
    {
        return $query->whereBetween('date', [
            now()->startOfMonth()->toDateString(),
            now()->endOfMonth()->toDateString()
        ]);
    }

    /**
     * Scope to get sum of transaction amounts using database aggregation
     */
    public function scopeSumAmount(Builder $query): float
    {
        return (float) $query->sum('amount') ?? 0.0;
    }

    /**
     * Get monthly summary for a user using database aggregation
     */
    public static function getMonthlySummary(int $userId, string $year, string $month): array
    {
        $startDate = "{$year}-{$month}-01";
        $endDate = date('Y-m-t', strtotime($startDate));

        return self::forUser($userId)
            ->inDateRange($startDate, $endDate)
            ->selectRaw('
                SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END) as total_income,
                SUM(CASE WHEN amount < 0 THEN ABS(amount) ELSE 0 END) as total_expenses,
                SUM(amount) as net_amount,
                COUNT(*) as transaction_count
            ')
            ->first()
            ->toArray();
    }

    /**
     * Get account balances using database aggregation
     */
    public static function getAccountBalances(int $userId): array
    {
        return self::forUser($userId)
            ->selectRaw('account_id, SUM(amount) as transaction_sum')
            ->groupBy('account_id')
            ->pluck('transaction_sum', 'account_id')
            ->toArray();
    }
}
