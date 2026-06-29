<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\HasYearlyDashboardData;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class MonthlyProfitChart extends ChartWidget
{
    use HasYearlyDashboardData;
    use InteractsWithPageFilters;

    protected static bool $isLazy = false;

    protected ?string $heading = 'Monthly Profit This Year';

    protected ?string $pollingInterval = null;

    protected int | string | array $columnSpan = 1;

    protected function getData(): array
    {
        return [
            'datasets' => [
                [
                    'label' => 'Gross profit',
                    'data' => $this->getMonthlyGrossProfitTotals(),
                    'backgroundColor' => 'rgba(245, 158, 11, 0.12)',
                    'borderColor' => '#f59e0b',
                ],
                [
                    'label' => 'Profit bersih',
                    'data' => $this->getMonthlyNetProfitTotals(),
                    'backgroundColor' => 'rgba(16, 185, 129, 0.2)',
                    'borderColor' => '#10b981',
                ],
            ],
            'labels' => $this->getMonthLabels(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
