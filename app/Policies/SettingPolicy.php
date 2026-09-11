<?php

namespace App\Policies;

use App\Models\Setting;
use App\Models\User;
use App\Support\Access;

class SettingPolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->can($user, Access::SETTINGS_VIEW_ANY);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function view(User $user, Setting $setting): bool
    {
        return $this->can($user, Access::SETTINGS_VIEW_ANY);
    }

    public function update(User $user, Setting $setting): bool
    {
        return $this->can($user, Access::SETTINGS_UPDATE);
    }

    public function delete(User $user, Setting $setting): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}