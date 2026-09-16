<?php

namespace Tests\Feature;

use App\Filament\Resources\RoleResource;
use App\Filament\Resources\UserResource;
use App\Models\User;
use App\Support\Access;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RolePermissionMatrixTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (Access::all() as $permission) {
            Permission::query()->create([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }
    }

    public function test_it_extracts_only_enabled_individual_permission_toggles(): void
    {
        $selected = RoleResource::extractSelectedPermissions([
            'permission_matrix' => [
                'transactions' => [
                    'all' => true,
                    'view_any' => true,
                    'view' => true,
                    'create' => false,
                ],
                'settings' => [
                    'view_any' => true,
                    'update' => false,
                ],
            ],
        ]);

        $this->assertSame([
            Access::TRANSACTIONS_VIEW_ANY,
            Access::TRANSACTIONS_VIEW,
            Access::SETTINGS_VIEW_ANY,
        ], $selected);
    }

    public function test_it_syncs_the_selected_permissions_and_removes_disabled_ones(): void
    {
        $role = Role::query()->create([
            'name' => 'teknisi',
            'guard_name' => 'web',
        ]);

        RoleResource::syncPermissions($role, [
            Access::DASHBOARD_VIEW,
            Access::TRANSACTIONS_VIEW_ANY,
            Access::TRANSACTIONS_CREATE,
        ]);

        $this->assertSame([
            Access::DASHBOARD_VIEW,
            Access::TRANSACTIONS_CREATE,
            Access::TRANSACTIONS_VIEW_ANY,
        ], $role->fresh()->permissions->pluck('name')->sort()->values()->all());

        RoleResource::syncPermissions($role, [Access::DASHBOARD_VIEW]);

        $this->assertSame(
            [Access::DASHBOARD_VIEW],
            $role->fresh()->permissions->pluck('name')->all(),
        );
    }

    public function test_master_role_cannot_be_deleted_through_the_resource(): void
    {
        $master = Role::query()->create([
            'name' => Access::MASTER_ROLE,
            'guard_name' => 'web',
        ]);

        $this->assertFalse(RoleResource::canDelete($master));
    }

    public function test_role_delete_any_is_not_shown_as_a_separate_permission(): void
    {
        $this->assertNotContains('roles.delete_any', Access::all());
        $this->assertArrayNotHasKey('delete_any', Access::allGrouped()['roles']['actions']);
        $this->assertArrayHasKey('delete', Access::allGrouped()['roles']['actions']);
    }

    public function test_sync_role_replaces_the_users_previous_role(): void
    {
        $firstRole = Role::query()->create(['name' => 'kasir', 'guard_name' => 'web']);
        $secondRole = Role::query()->create(['name' => 'admin', 'guard_name' => 'web']);
        $user = User::factory()->create();

        UserResource::syncRole($user, $firstRole->id);
        UserResource::syncRole($user, $secondRole->id);

        $this->assertSame([$secondRole->id], $user->fresh()->roles->pluck('id')->all());
    }
}
