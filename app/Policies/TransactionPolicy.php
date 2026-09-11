<?php

namespace App\Policies;

use App\Models\Transaction;
use App\Models\User;
use App\Support\Access;

class TransactionPolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->can($user, Access::TRANSACTIONS_VIEW_ANY);
    }

    public function create(User $user): bool
    {
        return $this->can($user, Access::TRANSACTIONS_CREATE);
    }

    public function view(User $user, Transaction $transaction): bool
    {
        return $this->can($user, Access::TRANSACTIONS_VIEW);
    }

    public function update(User $user, Transaction $transaction): bool
    {
        return $this->can($user, Access::TRANSACTIONS_UPDATE);
    }

    public function delete(User $user, Transaction $transaction): bool
    {
        return $this->can($user, Access::TRANSACTIONS_DELETE);
    }

    public function deleteAny(User $user): bool
    {
        return $this->can($user, Access::TRANSACTIONS_DELETE_ANY);
    }
}