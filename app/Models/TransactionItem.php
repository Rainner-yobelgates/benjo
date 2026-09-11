<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransactionItem extends Model
{
    protected $fillable = [
        'item_id',
        'price_list_id',
        'item_name',
        'item_price',
        'quantity',
        'subtotal',
    ];

    protected function casts(): array
    {
        return [
            'item_price' => 'decimal:2',
            'subtotal' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (TransactionItem $transactionItem): void {
            if ($transactionItem->item_id && blank($transactionItem->item_name)) {
                $item = Item::query()->find($transactionItem->item_id);

                $transactionItem->item_name = $item?->name ?? $transactionItem->item_name;
                $transactionItem->item_price = $transactionItem->item_price ?: ($item?->price ?? 0);
            }

            if ($transactionItem->price_list_id && blank($transactionItem->item_name)) {
                $priceList = PriceList::query()->find($transactionItem->price_list_id);

                $transactionItem->item_name = $priceList?->name ?? $transactionItem->item_name;
                $transactionItem->item_price = $transactionItem->item_price ?: ($priceList?->price ?? 0);
            }

            $transactionItem->quantity = max(1, (int) ($transactionItem->quantity ?: 1));
            $transactionItem->subtotal = (float) $transactionItem->item_price * $transactionItem->quantity;
        });

        static::saved(fn (TransactionItem $transactionItem) => $transactionItem->transaction?->recalculateTotals());
        static::deleted(fn (TransactionItem $transactionItem) => $transactionItem->transaction?->recalculateTotals());
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function priceList(): BelongsTo
    {
        return $this->belongsTo(PriceList::class);
    }
}
