<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Transaction extends Model
{
    protected $fillable = [
        'customer_name',
        'customer_phone',
        'vehicle_name',
        'service_description',
        'service_fee',
    ];

    protected function casts(): array
    {
        return [
            'transaction_date' => 'date',
            'service_fee' => 'decimal:2',
            'total_item_cost' => 'decimal:2',
            'total_income' => 'decimal:2',
            'gross_profit' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Transaction $transaction): void {
            $transaction->transaction_date ??= today();
            $transaction->transaction_number ??= static::generateTransactionNumber($transaction->transaction_date);
        });

        static::saving(function (Transaction $transaction): void {
            $itemCost = $transaction->exists
                ? (float) $transaction->transactionItems()->sum('subtotal')
                : (float) ($transaction->total_item_cost ?? 0);

            $transaction->total_item_cost = $itemCost;
            $transaction->total_income = (float) ($transaction->service_fee ?? 0);
            $transaction->gross_profit = $transaction->total_income - $transaction->total_item_cost;
        });
    }

    public function transactionItems(): HasMany
    {
        return $this->hasMany(TransactionItem::class);
    }

    public function recalculateTotals(): void
    {
        $this->total_item_cost = (float) $this->transactionItems()->sum('subtotal');
        $this->total_income = (float) ($this->service_fee ?? 0);
        $this->gross_profit = $this->total_income - $this->total_item_cost;
        $this->saveQuietly();
    }

    public static function generateTransactionNumber(Carbon | string $date): string
    {
        $date = Carbon::parse($date);
        $prefix = 'TRX-' . $date->format('Ymd');
        $sequence = static::query()
            ->whereDate('transaction_date', $date)
            ->where('transaction_number', 'like', "{$prefix}-%")
            ->count() + 1;

        do {
            $number = "{$prefix}-" . str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
            $sequence++;
        } while (static::query()->where('transaction_number', $number)->exists());

        return $number;
    }

    public function scopeInYear(Builder $query, int $year): Builder
    {
        return $query->whereYear('transaction_date', $year);
    }
}
