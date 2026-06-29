<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\HasYearlyDashboardData;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class MonthlyCashoutChart extends ChartWidget
{
    use HasYearlyDashboardData;
    use InteractsWithPageFilters;

    protected static bool $isLazy = false;

    protected ?string $heading = 'Monthly Cashout This Year';

    protected ?string $pollingInterval = null;

    protected int | string | array $columnSpan = 1;

    protected function getData(): array
    {
        return [
            'datasets' => [
                [
                    'label' => 'Total cashout',
                    'data' => $this->getMonthlyCashoutTotals(),
                    'backgroundColor' => 'rgba(239, 68, 68, 0.2)',
                    'borderColor' => '#ef4444',
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
