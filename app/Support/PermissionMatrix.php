<?php

namespace App\Support;

/**
 * UI helpers for the Role Permission Matrix.
 *
 * These helpers are UI-only — they do NOT change how permissions are stored,
 * synced, or checked. The Spatie relationship sync and the master role guard
 * are untouched.
 */
class PermissionMatrix
{
    /** Human-readable labels for permission actions. */
    private const ACTION_LABELS = [
        'view'            => 'Lihat',
        'view_any'        => 'Lihat Semua',
        'create'          => 'Tambah',
        'update'          => 'Ubah',
        'delete'          => 'Hapus',
        'delete_any'      => 'Hapus Semua',
        'restore'         => 'Pulihkan',
        'restore_any'     => 'Pulihkan Semua',
        'force_delete'    => 'Hapus Permanen',
        'force_delete_any'=> 'Hapus Permanen Semua',
        'approve'         => 'Approval',
        'print'           => 'Print',
        'export'          => 'Export',
        'import'          => 'Import',
        'adjust'          => 'Adjustment',
    ];

    /** Human-readable module names shown in the matrix cards. */
    private const MODULE_LABELS = [
        'dashboard'    => 'Dashboard',
        'transactions' => 'Transaksi',
        'items'        => 'Barang',
        'price_lists'  => 'Daftar Harga',
        'cashouts'     => 'Cashout',
        'users'        => 'Pengguna',
        'roles'        => 'Role Management',
        'permissions'  => 'Izin',
        'settings'     => 'Pengaturan',
    ];

    /** Module display order. Unknown modules sort last alphabetically. */
    private const MODULE_ORDER = [
        'dashboard'    => 1,
        'transactions' => 2,
        'items'        => 3,
        'price_lists'  => 4,
        'cashouts'     => 5,
        'users'        => 6,
        'roles'        => 7,
        'permissions'  => 8,
        'settings'     => 9,
    ];

    /**
     * Action display order within a module (lower = appears first).
     * Unknown actions sort last.
     */
    private const ACTION_ORDER = [
        'view'            => 10,
        'view_any'        => 20,
        'create'          => 30,
        'update'          => 40,
        'approve'         => 50,
        'delete'          => 60,
        'delete_any'      => 70,
        'restore'         => 80,
        'restore_any'     => 90,
        'force_delete'    => 100,
        'force_delete_any'=> 110,
        'import'          => 120,
        'export'          => 130,
        'print'           => 140,
        'adjust'          => 150,
    ];

    /**
     * Group all permissions from Access::all() by module prefix.
     *
     * Each module's 'options' array uses permission slugs as keys
     * (e.g. 'transactions.view' => 'Lihat Semua'), suitable for
     * passing directly to CheckboxList::options().
     *
     * @return array<string, array{label: string, key: string, options: array<string, string>, total: int}>
     */
    public static function groupedPermissions(): array
    {
        $grouped = [];

        foreach (Access::all() as $permission) {
            if (! str_contains($permission, '.')) {
                continue;
            }

            [$module, $action] = explode('.', $permission, 2);

            $grouped[$module] ??= [
                'label'  => self::moduleLabel($module),
                'key'    => $module,
                'options'=> [],
            ];

            $grouped[$module]['options'][$permission] = self::actionLabel($action);
        }

        // Sort modules by order, then alphabetically.
        uksort($grouped, function (string $a, string $b): int {
            $orderA = self::MODULE_ORDER[$a] ?? 9999;
            $orderB = self::MODULE_ORDER[$b] ?? 9999;

            if ($orderA !== $orderB) {
                return $orderA <=> $orderB;
            }

            return strcasecmp($a, $b);
        });

        // Sort options within each module by action order.
        foreach ($grouped as $module => &$data) {
            $sorted = [];
            $actionNames = [];

            foreach ($data['options'] as $slug => $label) {
                [$_, $action] = explode('.', $slug, 2);
                $actionNames[$action] = $slug;
            }

            uksort($actionNames, function (string $a, string $b): int {
                $orderA = self::ACTION_ORDER[$a] ?? 9999;
                $orderB = self::ACTION_ORDER[$b] ?? 9999;

                if ($orderA !== $orderB) {
                    return $orderA <=> $orderB;
                }

                return strcasecmp($a, $b);
            });

            foreach ($actionNames as $action => $slug) {
                $sorted[$slug] = $data['options'][$slug];
            }

            $data['options'] = $sorted;
            $data['total'] = count($sorted);
        }

        return $grouped;
    }

    /**
     * Return options in the format expected by Filament's CheckboxList::options()
     * when using grouped display: ['Group Label' => ['slug' => 'Label', ...], ...]
     *
     * @return array<string, array<string, string>>
     */
    public static function groupedOptions(): array
    {
        $result = [];

        foreach (self::groupedPermissions() as $module) {
            $result[$module['label']] = $module['options'];
        }

        return $result;
    }

    /**
     * Human-readable label for a permission action slug.
     */
    public static function actionLabel(string $action): string
    {
        return self::ACTION_LABELS[$action] ?? self::autoLabel($action);
    }

    /**
     * Human-readable label for a module prefix.
     */
    public static function moduleLabel(string $module): string
    {
        return self::MODULE_LABELS[$module] ?? self::autoLabel($module);
    }

    /**
     * Auto-format a machine name like "stock_adjustment" into "Stock Adjustment".
     */
    private static function autoLabel(string $value): string
    {
        return ucwords(str_replace(['_', '-'], ' ', $value), ' ');
    }

    /**
     * Summary stats for a role.
     *
     * @param  array<int, string>  $selected  permission slugs currently on the role
     * @return array{selected: int, total: int, percent: int, status: string}
     */
    public static function summary(array $selected): array
    {
        $total = count(Access::all());
        $selectedCount = count($selected);

        $percent = $total > 0 ? (int) round(($selectedCount / $total) * 100) : 0;

        if ($selectedCount === $total) {
            $status = 'Semua akses aktif';
        } elseif ($selectedCount === 0) {
            $status = 'Tidak ada akses';
        } else {
            $status = "{$selectedCount} dari {$total} permission aktif";
        }

        return [
            'selected' => $selectedCount,
            'total'    => $total,
            'percent'  => $percent,
            'status'   => $status,
        ];
    }

    /**
     * Filter module groups by a search query (UI only).
     *
     * @param  array  $groups  output of groupedPermissions()
     * @return array
     */
    public static function filterGroups(array $groups, ?string $query): array
    {
        $q = trim($query ?? '');

        if ($q === '') {
            return $groups;
        }

        $lower = strtolower($q);

        return array_filter($groups, function (array $group) use ($lower): bool {
            if (str_contains(strtolower($group['label']), $lower)
                || str_contains(strtolower($group['key']), $lower)) {
                return true;
            }

            foreach ($group['options'] as $slug => $label) {
                [$_, $action] = explode('.', $slug, 2);
                if (str_contains(strtolower($action), $lower)
                    || str_contains(strtolower($label), $lower)) {
                    return true;
                }
            }

            return false;
        });
    }
}
