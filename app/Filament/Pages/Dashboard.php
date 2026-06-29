<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\MonthlyCashoutChart;
use App\Filament\Widgets\MonthlyProfitChart;
use App\Filament\Widgets\MonthlyTransactionsChart;
use App\Filament\Widgets\TransactionStatsOverview;
use App\Models\Cashout;
use App\Models\Transaction;
use Carbon\Carbon;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class Dashboard extends BaseDashboard
{
    use HasFiltersForm;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-home';

    public function filtersForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->schema([
                        Select::make('year')
                            ->label('Tahun')
                            ->options($this->getYearOptions())
                            ->default((string) now()->year)
                            ->native(false)
                            ->selectablePlaceholder(false)
                            ->required(),
                    ])
                    ->columns(1),
            ]);
    }

    public function getColumns(): int | array
    {
        return [
            'md' => 2,
            'xl' => 3,
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getFiltersFormContentComponent(),
                Grid::make(1)
                    ->schema(fn (): array => $this->getWidgetsSchemaComponents([
                        TransactionStatsOverview::class,
                    ])),
                Grid::make([
                    'md' => 2,
                    'xl' => 3,
                ])
                    ->schema(fn (): array => $this->getWidgetsSchemaComponents([
                        MonthlyTransactionsChart::class,
                        MonthlyProfitChart::class,
                        MonthlyCashoutChart::class,
                    ])),
            ]);
    }

    /**
     * @return array<string, string>
     */
    protected function getYearOptions(): array
    {
        $currentYear = now()->year;

        $years = collect([
            Transaction::query()->min('transaction_date'),
            Cashout::query()->min('cashout_date'),
        ])
            ->filter()
            ->map(fn (string $date): int => Carbon::parse($date)->year);

        $startYear = $years->min() ?? $currentYear;

        return collect(range($currentYear, $startYear))
            ->mapWithKeys(fn (int $year): array => [(string) $year => (string) $year])
            ->all();
    }
}
