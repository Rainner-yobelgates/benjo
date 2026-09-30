<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const PERMISSION = 'transactions.view_financial';

    public function up(): void
    {
        $permission = Permission::query()->firstOrCreate([
            'name' => self::PERMISSION,
            'guard_name' => 'web',
        ]);

        Role::query()
            ->where('name', 'admin')
            ->where('guard_name', 'web')
            ->first()
            ?->givePermissionTo($permission);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::query()
            ->where('name', self::PERMISSION)
            ->where('guard_name', 'web')
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
