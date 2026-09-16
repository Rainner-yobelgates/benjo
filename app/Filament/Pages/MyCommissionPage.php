<?php

namespace App\Filament\Pages;

use App\Models\TransactionCommission;
use App\Models\Transaction;
use App\Models\User;
use App\Support\Money;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class MyCommissionPage extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-currency-dollar';

    protected static ?string $navigationLabel = 'Komisi Saya';

    protected static ?int $navigationSort = 10;

    protected string $view = 'filament.pages.my-commission-page';

    public string $period = 'month';

    public function content(Schema $schema): Schema
    {
        return $schema;
    }

    public function setPeriod(string $period): void
    {
        if (in_array($period, ['day', 'week', 'month'], true)) {
            $this->period = $period;
        }
    }

    /**
     * @return array<string, array{label: string, description: string, amount: float}>
     */
    public function getPeriodStatsProperty(): array
    {
        return collect(['day', 'week', 'month'])
            ->mapWithKeys(function (string $period): array {
                [$start, $end] = $this->periodRange($period);

                return [$period => [
                    'label' => match ($period) {
                        'day' => 'Komisi Harian',
                        'week' => 'Komisi Mingguan',
                        default => 'Komisi Bulanan',
                    },
                    'description' => match ($period) {
                        'day' => 'Hari ini, ' . $start->format('d M Y'),
                        'week' => 'Minggu berjalan',
                        default => 'Bulan ' . $start->translatedFormat('F Y'),
                    },
                    'amount' => $this->commissionTotalBetween($start, $end),
                ]];
            })
            ->all();
    }

    /**
     * @return Collection<int, User>
     */
    public function getUserCommissionCardsProperty(): Collection
    {
        [$start, $end] = $this->periodRange($this->period);

        return User::query()
            ->where('commission_percent', '>', 0)
            ->withSum([
                'commissions as period_commission_total' => fn ($query) => $query->whereHas(
                    'transaction',
                    fn ($query) => $query
                        ->where('status', Transaction::STATUS_LOCKED)
                        ->whereBetween('transaction_date', [
                            $start->toDateString(),
                            $end->toDateString(),
                        ]),
                ),
            ], 'amount')
            ->withCount([
                'commissions as period_commission_transactions_count' => fn ($query) => $query->whereHas(
                    'transaction',
                    fn ($query) => $query
                        ->where('status', Transaction::STATUS_LOCKED)
                        ->whereBetween('transaction_date', [
                            $start->toDateString(),
                            $end->toDateString(),
                        ]),
                ),
            ])
            ->orderBy('name')
            ->get();
    }

    public function getActivePeriodLabelProperty(): string
    {
        return match ($this->period) {
            'day' => 'Harian',
            'week' => 'Mingguan',
            default => 'Bulanan',
        };
    }

    /**
     * @return Collection<int, array{key: string, period_label: string, user_id: int, user_name: string, percent_label: string, transaction_count: int, amount: float}>
     */
    public function getCommissionHistoryRowsProperty(): Collection
    {
        $buckets = collect($this->historyBuckets($this->period));
        $oldestStart = $buckets->last()['start'];
        $newestEnd = $buckets->first()['end'];

        $commissions = TransactionCommission::query()
            ->with('transaction:id,transaction_date,status')
            ->whereHas('transaction', fn ($query) => $query
                ->where('status', Transaction::STATUS_LOCKED)
                ->whereBetween('transaction_date', [
                    $oldestStart->toDateString(),
                    $newestEnd->toDateString(),
                ]))
            ->get();
        $userIds = User::query()
            ->where('commission_percent', '>', 0)
            ->pluck('id')
            ->merge($commissions->pluck('user_id'))
            ->unique()
            ->values();

        if ($userIds->isEmpty()) {
            return collect();
        }

        $users = User::query()
            ->whereIn('id', $userIds)
            ->orderBy('name')
            ->get(['id', 'name']);
        $commissionsByUser = $commissions->groupBy('user_id');

        return $buckets->flatMap(function (array $bucket) use ($users, $commissionsByUser): Collection {
            return $users->map(function (User $user) use ($bucket, $commissionsByUser): array {
                $userCommissions = ($commissionsByUser->get($user->id) ?? collect())
                    ->filter(fn (TransactionCommission $commission): bool => $commission->transaction !== null
                        && $commission->transaction->transaction_date->betweenIncluded($bucket['start'], $bucket['end']))
                    ->values();
                $percentLabel = $userCommissions
                    ->pluck('percent')
                    ->map(fn (string|float $percent): string => $this->formatPercent($percent))
                    ->unique()
                    ->implode(', ');

                return [
                    'key' => $bucket['key'],
                    'period_label' => $bucket['label'],
                    'user_id' => $user->id,
                    'user_name' => $user->name,
                    'percent_label' => filled($percentLabel) ? $percentLabel : '-',
                    'transaction_count' => $userCommissions->pluck('transaction_id')->unique()->count(),
                    'amount' => (float) $userCommissions->sum('amount'),
                ];
            });
        })->values();
    }

    public function formatMoney(float|int|string|null $amount): string
    {
        return Money::rupiah((float) ($amount ?? 0));
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function periodRange(string $period): array
    {
        return match ($period) {
            'day' => [now()->startOfDay(), now()->endOfDay()],
            'week' => [now()->startOfWeek(), now()->endOfWeek()],
            default => [now()->startOfMonth(), now()->endOfMonth()],
        };
    }

    private function commissionTotalBetween(Carbon $start, Carbon $end): float
    {
        return (float) TransactionCommission::query()
            ->whereHas('transaction', fn ($query) => $query->whereBetween('transaction_date', [
                $start->toDateString(),
                $end->toDateString(),
            ])->where('status', Transaction::STATUS_LOCKED))
            ->sum('amount');
    }

    /**
     * @return array<int, array{key: string, label: string, start: Carbon, end: Carbon}>
     */
    private function historyBuckets(string $period): array
    {
        return match ($period) {
            'day' => collect(range(0, 6))
                ->map(function (int $offset): array {
                    $start = now()->startOfDay()->subDays($offset);

                    return [
                        'key' => $start->toDateString(),
                        'label' => $start->translatedFormat('l, d M Y'),
                        'start' => $start,
                        'end' => $start->copy()->endOfDay(),
                    ];
                })
                ->all(),
            'week' => collect(range(0, 7))
                ->map(function (int $offset): array {
                    $start = now()->startOfWeek()->subWeeks($offset);
                    $end = $start->copy()->endOfWeek();

                    return [
                        'key' => $start->toDateString(),
                        'label' => $start->format('d M') . ' – ' . $end->format('d M Y'),
                        'start' => $start,
                        'end' => $end,
                    ];
                })
                ->all(),
            default => collect(range(0, 11))
                ->map(function (int $offset): array {
                    $start = now()->startOfMonth()->subMonthsNoOverflow($offset);

                    return [
                        'key' => $start->format('Y-m'),
                        'label' => $start->translatedFormat('F Y'),
                        'start' => $start,
                        'end' => $start->copy()->endOfMonth(),
                    ];
                })
                ->all(),
        };
    }

    private function formatPercent(string|float|int $percent): string
    {
        return rtrim(rtrim(number_format((float) $percent, 2, '.', ''), '0'), '.') . '%';
    }
}
