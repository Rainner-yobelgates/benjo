<?php

namespace App\Support;

use App\Models\User;

/**
 * Central definition of every permission used by the application.
 *
 * Permissions are stored in the `permissions` table (guard: "web") and are
 * attached to roles through the role management UI. The "master" role always
 * bypasses permission checks (see User::can()).
 */
class Access
{
    public const MASTER_ROLE = 'master';

    public const DASHBOARD_VIEW = 'dashboard.view';

    public const TRANSACTIONS_VIEW_ANY = 'transactions.view_any';
    public const TRANSACTIONS_VIEW = 'transactions.view';
    public const TRANSACTIONS_CREATE = 'transactions.create';
    public const TRANSACTIONS_UPDATE = 'transactions.update';
    public const TRANSACTIONS_UNLOCK = 'transactions.unlock';
    public const TRANSACTIONS_DELETE = 'transactions.delete';
    public const TRANSACTIONS_DELETE_ANY = 'transactions.delete_any';

    public const ITEMS_VIEW_ANY = 'items.view_any';
    public const ITEMS_VIEW = 'items.view';
    public const ITEMS_CREATE = 'items.create';
    public const ITEMS_UPDATE = 'items.update';
    public const ITEMS_DELETE = 'items.delete';
    public const ITEMS_DELETE_ANY = 'items.delete_any';

    public const PRICE_LISTS_VIEW_ANY = 'price_lists.view_any';
    public const PRICE_LISTS_VIEW = 'price_lists.view';
    public const PRICE_LISTS_CREATE = 'price_lists.create';
    public const PRICE_LISTS_UPDATE = 'price_lists.update';
    public const PRICE_LISTS_DELETE = 'price_lists.delete';
    public const PRICE_LISTS_DELETE_ANY = 'price_lists.delete_any';

    public const CASHOUTS_VIEW_ANY = 'cashouts.view_any';
    public const CASHOUTS_VIEW = 'cashouts.view';
    public const CASHOUTS_CREATE = 'cashouts.create';
    public const CASHOUTS_UPDATE = 'cashouts.update';
    public const CASHOUTS_DELETE = 'cashouts.delete';
    public const CASHOUTS_DELETE_ANY = 'cashouts.delete_any';

    public const SETTINGS_VIEW_ANY = 'settings.view_any';
    public const SETTINGS_UPDATE = 'settings.update';

    public const USERS_VIEW_ANY = 'users.view_any';
    public const USERS_VIEW = 'users.view';
    public const USERS_CREATE = 'users.create';
    public const USERS_UPDATE = 'users.update';
    public const USERS_DELETE = 'users.delete';
    public const USERS_DELETE_ANY = 'users.delete_any';

    public const ROLES_VIEW_ANY = 'roles.view_any';
    public const ROLES_VIEW = 'roles.view';
    public const ROLES_CREATE = 'roles.create';
    public const ROLES_UPDATE = 'roles.update';
    public const ROLES_DELETE = 'roles.delete';

    public const PERMISSIONS_VIEW_ANY = 'permissions.view_any';

    /**
     * Human-readable action labels for the permission matrix UI.
     *
     * @var array<string, string>
     */
    private const ACTION_LABELS = [
        'view' => 'Lihat',
        'view_any' => 'Lihat Semua',
        'create' => 'Tambah',
        'update' => 'Ubah',
        'unlock' => 'Buka Kunci',
        'delete' => 'Hapus',
        'delete_any' => 'Hapus Semua',
        'restore' => 'Pulihkan',
        'restore_any' => 'Pulihkan Semua',
        'force_delete' => 'Hapus Permanen',
        'force_delete_any' => 'Hapus Permanen Semua',
        'approve' => 'Approval',
        'print' => 'Print',
        'export' => 'Export',
        'import' => 'Import',
        'adjust' => 'Adjustment',
    ];

    /**
     * Human-readable module labels for the permission matrix UI.
     *
     * @var array<string, string>
     */
    private const MODULE_LABELS = [
        'dashboard' => 'Dashboard',
        'transactions' => 'Transaksi',
        'items' => 'Barang',
        'price_lists' => 'Daftar Harga',
        'cashouts' => 'Cashout',
        'users' => 'Pengguna',
        'roles' => 'Role Management',
        'permissions' => 'Izin',
        'settings' => 'Pengaturan',
    ];

    /**
     * Module display precedence — lower numbers first, unknown modules last.
     *
     * @var array<string, int>
     */
    private const MODULE_ORDER = [
        'dashboard' => 1,
        'transactions' => 2,
        'items' => 3,
        'price_lists' => 4,
        'cashouts' => 5,
        'users' => 6,
        'roles' => 7,
        'permissions' => 8,
        'settings' => 9,
    ];

