<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\HasYearlyDashboardData;
use App\Support\Access;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class MonthlyCommissionChart extends ChartWidget
{
    use HasYearlyDashboardData;
    use InteractsWithPageFilters;

    protected static bool $isLazy = false;

    protected ?string $heading = 'Komisi Terkunci per Bulan';

    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 1;

    public static function canView(): bool
    {
        return Filament::auth()->user()?->can(Access::TRANSACTIONS_VIEW_FINANCIAL) ?? false;
    }

    protected function getData(): array
    {
        return [
            'datasets' => [
                [
                    'label' => 'Komisi terkunci',
                    'data' => $this->getMonthlyLockedCommissionTotals(),
                    'backgroundColor' => 'rgba(59, 130, 246, 0.18)',
                    'borderColor' => '#3b82f6',
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
