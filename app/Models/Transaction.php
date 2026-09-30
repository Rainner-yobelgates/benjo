<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class Transaction extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_LOCKED = 'locked';

    protected $fillable = [
        'customer_name',
        'customer_phone',
        'vehicle_name',
        'service_description',
        'service_fee',
        'payment_method',
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
            $transaction->status ??= self::STATUS_DRAFT;
        });

        static::saving(function (Transaction $transaction): void {
            // Barang (tanpa price_list_id) adalah pengeluaran/modal, layanan
            // daftar harga (dengan price_list_id) adalah pemasukan selain
            // biaya servis.
            $itemCost = $transaction->exists
                ? (float) $transaction->transactionItems()->whereNull('price_list_id')->sum('subtotal')
                : (float) ($transaction->total_item_cost ?? 0);

            $serviceIncome = $transaction->exists
                ? (float) $transaction->transactionItems()->whereNotNull('price_list_id')->sum('subtotal')
                : 0.0;

            $transaction->total_item_cost = $itemCost;
            $transaction->total_income = (float) ($transaction->service_fee ?? 0) + $serviceIncome;
            $transaction->gross_profit = $transaction->total_income
                - $transaction->total_item_cost
                - (float) ($transaction->exists ? $transaction->commissions()->sum('amount') : 0);
        });

        static::saved(function (Transaction $transaction): void {
            if ($transaction->isDraft()) {
                $transaction->syncDraftCommissionSnapshots();
            }
        });
    }

    public function transactionItems(): HasMany
    {
        return $this->hasMany(TransactionItem::class);
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(TransactionCommission::class);
    }

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'transaction_participants')
            ->withTimestamps();
    }

    public function recalculateTotals(): void
    {
        $items = $this->transactionItems();

        $this->total_item_cost = (float) (clone $items)->whereNull('price_list_id')->sum('subtotal');
        $this->total_income = (float) ($this->service_fee ?? 0)
            + (float) (clone $items)->whereNotNull('price_list_id')->sum('subtotal');
        $this->saveQuietly();

        $this->syncDraftCommissionSnapshots();
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isLocked(): bool
    {
        return $this->status === self::STATUS_LOCKED;
    }

    /**
     * Finalize the current totals and commission snapshots as immutable
     * transaction history.
     */
    public function lock(array $customPercents = []): void
    {
        if ($this->isLocked()) {
            return;
        }

        DB::transaction(function () use ($customPercents): void {
            $this->refresh();
            $this->applyCustomCommissionPercents($customPercents);
            $this->recalculateTotals();
            $this->syncDraftCommissionSnapshots();
            $this->status = self::STATUS_LOCKED;
            $this->saveQuietly();
        });
    }

    /**
     * Reopen a transaction for corrections. Its commission snapshots will
     * once again follow the current eligible participants and percentages.
     */
    public function unlock(): void
    {
        if ($this->isDraft()) {
            return;
        }

        DB::transaction(function (): void {
            $this->status = self::STATUS_DRAFT;
            $this->saveQuietly();
            $this->recalculateTotals();
        });
    }

    /**
     * Synchronize selected participants while the transaction is still a
     * draft. Both their percentage and amount remain live until it is locked.
     *
     * @param  array<int, int|string>  $participantIds
     */
    public function syncCommissionParticipants(array $participantIds): void
    {
        if ($this->isLocked()) {
            return;
        }

        DB::transaction(function () use ($participantIds): void {
            $selectedIds = collect($participantIds)
                ->filter(fn (mixed $id): bool => filled($id))
                ->map(fn (mixed $id): int => (int) $id)
                ->unique()
                ->values();
            $customUserIds = $this->commissions()->whereNotNull('custom_percent')->pluck('user_id');
            $eligibleUsers = User::query()
                ->whereIn('id', $selectedIds)
                ->where(fn (Builder $query) => $query
                    ->where('commission_percent', '>', 0)
                    ->orWhereIn('id', $customUserIds))
                ->get();
            $finalIds = $eligibleUsers->pluck('id');
            $existingIds = $this->participants()->pluck('users.id');

            $removedIds = $existingIds->diff($finalIds);

            if ($removedIds->isNotEmpty()) {
                $this->participants()->detach($removedIds->all());
                $this->commissions()->whereIn('user_id', $removedIds)->delete();
            }

            $this->participants()->syncWithoutDetaching($finalIds->all());
            $this->syncDraftCommissionSnapshots();
        });
    }

    /**
     * Keep draft commissions aligned with the transaction's latest gross
     * billing amount and each participant's latest commission percentage.
     */
    public function syncDraftCommissionSnapshots(): void
    {
        if (! $this->isDraft()) {
            return;
        }

        $participantIds = $this->participants()->pluck('users.id');

        if ($participantIds->isEmpty()) {
            $this->refreshProfitAfterCommission();

            return;
        }

        $existingCommissions = $this->commissions()->get()->keyBy('user_id');
        $eligibleUsers = User::query()->whereIn('id', $participantIds)->get()
            ->filter(fn (User $user): bool => $existingCommissions->get($user->id)?->custom_percent !== null || (float) $user->commission_percent > 0);
        $eligibleIds = $eligibleUsers->pluck('id');
        $ineligibleIds = $participantIds->diff($eligibleIds);

        if ($ineligibleIds->isNotEmpty()) {
            $this->participants()->detach($ineligibleIds->all());
            $this->commissions()->whereIn('user_id', $ineligibleIds)->delete();
        }

        foreach ($eligibleUsers as $user) {
            $customPercent = $existingCommissions->get($user->id)?->custom_percent;
            $percent = $customPercent ?? $user->commission_percent;
            $this->commissions()->updateOrCreate([
                'user_id' => $user->id,
            ], [
                'custom_percent' => $customPercent,
                'percent' => $percent,
                'amount' => round((float) $this->total_income * (float) $percent / 100, 2),
            ]);
        }

        $this->refreshProfitAfterCommission();
    }

    /** @param array<int, int|float|string|null> $customPercents */
    public function applyCustomCommissionPercents(array $customPercents): void
    {
        foreach ($customPercents as $userId => $percent) {
            if (! $this->participants()->whereKey($userId)->exists()) {
                continue;
            }
            $value = filled($percent) ? max(0, min(100, (float) $percent)) : null;
            $this->commissions()->updateOrCreate(['user_id' => $userId], ['custom_percent' => $value]);
        }
    }

    private function refreshProfitAfterCommission(): void
    {
        $this->gross_profit = (float) $this->total_income
            - (float) $this->total_item_cost
            - (float) $this->commissions()->sum('amount');

        $this->saveQuietly();
    }

    public static function generateTransactionNumber(Carbon|string $date): string
    {
        $date = Carbon::parse($date);
        $prefix = 'TRX-'.$date->format('Ymd');
        $sequence = static::query()
            ->whereDate('transaction_date', $date)
            ->where('transaction_number', 'like', "{$prefix}-%")
            ->count() + 1;

        do {
            $number = "{$prefix}-".str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
            $sequence++;
        } while (static::query()->where('transaction_number', $number)->exists());

        return $number;
    }

    public function scopeInYear(Builder $query, int $year): Builder
    {
        return $query->whereYear('transaction_date', $year);
    }
}
