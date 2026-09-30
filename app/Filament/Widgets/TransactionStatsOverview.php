<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\HasYearlyDashboardData;
use App\Models\Cashout;
use App\Models\Item;
use App\Models\Transaction;
use App\Models\TransactionCommission;
use App\Support\Money;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\HtmlString;

class TransactionStatsOverview extends BaseWidget
{
    use HasYearlyDashboardData;
    use InteractsWithPageFilters;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $year = $this->getSelectedYear();
        $totalItems = Item::query()->count();
        $totalTransactions = Transaction::query()
            ->where('status', Transaction::STATUS_LOCKED)
            ->whereYear('transaction_date', $year)
            ->count();
        $totalCashout = (float) Cashout::query()
            ->whereYear('cashout_date', $year)
            ->sum('amount');
        $totalIncome = (float) Transaction::query()
            ->where('status', Transaction::STATUS_LOCKED)
            ->whereYear('transaction_date', $year)
            ->sum('total_income');
        $totalItemCost = (float) Transaction::query()
            ->where('status', Transaction::STATUS_LOCKED)
            ->whereYear('transaction_date', $year)
            ->sum('total_item_cost');
        $lockedCommissions = TransactionCommission::query()
            ->whereHas('transaction', fn ($query) => $query
                ->where('status', Transaction::STATUS_LOCKED)
                ->whereYear('transaction_date', $year));
        $totalCommission = (float) (clone $lockedCommissions)->sum('amount');
        $totalProfit = $totalIncome - $totalItemCost - $totalCommission - $totalCashout;

        $stats = [
            Stat::make('Total Barang', number_format($totalItems, 0, ',', '.'))
                ->description('Semua data barang'),
            Stat::make('Total Transaction', number_format($totalTransactions, 0, ',', '.'))
                ->description("Terkunci • Tahun {$year}"),
            Stat::make('Total Cashout', Money::rupiah($totalCashout))
                ->description("Tahun {$year}"),
            Stat::make('Total Pendapatan', Money::rupiah($totalIncome))
                ->description("Transaksi terkunci • Tahun {$year}"),
            Stat::make('Total Profit', Money::rupiah($totalProfit))
                ->description(new HtmlString(
                    "Setelah modal, komisi & cashout • Tahun {$year}"
                )),
        ];

        return $stats;
    }
}
