<?php

namespace App\Policies;

use App\Models\User;
use App\Support\Access;
use Spatie\Permission\Models\Permission;

class PermissionPolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Access::PERMISSIONS_VIEW_ANY);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function view(User $user, Permission $permission): bool
    {
        return $user->can(Access::PERMISSIONS_VIEW_ANY);
    }

    public function update(User $user, Permission $permission): bool
    {
        return false;
    }

    public function delete(User $user, Permission $permission): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}