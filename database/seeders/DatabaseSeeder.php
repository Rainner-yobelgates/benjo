<?php

namespace Database\Seeders;

use App\Models\User;
use App\Support\Access;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // The registrar caches the whole permission table (24h) and serves
        // stale data during this seed (on a fresh database the first lookup
        // caches an empty permission list), so flush it before and after.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // firstOrCreate is plain Eloquent and does not touch the registrar
        // cache, so the records are guaranteed to exist afterwards.
        collect(Access::all())
            ->map(fn (string $name) => Permission::firstOrCreate([
                'name' => $name,
                'guard_name' => 'web',
            ]));

        static::syncRolePermissions(Access::MASTER_ROLE, Access::all());

        static::syncRolePermissions('admin', [
            Access::DASHBOARD_VIEW,

            Access::TRANSACTIONS_VIEW_ANY,
            Access::TRANSACTIONS_VIEW,
            Access::TRANSACTIONS_CREATE,
            Access::TRANSACTIONS_UPDATE,
            Access::TRANSACTIONS_UNLOCK,

            Access::ITEMS_VIEW_ANY,
            Access::ITEMS_VIEW,
            Access::ITEMS_CREATE,
            Access::ITEMS_UPDATE,

            Access::PRICE_LISTS_VIEW_ANY,
            Access::PRICE_LISTS_VIEW,
            Access::PRICE_LISTS_CREATE,
            Access::PRICE_LISTS_UPDATE,

            Access::CASHOUTS_VIEW_ANY,
            Access::CASHOUTS_VIEW,
            Access::CASHOUTS_CREATE,
            Access::CASHOUTS_UPDATE,

            Access::SETTINGS_VIEW_ANY,
            Access::SETTINGS_UPDATE,
        ]);

        static::syncRolePermissions('kasir', [
            Access::DASHBOARD_VIEW,
            Access::TRANSACTIONS_VIEW_ANY,
            Access::TRANSACTIONS_VIEW,
            Access::TRANSACTIONS_CREATE,
        ]);

        $user = User::query()->updateOrCreate([
            'name' => 'benjo',
        ], [
            'password' => Hash::make('benjogarage2018'),
        ]);

        $user->syncRoles([Role::firstOrCreate([
            'name' => Access::MASTER_ROLE,
            'guard_name' => 'web',
        ])]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Sync a role's permissions using MODEL INSTANCES. Passing models (not
     * name strings) keeps syncPermissions away from findByName and therefore
     * immune to any stale registrar cache state.
     *
     * @param  array<int, string>  $permissionNames
     */
    protected static function syncRolePermissions(string $roleName, array $permissionNames): void
    {
        $role = Role::firstOrCreate([
            'name' => $roleName,
            'guard_name' => 'web',
        ]);

        $permissions = collect($permissionNames)
            ->map(fn (string $name) => Permission::firstOrCreate([
                'name' => $name,
                'guard_name' => 'web',
            ]))
            ->values();

        $role->syncPermissions($permissions->all());
    }
}
