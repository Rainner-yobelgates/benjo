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
    public const ROLES_DELETE_ANY = 'roles.delete_any';

    public const PERMISSIONS_VIEW_ANY = 'permissions.view_any';

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
            Access::ROLES_DELETE_ANY,

            Access::PERMISSIONS_VIEW_ANY,
        ];
    }
}