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

            <div class="grid gap-4 sm:grid-cols-2 2xl:grid-cols-3">
                @forelse ($this->userCommissionCards as $user)
                    <article wire:key="commission-user-{{ $period }}-{{ $user->id }}" class="flex min-w-0 flex-col rounded-xl border border-gray-200 bg-gray-50/60 p-5 dark:border-white/10 dark:bg-white/[0.03]">
                        <div class="flex min-w-0 items-start justify-between gap-3">
                            <div class="min-w-0">
                                <h3 class="truncate text-base font-semibold text-gray-950 dark:text-white" title="{{ $user->name }}">{{ $user->name }}</h3>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Persentase komisi {{ $user->commission_percent }}%</p>
                            </div>

                            <x-filament::badge :color="$user->hasActiveCommission() ? 'success' : 'gray'" size="sm" class="shrink-0">
                                {{ $user->hasActiveCommission() ? 'Aktif' : 'Nonaktif' }}
                            </x-filament::badge>
                        </div>

                        <div class="mt-5 border-t border-gray-200 pt-4 dark:border-white/10">
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Komisi {{ strtolower($this->activePeriodLabel) }}</p>
                            <p class="mt-1 break-words text-2xl font-bold tracking-tight text-primary-600 dark:text-primary-400">{{ $this->formatMoney($user->period_commission_total) }}</p>
                            <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">{{ $user->period_commission_transactions_count }} transaksi berkomisi</p>
                        </div>
                    </article>
                @empty
                    <div class="col-span-full rounded-xl border border-dashed border-gray-300 p-10 text-center dark:border-white/15">
                        <x-filament::icon icon="heroicon-o-users" class="mx-auto size-8 text-gray-400 dark:text-gray-500" />
                        <p class="mt-3 font-medium text-gray-950 dark:text-white">Belum ada user komisi</p>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Tambahkan persentase komisi pada data user untuk menampilkannya di sini.</p>
                    </div>
                @endforelse
            </div>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">
                Riwayat Komisi per Periode
            </x-slot>

            <x-slot name="description">
                Menampilkan {{ $period === 'day' ? '7 hari' : ($period === 'week' ? '8 minggu' : '12 bulan') }} terakhir, termasuk periode berjalan. Hanya transaksi Terkunci yang dihitung.
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
    </div>
</x-filament-panels::page>
