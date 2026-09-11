<?php

namespace App\Policies;

use App\Models\User;
use App\Support\Access;

class UserPolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Access::USERS_VIEW_ANY);
    }

    public function create(User $user): bool
    {
        return $user->can(Access::USERS_CREATE);
    }

    public function view(User $user, User $record): bool
    {
        return $user->can(Access::USERS_VIEW);
    }

    public function update(User $user, User $record): bool
    {
        if ($record->hasRole(Access::MASTER_ROLE) && ! $user->hasRole(Access::MASTER_ROLE)) {
            return false;
        }

        return $user->can(Access::USERS_UPDATE);
    }

    public function delete(User $user, User $record): bool
    {
        if ($record->hasRole(Access::MASTER_ROLE)) {
            return false;
        }

        return $user->can(Access::USERS_DELETE);
    }

    public function deleteAny(User $user): bool
    {
        return $user->can(Access::USERS_DELETE_ANY);
    }
}