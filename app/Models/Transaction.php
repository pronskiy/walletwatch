<?php

namespace App\Models;

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

    // Query scopes for filtering
    public function scopeSearch($query, $search)
    {
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('description', 'like', '%' . $search . '%')
                  ->orWhere('notes', 'like', '%' . $search . '%');
            });
        }
        return $query;
    }

    public function scopeDateRange($query, $startDate, $endDate)
    {
        if ($startDate) {
            $query->where('date', '>=', $startDate);
        }
        if ($endDate) {
            $query->where('date', '<=', $endDate);
        }
        return $query;
    }

    public function scopeByCategory($query, $categoryId)
    {
        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }
        return $query;
    }

    public function scopeByAccount($query, $accountId)
    {
        if ($accountId) {
            $query->where('account_id', $accountId);
        }
        return $query;
    }

    public function scopeAmountRange($query, $minAmount, $maxAmount)
    {
        if ($minAmount !== null) {
            $query->where('amount', '>=', $minAmount);
        }
        if ($maxAmount !== null) {
            $query->where('amount', '<=', $maxAmount);
        }
        return $query;
    }
}
