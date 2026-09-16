<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const LEGACY_PERMISSION = 'roles.delete_any';

    private const DELETE_PERMISSION = 'roles.delete';

    public function up(): void
    {
        Permission::query()
            ->where('name', self::LEGACY_PERMISSION)
            ->get()
            ->each(function (Permission $legacyPermission): void {
                $deletePermission = Permission::query()->firstOrCreate([
                    'name' => self::DELETE_PERMISSION,
                    'guard_name' => $legacyPermission->guard_name,
                ]);

                $legacyPermission->roles->each(
                    fn ($role) => $role->givePermissionTo($deletePermission),
                );

                $legacyPermission->delete();
            });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::query()
            ->where('name', self::DELETE_PERMISSION)
            ->get()
            ->each(function (Permission $deletePermission): void {
                $legacyPermission = Permission::query()->firstOrCreate([
                    'name' => self::LEGACY_PERMISSION,
                    'guard_name' => $deletePermission->guard_name,
                ]);

                $deletePermission->roles->each(
                    fn ($role) => $role->givePermissionTo($legacyPermission),
                );
            });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
