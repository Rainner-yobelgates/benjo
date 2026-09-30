<?php

namespace App\Filament\Pages;

use App\Filament\Resources\TransactionResource;
use App\Models\Transaction;
use App\Models\TransactionCommission;
use App\Models\User;
use App\Support\Money;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class MyCommissionPage extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-currency-dollar';

    protected static ?string $navigationLabel = 'Komisi';

    protected static ?string $title = 'Komisi';

    protected static ?int $navigationSort = 10;

    protected string $view = 'filament.pages.my-commission-page';

    public string $period = 'day';

    public string $dailyDate = '';

    public string $weeklyStartDate = '';

    public string $monthlyStart = '';

    public ?int $selectedUserId = null;

    public function mount(): void
    {
        $this->dailyDate = now()->toDateString();
        $this->weeklyStartDate = now()->startOfWeek()->toDateString();
        $this->monthlyStart = now()->startOfMonth()->format('Y-m');

        $userId = User::query()
            ->where('commission_percent', '>', 0)
            ->orderBy('name')
            ->value('id');

        $this->selectedUserId = $userId === null ? null : (int) $userId;
    }

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

    public function selectUser(int $userId): void
    {
        if (! $this->userCommissionCards->contains('id', $userId)) {
            return;
        }

        $this->selectedUserId = $this->selectedUserId === $userId ? null : $userId;
    }

    public function getSelectedUserNameProperty(): ?string
    {
        return $this->userCommissionCards
            ->firstWhere('id', $this->selectedUserId)?->name;
    }

    public function getSelectedUserTransactionsUrlProperty(): ?string
    {
        if ($this->selectedUserId === null) {
            return null;
        }

        [$start, $end] = $this->periodRange($this->period);

        return TransactionResource::getUrl('index', [
            'tableFilters' => [
                'transaction_date_range' => [
                    'from' => $start->toDateString(),
                    'until' => $end->toDateString(),
                ],
                'participant_id' => [
                    'value' => $this->selectedUserId,
                ],
            ],
        ]);
    }

    /**
     * @return array<int, array{date: string, label: string}>
     */
    public function getRecentDailyDatesProperty(): array
    {
        return collect(range(0, 6))
            ->map(function (int $offset): array {
                $date = now()->startOfDay()->subDays($offset);

                return [
                    'date' => $date->toDateString(),
                    'label' => match ($offset) {
                        0 => 'Hari ini — '.$date->translatedFormat('d M Y'),
                        1 => 'Kemarin — '.$date->translatedFormat('d M Y'),
                        default => $date->locale('id')->translatedFormat('l, d M Y'),
                    },
                ];
            })
            ->all();
    }

    /**
     * @return array<int, array{date: string, label: string}>
     */
    public function getRecentWeeklyPeriodsProperty(): array
    {
        return collect(range(0, 7))
            ->map(function (int $offset): array {
                $start = now()->startOfWeek()->subWeeks($offset);
                $end = $start->copy()->endOfWeek();

                return [
                    'date' => $start->toDateString(),
                    'label' => $offset === 0
                        ? 'Minggu ini — '.$start->format('d M').' s.d. '.$end->format('d M Y')
                        : $start->format('d M').' s.d. '.$end->format('d M Y'),
                ];
            })
            ->all();
    }

    /**
     * @return array<int, array{key: string, label: string}>
     */
    public function getRecentMonthlyPeriodsProperty(): array
    {
        return collect(range(0, 11))
            ->map(function (int $offset): array {
                $start = now()->startOfMonth()->subMonthsNoOverflow($offset);

                return [
                    'key' => $start->format('Y-m'),
                    'label' => $offset === 0
                        ? 'Bulan ini — '.$start->locale('id')->translatedFormat('F Y')
                        : $start->locale('id')->translatedFormat('F Y'),
                ];
            })
            ->all();
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
                        'day' => $start->isToday()
                            ? 'Hari ini, '.$start->format('d M Y')
                            : $start->locale('id')->translatedFormat('l, d M Y'),
                        'week' => $start->isSameWeek(now())
                            ? 'Minggu berjalan'
                            : $start->format('d M').' s.d. '.$end->format('d M Y'),
                        default => 'Bulan '.$start->translatedFormat('F Y'),
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
            'day' => $this->dailyRangeStart()->isToday()
                ? 'Harian (Hari ini)'
                : 'Harian ('.$this->dailyRangeStart()->locale('id')->translatedFormat('d M Y').')',
            'week' => 'Mingguan ('.$this->weeklyRangeStart()->format('d M').' s.d. '.$this->weeklyRangeStart()->copy()->endOfWeek()->format('d M Y').')',
            default => 'Bulanan ('.$this->monthlyRangeStart()->locale('id')->translatedFormat('F Y').')',
        };
    }

    public function getSelectedPeriodIncomeProperty(): float
    {
        [$start, $end] = $this->periodRange($this->period);

        return (float) Transaction::query()
            ->where('status', Transaction::STATUS_LOCKED)
            ->whereBetween('transaction_date', [
                $start->toDateString(),
                $end->toDateString(),
            ])
            ->sum('total_income');
    }

    public function getSelectedPeriodIncomeLabelProperty(): string
    {
        [$start, $end] = $this->periodRange($this->period);

        return match ($this->period) {
            'day' => $start->isToday()
                ? 'Pendapatan Kotor Hari Ini'
                : 'Pendapatan Kotor '.$start->locale('id')->translatedFormat('d M Y'),
            'week' => 'Pendapatan Kotor '.$start->format('d M').' s.d. '.$end->format('d M Y'),
            default => 'Pendapatan Kotor '.$start->locale('id')->translatedFormat('F Y'),
        };
    }

    /**
     * @return Collection<int, array{key: string, period_label: string, user_id: int, user_name: string, percent_label: string, transaction_count: int, amount: float}>
     */
    public function getCommissionHistoryRowsProperty(): Collection
    {
        if ($this->selectedUserId === null) {
            return collect();
        }

        $buckets = collect($this->historyBuckets($this->period));
        $oldestStart = $buckets->last()['start'];
        $newestEnd = $buckets->first()['end'];

        $commissions = TransactionCommission::query()
            ->with('transaction:id,transaction_date,status')
            ->where('user_id', $this->selectedUserId)
            ->whereHas('transaction', fn ($query) => $query
                ->where('status', Transaction::STATUS_LOCKED)
                ->whereBetween('transaction_date', [
                    $oldestStart->toDateString(),
                    $newestEnd->toDateString(),
                ]))
            ->get();
        $userIds = collect([$this->selectedUserId]);

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
            'day' => [$this->dailyRangeStart(), $this->dailyRangeStart()->copy()->endOfDay()],
            'week' => [$this->weeklyRangeStart(), $this->weeklyRangeStart()->copy()->endOfWeek()],
            default => [$this->monthlyRangeStart(), $this->monthlyRangeStart()->copy()->endOfMonth()],
        };
    }

    private function dailyRangeStart(): Carbon
    {
        $allowedDates = collect($this->getRecentDailyDatesProperty())->pluck('date');
        $date = $allowedDates->contains($this->dailyDate)
            ? $this->dailyDate
            : now()->toDateString();

        return Carbon::parse($date)->startOfDay();
    }

    private function weeklyRangeStart(): Carbon
    {
        $allowedDates = collect($this->getRecentWeeklyPeriodsProperty())->pluck('date');
        $date = $allowedDates->contains($this->weeklyStartDate)
            ? $this->weeklyStartDate
            : now()->startOfWeek()->toDateString();

        return Carbon::parse($date)->startOfWeek();
    }

    private function monthlyRangeStart(): Carbon
    {
        $allowedMonths = collect($this->getRecentMonthlyPeriodsProperty())->pluck('key');
        $month = $allowedMonths->contains($this->monthlyStart)
            ? $this->monthlyStart
            : now()->format('Y-m');

        return Carbon::createFromFormat('Y-m', $month)->startOfMonth();
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
                        'label' => $start->locale('id')->translatedFormat('l, d M Y'),
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
                        'label' => $start->format('d M').' – '.$end->format('d M Y'),
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
        return rtrim(rtrim(number_format((float) $percent, 2, '.', ''), '0'), '.').'%';
    }
}
