<?php

namespace App\Policies;

use App\Models\Cashout;
use App\Models\User;
use App\Support\Access;

class CashoutPolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->can($user, Access::CASHOUTS_VIEW_ANY);
    }

    public function create(User $user): bool
    {
        return $this->can($user, Access::CASHOUTS_CREATE);
    }

    public function view(User $user, Cashout $cashout): bool
    {
        return $this->can($user, Access::CASHOUTS_VIEW);
    }

    public function update(User $user, Cashout $cashout): bool
    {
        return $this->can($user, Access::CASHOUTS_UPDATE);
    }

    public function delete(User $user, Cashout $cashout): bool
    {
        return $this->can($user, Access::CASHOUTS_DELETE);
    }

    public function deleteAny(User $user): bool
    {
        return $this->can($user, Access::CASHOUTS_DELETE);
    }
}
