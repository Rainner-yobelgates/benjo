<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Shared helpers for application policies.
 *
 * Every method a Laravel gate can ask for is defined so the default
 * "policy present but method missing" fallback of Filament (which would
 * allow the action) can never be hit accidentally.
 */
abstract class BasePolicy
{
    protected function can(User $user, string $permission): bool
    {
        return $user->can($permission);
    }

    public function forceDelete(User $user, Model $record): bool
    {
        return false;
    }

    public function forceDeleteAny(User $user): bool
    {
        return false;
    }

    public function reorder(User $user): bool
    {
        return false;
    }

    public function replicate(User $user, Model $record): bool
    {
        return false;
    }

    public function restore(User $user, Model $record): bool
    {
        return false;
    }

    public function restoreAny(User $user): bool
    {
        return false;
    }
}