    /**
     * Action sort order within a module — lower numbers first.
     *
     * @var array<string, int>
     */
    private const ACTION_ORDER = [
        'view' => 10,
        'view_any' => 20,
        'create' => 30,
        'update' => 40,
        'unlock' => 45,
        'approve' => 50,
        'delete' => 60,
        'delete_any' => 70,
        'restore' => 80,
        'restore_any' => 90,
        'force_delete' => 100,
        'force_delete_any' => 110,
        'import' => 120,
        'export' => 130,
        'print' => 140,
        'adjust' => 150,
    ];

    /**
     * The complete list of permissions, used by the seeder to create the
     * permission records and to seed the "master" role.
     *
     * @return array<int, string>
     */
    public static function all(): array
    {
        return [
            Access::DASHBOARD_VIEW,

            Access::TRANSACTIONS_VIEW_ANY,
            Access::TRANSACTIONS_VIEW,
            Access::TRANSACTIONS_CREATE,
            Access::TRANSACTIONS_UPDATE,
            Access::TRANSACTIONS_UNLOCK,
            Access::TRANSACTIONS_DELETE,
            Access::TRANSACTIONS_DELETE_ANY,

            Access::ITEMS_VIEW_ANY,
            Access::ITEMS_VIEW,
            Access::ITEMS_CREATE,
            Access::ITEMS_UPDATE,
            Access::ITEMS_DELETE,
            Access::ITEMS_DELETE_ANY,

            Access::PRICE_LISTS_VIEW_ANY,
            Access::PRICE_LISTS_VIEW,
            Access::PRICE_LISTS_CREATE,
            Access::PRICE_LISTS_UPDATE,
            Access::PRICE_LISTS_DELETE,
            Access::PRICE_LISTS_DELETE_ANY,

            Access::CASHOUTS_VIEW_ANY,
            Access::CASHOUTS_VIEW,
            Access::CASHOUTS_CREATE,
            Access::CASHOUTS_UPDATE,
            Access::CASHOUTS_DELETE,
            Access::CASHOUTS_DELETE_ANY,

            Access::SETTINGS_VIEW_ANY,
            Access::SETTINGS_UPDATE,

            Access::USERS_VIEW_ANY,
            Access::USERS_VIEW,
            Access::USERS_CREATE,
            Access::USERS_UPDATE,
            Access::USERS_DELETE,
            Access::USERS_DELETE_ANY,

            Access::ROLES_VIEW_ANY,
            Access::ROLES_VIEW,
            Access::ROLES_CREATE,
            Access::ROLES_UPDATE,
            Access::ROLES_DELETE,

            Access::PERMISSIONS_VIEW_ANY,
        ];
    }

    /**
     * Group all known permissions by module prefix, with human-readable labels.
     *
     * Group structure:
     *   module_key => [
     *       'label'   => 'Transaksi',
     *       'key'     => 'transactions',
     *       'actions' => [
     *           'view'    => 'Lihat',
     *           'create'  => 'Tambah',
     *       ],
     *   ]
     *
     * Modules and actions inside each module are sorted by a predefined
     * precedence so the matrix always renders in a stable, predictable order.
     *
     * @return array<string, array{label: string, key: string, actions: array<string, string>}>
     */
    public static function allGrouped(): array
    {
        $grouped = [];

        foreach (self::all() as $permission) {
            if (! str_contains($permission, '.')) {
                continue;
            }

            [$module, $action] = explode('.', $permission, 2);

            $grouped[$module] ??= [
                'label'   => self::moduleLabel($module),
                'key'     => $module,
                'actions' => [],
            ];

            $grouped[$module]['actions'][$action] = self::actionLabel($action);
        }

        // Modules: predefined order, then alphabetical for unknowns.
        uksort($grouped, static function (string $a, string $b): int {
            $orderA = self::MODULE_ORDER[$a] ?? 9999;
            $orderB = self::MODULE_ORDER[$b] ?? 9999;

            if ($orderA !== $orderB) {
                return $orderA <=> $orderB;
            }

            return strcasecmp($a, $b);
        });

        // Actions within each module: CRUD-ish order, then alphabetical.
        foreach ($grouped as $module => &$data) {
            uksort($data['actions'], static function (string $a, string $b): int {
                $orderA = self::ACTION_ORDER[$a] ?? 9999;
                $orderB = self::ACTION_ORDER[$b] ?? 9999;

                if ($orderA !== $orderB) {
                    return $orderA <=> $orderB;
                }

                return strcasecmp($a, $b);
            });
        }

        return $grouped;
    }

    /**
     * Human-readable label for a permission action slug.
     */
    public static function actionLabel(string $action): string
    {
        return self::ACTION_LABELS[$action]
            ?? self::autoLabel($action);
    }

    /**
     * Human-readable label for a module prefix.
     */
    public static function moduleLabel(string $module): string
    {
        return self::MODULE_LABELS[$module]
            ?? self::autoLabel($module);
    }

    /**
     * Auto-format a machine name like "stock_adjustment" into "Stock Adjustment".
     */
    private static function autoLabel(string $value): string
    {
        return ucwords(str_replace(['_', '-'], ' ', $value), ' ');
    }
}
