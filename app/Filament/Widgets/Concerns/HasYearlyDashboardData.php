<?php

namespace App\Filament\Widgets\Concerns;

use App\Models\Cashout;
use App\Models\Transaction;

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
    protected function getMonthlyGrossProfitTotals(): array
    {
        $buckets = array_fill(1, 12, 0.0);

        Transaction::query()
            ->whereYear('transaction_date', $this->getSelectedYear())
            ->get(['transaction_date', 'gross_profit'])
            ->each(function (Transaction $transaction) use (&$buckets): void {
                $buckets[$transaction->transaction_date->month] += (float) $transaction->gross_profit;
            });

        return array_values($buckets);
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
    protected function getMonthlyNetProfitTotals(): array
    {
        $grossProfit = $this->getMonthlyGrossProfitTotals();
        $cashouts = $this->getMonthlyCashoutTotals();

        return collect($grossProfit)
            ->map(fn (float $amount, int $index): float => $amount - $cashouts[$index])
            ->all();
    }
}
