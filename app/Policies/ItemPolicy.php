<?php

namespace App\Policies;

use App\Models\Item;
use App\Models\User;
use App\Support\Access;

class ItemPolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->can($user, Access::ITEMS_VIEW_ANY);
    }

    public function create(User $user): bool
    {
        return $this->can($user, Access::ITEMS_CREATE);
    }

    public function view(User $user, Item $item): bool
    {
        return $this->can($user, Access::ITEMS_VIEW);
    }

    public function update(User $user, Item $item): bool
    {
        return $this->can($user, Access::ITEMS_UPDATE);
    }

    public function delete(User $user, Item $item): bool
    {
        return $this->can($user, Access::ITEMS_DELETE);
    }

    public function deleteAny(User $user): bool
    {
        return $this->can($user, Access::ITEMS_DELETE_ANY);
    }
}