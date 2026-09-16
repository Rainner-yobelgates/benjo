@props([
    'getState',
    'options',
    'id',
    'name',
    'disabled',
    'errors',
])

@php
    $state = $getState();
    $selected = is_array($state) ? $state : [];
    $isDisabled = (bool) $disabled;
@endphp

<div
    x-data="{
        search: @entangle('search').live,
        _options: @js($options),
        get filtered() {
            if (!this.search) return this._options;
            const q = this.search.toLowerCase();
            return this._options.filter(o =>
                o.label.toLowerCase().includes(q) ||
                o.group?.toLowerCase().includes(q)
            );
        },
        get selectedSet() {
            return new Set(@json($selected));
        },
        isSelected(key) {
            return this.selectedSet.has(key);
        },
        toggle(key) {
            if (this.isDisabled) return;
            const set = this.selectedSet;
            if (set.has(key)) set.delete(key);
            else set.add(key);
            this.commit();
        },
        toggleAllInGroup(groupKey) {
            if (this.isDisabled) return;
            const group = this._options.filter(o => o.group === groupKey);
            const set = this.selectedSet;
            const anySelected = group.some(o => set.has(o.key));
            group.forEach(o => {
                if (anySelected) set.delete(o.key);
                else set.add(o.key);
            });
            this.commit();
        },
        isGroupAllSelected(groupKey) {
            const group = this._options.filter(o => o.group === groupKey);
            if (group.length === 0) return false;
            const set = this.selectedSet;
            return group.every(o => set.has(o.key));
        },
        isGroupSomeSelected(groupKey) {
            const group = this._options.filter(o => o.group === groupKey);
            if (group.length === 0) return false;
            const set = this.selectedSet;
            return group.some(o => set.has(o.key));
        },
        commit() {
            const values = [...this.selectedSet];
            const input = document.getElementById(this._inputId);
            if (input) input.value = JSON.stringify(values);
            @this.set('{{ $name }}', values);
        }
    }"
    x-init="() => { this._inputId = '{{ $id }}-input'; }"
    wire:ignore.self
    class="space-y-5"
>
    <!-- Search -->
    <div class="relative">
        <x-filament::input
            type="search"
            wire:model.debounce.300ms="search"
            placeholder="Cari module atau izin..."
            class="w-full"
            icon="heroicon-o-magnifying-glass"
        />
    </div>

    <!-- Modules -->
    <div class="space-y-4">
        @php
            $orderedGroups = [];
            foreach ($options as $opt) {
                $g = $opt['group'] ?? '__uncategorized__';
                if (!isset($orderedGroups[$g])) {
                    $orderedGroups[$g] = [
                        'label' => $opt['groupLabel'] ?? ucwords(str_replace('_', ' ', $g)),
                        'items' => [],
                    ];
                }
                $orderedGroups[$g]['items'][] = $opt;
            }
        @endphp

        @foreach ($orderedGroups as $groupKey => $group)
            @php
                $allSelected = collect($group['items'])->every(fn($o) => in_array($o['key'], $selected, true));
                $someSelected = collect($group['items'])->some(fn($o) => in_array($o['key'], $selected, true));
                $activeCount = collect($group['items'])->filter(fn($o) => in_array($o['key'], $selected, true))->count();
                $totalCount = count($group['items']);
            @endphp

            <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-5 shadow-sm">
                <!-- Header -->
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <input
                            type="checkbox"
                            id="grp-{{ $groupKey }}"
                            :checked="isGroupAllSelected('{{ $groupKey }}')"
                            @change="toggleAllInGroup('{{ $groupKey }}')"
                            :disabled="isDisabled"
                            class="h-5 w-5 rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                        />

                        <label for="grp-{{ $groupKey }}" class="text-base font-semibold text-gray-900 dark:text-white select-none cursor-pointer">
                            {{ $group['label'] }}
                        </label>

                        @if ($totalCount > 0)
                            <span class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-medium
                                {{ $allSelected
                                    ? 'border-green-300 bg-green-50 text-green-700 dark:border-green-700 dark:bg-green-900/30 dark:text-green-300'
                                    : ($someSelected
                                        ? 'border-amber-300 bg-amber-50 text-amber-700 dark:border-amber-700 dark:bg-amber-900/30 dark:text-amber-300'
                                        : 'border-gray-300 bg-gray-50 text-gray-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-400')
                                }}">
                                {{ $activeCount }} / {{ $totalCount }} aktif
                            </span>
                        @endif
                    </div>

                    @if ($totalCount > 0)
                        <button
                            type="button"
                            @click="toggleAllInGroup('{{ $groupKey }}')"
                            :disabled="isDisabled"
                            class="text-xs font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300 disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            {{ $allSelected ? 'Hapus semua' : 'Pilih semua' }}
                        </button>
                    @endif
                </div>

                <!-- Action checkboxes -->
                <div class="mt-4 grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-x-4 gap-y-1.5">
                    @foreach ($group['items'] as $item)
                        @php
                            $key = $item['key'];
                            $label = $item['label'] ?? $key;
                        @endphp

                        <label class="flex items-start gap-3 cursor-pointer group py-1.5 {{ $isDisabled ? 'opacity-50 pointer-events-none' : '' }}">
                            <input
                                type="checkbox"
                                :value="`{{ $key }}`"
                                x-model="selectedSet"
                                @change="commit"
                                :disabled="isDisabled"
                                class="mt-0.5 h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                            />

                            <span class="text-sm text-gray-700 dark:text-gray-200 select-none">{{ $label }}</span>
                        </label>
                    @endforeach
                </div>

                @if ($totalCount === 0)
                    <div class="mt-3 rounded-lg border border-dashed border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800/50 px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                        Module ini belum memiliki izin yang terdaftar.
                    </div>
                @endif
            </div>
        @endforeach
    </div>
</div>
