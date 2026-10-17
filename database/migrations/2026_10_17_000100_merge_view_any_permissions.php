<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            foreach (['transactions', 'items', 'price_lists', 'cashouts', 'users', 'roles', 'settings', 'permissions'] as $module) {
                foreach (Permission::query()->where('name', "{$module}.view_any")->get() as $legacy) {
                    $permission = Permission::query()->where('name', "{$module}.view")
                        ->where('guard_name', $legacy->guard_name)->first();

                    if ($permission === null) {
                        $legacy->update(['name' => "{$module}.view"]);

                        continue;
                    }

                    $permissionKey = config('permission.column_names.permission_pivot_key') ?? 'permission_id';
                    foreach (['role_has_permissions', 'model_has_permissions'] as $pivot) {
                        $table = config("permission.table_names.{$pivot}");
                        foreach (DB::table($table)->where($permissionKey, $legacy->id)->get() as $assignment) {
                            $assignment = (array) $assignment;
                            $assignment[$permissionKey] = $permission->id;
                            DB::table($table)->insertOrIgnore($assignment);
                        }
                    }

                    $legacy->delete();
                }
            }
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // The merged grants cannot be separated back into their original permissions.
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
