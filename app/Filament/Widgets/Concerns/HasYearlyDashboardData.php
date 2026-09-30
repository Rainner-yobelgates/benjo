<?php

namespace App\Filament\Widgets\Concerns;

use App\Models\Cashout;
use App\Models\Transaction;
use App\Models\TransactionCommission;

trait HasYearlyDashboardData
{
    protected function getSelectedYear(): int
    {
        return (int) ($this->pageFilters['year'] ?? now()->year);
    }

    /**
     * @return array<int, string>
     */
    protected function getMonthLabels(): array
    {
        return ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    }

    /**
     * @return array<int, int>
     */
    protected function getMonthlyTransactionCounts(): array
    {
        $buckets = array_fill(1, 12, 0);

        Transaction::query()
            ->where('status', Transaction::STATUS_LOCKED)
            ->whereYear('transaction_date', $this->getSelectedYear())
            ->get(['transaction_date'])
            ->each(function (Transaction $transaction) use (&$buckets): void {
                $buckets[$transaction->transaction_date->month]++;
            });

        return array_values($buckets);
    }

    /**
     * @return array<int, float>
     */
    protected function getMonthlyIncomeTotals(): array
    {
        $buckets = array_fill(1, 12, 0.0);

        Transaction::query()
            ->where('status', Transaction::STATUS_LOCKED)
            ->whereYear('transaction_date', $this->getSelectedYear())
            ->get(['transaction_date', 'total_income'])
            ->each(function (Transaction $transaction) use (&$buckets): void {
                $buckets[$transaction->transaction_date->month] += (float) $transaction->total_income;
            });

        return array_values($buckets);
    }

    /**
     * Profit setelah dikurangi modal barang, komisi peserta terkunci, dan
     * cashout pada bulan yang sama.
     *
     * @return array<int, float>
     */
    protected function getMonthlyProfitTotals(): array
    {
        $buckets = array_fill(1, 12, 0.0);

        Transaction::query()
            ->where('status', Transaction::STATUS_LOCKED)
            ->whereYear('transaction_date', $this->getSelectedYear())
            ->with('commissions:id,transaction_id,amount')
            ->get(['id', 'transaction_date', 'total_income', 'total_item_cost', 'status'])
            ->each(function (Transaction $transaction) use (&$buckets): void {
                $commission = (float) $transaction->commissions->sum('amount');
                $profit = (float) $transaction->total_income
                    - (float) $transaction->total_item_cost
                    - $commission;

                $buckets[$transaction->transaction_date->month] += $profit;
            });

        $cashouts = $this->getMonthlyCashoutTotals();

        return collect(array_values($buckets))
            ->map(fn (float $amount, int $index): float => $amount - $cashouts[$index])
            ->all();
    }

    /**
     * @return array<int, float>
     */
    protected function getMonthlyCashoutTotals(): array
    {
        $buckets = array_fill(1, 12, 0.0);

        Cashout::query()
            ->whereYear('cashout_date', $this->getSelectedYear())
            ->get(['cashout_date', 'amount'])
            ->each(function (Cashout $cashout) use (&$buckets): void {
                $buckets[$cashout->cashout_date->month] += (float) $cashout->amount;
            });

        return array_values($buckets);
    }

    /**
     * @return array<int, float>
     */
    protected function getMonthlyLockedCommissionTotals(): array
    {
        $buckets = array_fill(1, 12, 0.0);

        TransactionCommission::query()
            ->with('transaction:id,transaction_date')
            ->whereHas('transaction', fn ($query) => $query
                ->where('status', Transaction::STATUS_LOCKED)
                ->whereYear('transaction_date', $this->getSelectedYear()))
            ->get(['transaction_id', 'amount'])
            ->each(function (TransactionCommission $commission) use (&$buckets): void {
                $buckets[$commission->transaction->transaction_date->month] += (float) $commission->amount;
            });

        return array_values($buckets);
    }
}
