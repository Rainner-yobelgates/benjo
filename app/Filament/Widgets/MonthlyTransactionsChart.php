<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\HasYearlyDashboardData;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class MonthlyTransactionsChart extends ChartWidget
{
    use HasYearlyDashboardData;
    use InteractsWithPageFilters;

    protected static bool $isLazy = false;

    protected ?string $heading = 'Monthly Transaction This Year';

    protected ?string $pollingInterval = null;

    protected int | string | array $columnSpan = 1;

    protected function getData(): array
    {
        return [
            'datasets' => [
                [
                    'label' => 'Jumlah transaksi',
                    'data' => $this->getMonthlyTransactionCounts(),
                    'backgroundColor' => '#f59e0b',
                    'borderColor' => '#d97706',
                ],
            ],
            'labels' => $this->getMonthLabels(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
