<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Access;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MergedViewPermissionsTest extends TestCase
{
    public function test_every_module_has_only_one_view_permission(): void
    {
        foreach (Access::allGrouped() as $module) {
            $this->assertSame('Lihat', $module['actions']['view']);
            $this->assertArrayNotHasKey('view_any', $module['actions']);
        }

        $this->assertSame(Access::TRANSACTIONS_VIEW, Access::TRANSACTIONS_VIEW_ANY);
        $this->assertSame(Access::ITEMS_VIEW, Access::ITEMS_VIEW_ANY);
        $this->assertSame(Access::PRICE_LISTS_VIEW, Access::PRICE_LISTS_VIEW_ANY);
        $this->assertSame(Access::CASHOUTS_VIEW, Access::CASHOUTS_VIEW_ANY);
        $this->assertSame(Access::USERS_VIEW, Access::USERS_VIEW_ANY);
        $this->assertSame(Access::ROLES_VIEW, Access::ROLES_VIEW_ANY);
        $this->assertSame(Access::all(), array_values(array_unique(Access::all())));
    }

    public function test_migration_preserves_role_and_direct_grants_without_duplicates(): void
    {
        $schema = require database_path('migrations/2026_09_10_131912_create_permission_tables.php');
        $schema->up();

        try {
            $role = Role::create(['name' => 'test-worker', 'guard_name' => 'web']);
            $legacy = Permission::create(['name' => 'transactions.view_any', 'guard_name' => 'web']);
            $view = Permission::create(['name' => 'transactions.view', 'guard_name' => 'web']);
            $role->givePermissionTo([$legacy, $view]);
            DB::table('model_has_permissions')->insert([
                'permission_id' => $legacy->id,
                'model_type' => User::class,
                'model_id' => 42,
            ]);
            $settings = Permission::create(['name' => 'settings.view_any', 'guard_name' => 'web']);
            $role->givePermissionTo($settings);

            $migration = require database_path('migrations/2026_10_17_000100_merge_view_any_permissions.php');
            $migration->up();
            $migration->up();

            $this->assertSame(0, Permission::where('name', 'like', '%.view_any')->count());
            $this->assertSame(['settings.view', 'transactions.view'], $role->fresh()->permissions->pluck('name')->sort()->values()->all());
            $this->assertDatabaseHas('model_has_permissions', [
                'permission_id' => $view->id,
                'model_type' => User::class,
                'model_id' => 42,
            ]);
            $this->assertDatabaseCount('model_has_permissions', 1);
        } finally {
            $schema->down();
        }
    }
}
