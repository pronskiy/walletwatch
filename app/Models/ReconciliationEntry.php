<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReconciliationEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'account_id',
        'user_id',
        'bank_balance',
        'calculated_balance',
        'adjustment_amount',
        'reconciled_at',
        'notes',
        'adjustment_transaction_id',
    ];

    protected $casts = [
        'bank_balance' => 'decimal:2',
        'calculated_balance' => 'decimal:2',
        'adjustment_amount' => 'decimal:2',
        'reconciled_at' => 'timestamp',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function adjustmentTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'adjustment_transaction_id');
    }

    /**
     * Create a new reconciliation entry
     */
    public static function create(
        int $accountId,
        int $userId,
        float $bankBalance,
        float $calculatedBalance,
        string $notes = null
    ): self {
        $adjustmentAmount = $bankBalance - $calculatedBalance;

        return parent::create([
            'account_id' => $accountId,
            'user_id' => $userId,
            'bank_balance' => $bankBalance,
            'calculated_balance' => $calculatedBalance,
            'adjustment_amount' => $adjustmentAmount,
            'reconciled_at' => now(),
            'notes' => $notes,
        ]);
    }

    /**
     * Check if this reconciliation requires an adjustment
     */
    public function needsAdjustment(): bool
    {
        return abs($this->adjustment_amount) > 0.01; // Allow for small rounding differences
    }

    /**
     * Get the adjustment type
     */
    public function getAdjustmentType(): string
    {
        if ($this->adjustment_amount > 0) {
            return 'positive'; // Bank balance is higher
        } elseif ($this->adjustment_amount < 0) {
            return 'negative'; // Bank balance is lower  
        } else {
            return 'none'; // Balanced
        }
    }

    /**
     * Get adjustment amount as absolute value
     */
    public function getAbsoluteAdjustmentAmount(): float
    {
        return abs($this->adjustment_amount);
    }
}
