<?php

namespace App\Policies;

use App\Models\User;
use App\Support\Access;
use Spatie\Permission\Models\Role;

class RolePolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Access::ROLES_VIEW_ANY);
    }

    public function create(User $user): bool
    {
        return $user->can(Access::ROLES_CREATE);
    }

    public function view(User $user, Role $role): bool
    {
        return $user->can(Access::ROLES_VIEW);
    }

    public function update(User $user, Role $role): bool
    {
        if ($role->name === Access::MASTER_ROLE && ! $user->hasRole(Access::MASTER_ROLE)) {
            return false;
        }

        return $user->can(Access::ROLES_UPDATE);
    }

    public function delete(User $user, Role $role): bool
    {
        if ($role->name === Access::MASTER_ROLE) {
            return false;
        }

        return $user->can(Access::ROLES_DELETE);
    }

    public function deleteAny(User $user): bool
    {
        return $user->can(Access::ROLES_DELETE);
    }
}
