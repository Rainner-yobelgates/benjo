<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\HasYearlyDashboardData;
use App\Models\Cashout;
use App\Models\Item;
use App\Models\Transaction;
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

    protected int | string | array $columnSpan = 'full';

    protected function getStats(): array
    {
        $year = $this->getSelectedYear();
        $totalItems = Item::query()->count();
        $totalTransactions = Transaction::query()
            ->whereYear('transaction_date', $year)
            ->count();
        $totalCashout = (float) Cashout::query()
            ->whereYear('cashout_date', $year)
            ->sum('amount');
        $grossProfit = (float) Transaction::query()
            ->whereYear('transaction_date', $year)
            ->sum('gross_profit');
        $totalProfit = $grossProfit - $totalCashout;

        return [
            Stat::make('Total Barang', number_format($totalItems, 0, ',', '.'))
                ->description("Semua data barang"),
            Stat::make('Total Transaction', number_format($totalTransactions, 0, ',', '.'))
                ->description("Tahun {$year}"),
            Stat::make('Total Profit', Money::rupiah($totalProfit))
                ->description(new HtmlString(
                    "Tahun {$year}<br><span class=\"text-xs text-gray-500\">Pendapatan kotor " . e(Money::rupiah($grossProfit)) . '</span>'
                )),
            Stat::make('Total Cashout', Money::rupiah($totalCashout))
                ->description("Tahun {$year}"),
        ];
    }
}
