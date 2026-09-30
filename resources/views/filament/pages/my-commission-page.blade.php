<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::section
            icon="heroicon-o-information-circle"
            icon-color="primary"
            heading="Ringkasan komisi tim"
            description="Komisi diberikan kepada user yang terlibat pada transaksi dan dihitung dari Nilai Tagihan Bruto."
        />

        <section aria-label="Pilih periode komisi">
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($this->periodStats as $periodKey => $stat)
                    <button
                        type="button"
                        wire:click="setPeriod('{{ $periodKey }}')"
                        wire:loading.attr="disabled"
                        aria-pressed="{{ $period === $periodKey ? 'true' : 'false' }}"
                        @class([
                            'group rounded-xl border p-5 text-left shadow-sm transition focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 disabled:cursor-wait disabled:opacity-70 dark:focus:ring-offset-gray-950',
                            'border-primary-500 bg-primary-50 ring-1 ring-primary-500/20 dark:border-primary-400 dark:bg-primary-500/10' => $period === $periodKey,
                            'border-gray-200 bg-white hover:border-primary-300 hover:shadow-md dark:border-white/10 dark:bg-gray-900 dark:hover:border-primary-700' => $period !== $periodKey,
                        ])
                    >
                        <div class="flex items-start justify-between gap-3">
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-300">{{ $stat['label'] }}</p>
                            @if ($period === $periodKey)
                                <x-filament::icon icon="heroicon-m-check-circle" class="size-5 shrink-0 text-primary-600 dark:text-primary-400" />
                            @endif
                        </div>
                        <p class="mt-3 break-words text-2xl font-bold tracking-tight text-gray-950 dark:text-white">{{ $this->formatMoney($stat['amount']) }}</p>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $stat['description'] }}</p>
                    </button>
                @endforeach
            </div>
        </section>

        <x-filament::section>
            <x-slot name="heading">
                Komisi per User
            </x-slot>

            <x-slot name="description">
                Periode aktif: {{ $this->activePeriodLabel }}
            </x-slot>

            <x-slot name="afterHeader">
                <x-filament::badge color="primary" size="sm">
                    {{ $this->userCommissionCards->count() }} user
                </x-filament::badge>
            </x-slot>

            <div class="mb-8 grid gap-4 md:grid-cols-3">
                <div class="rounded-xl border border-gray-200 bg-gray-50/60 p-5 md:col-span-2 dark:border-white/10 dark:bg-white/[0.03]">
                    @if ($period === 'day')
                    <label for="commission-daily-date" class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-200">
                        Tanggal komisi
                    </label>
                    <x-filament::input.wrapper wire:target="dailyDate">
                        <x-filament::input.select id="commission-daily-date" wire:model.live="dailyDate">
                            @foreach ($this->recentDailyDates as $date)
                                <option value="{{ $date['date'] }}">{{ $date['label'] }}</option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                    <p class="mt-3 text-xs leading-5 text-gray-500 dark:text-gray-400">Pilih salah satu dari 7 hari terakhir untuk melihat komisi setiap user.</p>
                    @elseif ($period === 'week')
                    <label for="commission-weekly-period" class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-200">
                        Periode komisi mingguan
                    </label>
                    <x-filament::input.wrapper wire:target="weeklyStartDate">
                        <x-filament::input.select id="commission-weekly-period" wire:model.live="weeklyStartDate">
                            @foreach ($this->recentWeeklyPeriods as $week)
                                <option value="{{ $week['date'] }}">{{ $week['label'] }}</option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                    <p class="mt-3 text-xs leading-5 text-gray-500 dark:text-gray-400">Pilih salah satu dari 8 minggu terakhir untuk melihat komisi setiap user.</p>
                    @else
                    <label for="commission-monthly-period" class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-200">
                        Periode komisi bulanan
                    </label>
                    <x-filament::input.wrapper wire:target="monthlyStart">
                        <x-filament::input.select id="commission-monthly-period" wire:model.live="monthlyStart">
                            @foreach ($this->recentMonthlyPeriods as $month)
                                <option value="{{ $month['key'] }}">{{ $month['label'] }}</option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                    <p class="mt-3 text-xs leading-5 text-gray-500 dark:text-gray-400">Pilih salah satu dari 12 bulan terakhir untuk melihat komisi setiap user.</p>
                    @endif
                </div>

                <div class="flex min-h-40 flex-col justify-between rounded-xl border border-primary-200 bg-primary-50/60 p-5 dark:border-primary-500/20 dark:bg-primary-500/[0.08]">
                    <div>
                        <p class="text-sm font-medium text-primary-700 dark:text-primary-300">{{ $this->selectedPeriodIncomeLabel }}</p>
                        <p class="mt-2 break-words text-3xl font-bold tracking-tight text-gray-950 dark:text-white">{{ $this->formatMoney($this->selectedPeriodIncome) }}</p>
                    </div>
                    <p class="mt-4 text-xs leading-5 text-gray-600 dark:text-gray-400">Nilai tagihan bruto dari transaksi terkunci pada periode ini.</p>
                </div>
            </div>

            <div class="mb-5 mt-2 flex items-center gap-2 text-sm leading-6 text-gray-500 dark:text-gray-400">
                <x-filament::icon icon="heroicon-o-cursor-arrow-rays" class="size-4 shrink-0" />
                <p>Klik kartu user untuk melihat riwayat komisinya.</p>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                @forelse ($this->userCommissionCards as $user)
                    <button
                        type="button"
                        wire:key="commission-user-{{ $period }}-{{ $dailyDate }}-{{ $user->id }}"
                        wire:click="selectUser({{ $user->id }})"
                        wire:loading.attr="disabled"
                        aria-pressed="{{ $selectedUserId === $user->id ? 'true' : 'false' }}"
                        @class([
                            'flex min-w-0 flex-col rounded-xl border p-5 text-left shadow-sm transition hover:-translate-y-0.5 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-primary-500 disabled:cursor-wait disabled:opacity-70',
                            'border-primary-500 bg-primary-50 ring-1 ring-primary-500/20 dark:border-primary-400 dark:bg-primary-500/10' => $selectedUserId === $user->id,
                            'border-gray-200 bg-gray-50/60 hover:border-primary-300 hover:shadow-sm dark:border-white/10 dark:bg-white/[0.03] dark:hover:border-primary-700' => $selectedUserId !== $user->id,
                        ])
                    >
                        <div class="min-w-0">
                            <div class="flex items-start justify-between gap-3">
                                <h3 class="truncate text-base font-semibold text-gray-950 dark:text-white" title="{{ $user->name }}">{{ $user->name }}</h3>
                                @if ($selectedUserId === $user->id)
                                    <x-filament::icon icon="heroicon-m-check-circle" class="size-5 shrink-0 text-primary-600 dark:text-primary-400" />
                                @endif
                            </div>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Persentase komisi {{ $user->commission_percent }}%</p>
                        </div>

                        <div class="mt-5 border-t border-gray-200 pt-4 dark:border-white/10">
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Komisi {{ strtolower($this->activePeriodLabel) }}</p>
                            <p class="mt-1 break-words text-2xl font-bold tracking-tight text-primary-600 dark:text-primary-400">{{ $this->formatMoney($user->period_commission_total) }}</p>
                            <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">{{ $user->period_commission_transactions_count }} transaksi berkomisi</p>
                        </div>
                    </button>
                @empty
                    <div class="col-span-full rounded-xl border border-dashed border-gray-300 p-10 text-center dark:border-white/15">
                        <x-filament::icon icon="heroicon-o-users" class="mx-auto size-8 text-gray-400 dark:text-gray-500" />
                        <p class="mt-3 font-medium text-gray-950 dark:text-white">Belum ada user komisi</p>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Tambahkan persentase komisi pada data user untuk menampilkannya di sini.</p>
                    </div>
                @endforelse
            </div>
        </x-filament::section>

        @if ($selectedUserId !== null)
            <x-filament::section>
                <x-slot name="heading">
                    <div class="flex items-center gap-2">
                        <span>Riwayat Komisi: {{ $this->selectedUserName ?? 'User' }}</span>

                        @if ($this->selectedUserTransactionsUrl)
                            <a
                                href="{{ $this->selectedUserTransactionsUrl }}"
                                title="Lihat transaksi {{ $this->selectedUserName }} pada periode ini"
                                aria-label="Lihat transaksi {{ $this->selectedUserName }} pada periode ini"
                                class="inline-flex size-7 shrink-0 items-center justify-center rounded-md text-gray-500 transition hover:bg-primary-50 hover:text-primary-600 focus:outline-none focus:ring-2 focus:ring-primary-500/50 dark:text-gray-400 dark:hover:bg-primary-500/10 dark:hover:text-primary-400"
                            >
                                <x-filament::icon icon="heroicon-o-arrow-top-right-on-square" class="size-4" />
                            </a>
                        @endif
                    </div>
                </x-slot>

                <x-slot name="description">
                    Menampilkan {{ $period === 'day' ? '7 hari' : ($period === 'week' ? '8 minggu' : '12 bulan') }} terakhir untuk user yang dipilih. Hanya transaksi Terkunci yang dihitung.
                </x-slot>

                @if ($this->commissionHistoryRows->isNotEmpty())
                <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-white/10">
                    <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                        <thead class="bg-gray-50 dark:bg-white/[0.03]">
                            <tr>
                                <th scope="col" class="whitespace-nowrap px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-200">Periode</th>
                                <th scope="col" class="whitespace-nowrap px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-200">User</th>
                                <th scope="col" class="whitespace-nowrap px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-200">Persen Snapshot</th>
                                <th scope="col" class="whitespace-nowrap px-4 py-3 text-right font-semibold text-gray-700 dark:text-gray-200">Transaksi</th>
                                <th scope="col" class="whitespace-nowrap px-4 py-3 text-right font-semibold text-gray-700 dark:text-gray-200">Pendapatan Komisi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 bg-white dark:divide-white/10 dark:bg-gray-900">
                            @foreach ($this->commissionHistoryRows as $row)
                                <tr wire:key="commission-history-{{ $period }}-{{ $row['key'] }}-{{ $row['user_id'] }}">
                                    <td class="whitespace-nowrap px-4 py-3 text-gray-700 dark:text-gray-200">{{ $row['period_label'] }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 font-medium text-gray-950 dark:text-white">{{ $row['user_name'] }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-gray-600 dark:text-gray-300">{{ $row['percent_label'] }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right text-gray-600 dark:text-gray-300">{{ $row['transaction_count'] }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right font-semibold text-primary-600 dark:text-primary-400">{{ $this->formatMoney($row['amount']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @else
                    <div class="rounded-xl border border-dashed border-gray-300 p-8 text-center dark:border-white/15">
                        <x-filament::icon icon="heroicon-o-calendar-days" class="mx-auto size-8 text-gray-400 dark:text-gray-500" />
                        <p class="mt-3 font-medium text-gray-950 dark:text-white">Belum ada riwayat komisi</p>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Kunci transaksi yang melibatkan user untuk menampilkan riwayat komisi di sini.</p>
                    </div>
                @endif
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
