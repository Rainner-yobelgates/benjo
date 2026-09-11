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
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = Access::all();

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission);
        }

        static::syncRolePermissions(Access::MASTER_ROLE, $permissions);

        static::syncRolePermissions('admin', [
            Access::DASHBOARD_VIEW,

            Access::TRANSACTIONS_VIEW_ANY,
            Access::TRANSACTIONS_VIEW,
            Access::TRANSACTIONS_CREATE,
            Access::TRANSACTIONS_UPDATE,

            Access::ITEMS_VIEW_ANY,
            Access::ITEMS_VIEW,
            Access::ITEMS_CREATE,
            Access::ITEMS_UPDATE,

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

        $user->syncRoles([Access::MASTER_ROLE]);
    }

    /**
     * @param  array<int, string>  $permissions
     */
    protected static function syncRolePermissions(string $roleName, array $permissions): void
    {
        $role = Role::findOrCreate($roleName);

        $role->syncPermissions($permissions);
    }
}
