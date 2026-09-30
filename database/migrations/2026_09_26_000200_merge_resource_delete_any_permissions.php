<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /** @var array<string, string> */
    private const PERMISSIONS = [
        'transactions.delete_any' => 'transactions.delete',
        'items.delete_any' => 'items.delete',
        'price_lists.delete_any' => 'price_lists.delete',
        'cashouts.delete_any' => 'cashouts.delete',
        'users.delete_any' => 'users.delete',
    ];

    public function up(): void
    {
        foreach (self::PERMISSIONS as $legacyName => $deleteName) {
            Permission::query()
                ->where('name', $legacyName)
                ->get()
                ->each(function (Permission $legacyPermission) use ($deleteName): void {
                    $deletePermission = Permission::query()->firstOrCreate([
                        'name' => $deleteName,
                        'guard_name' => $legacyPermission->guard_name,
                    ]);

                    $legacyPermission->roles->each(
                        fn ($role) => $role->givePermissionTo($deletePermission),
                    );

                    $legacyPermission->delete();
                });
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
