<?php

namespace App\Policies;

use App\Models\PriceList;
use App\Models\User;
use App\Support\Access;

class PriceListPolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->can($user, Access::PRICE_LISTS_VIEW_ANY);
    }

    public function create(User $user): bool
    {
        return $this->can($user, Access::PRICE_LISTS_CREATE);
    }

    public function view(User $user, PriceList $priceList): bool
    {
        return $this->can($user, Access::PRICE_LISTS_VIEW);
    }

    public function update(User $user, PriceList $priceList): bool
    {
        return $this->can($user, Access::PRICE_LISTS_UPDATE);
    }

    public function delete(User $user, PriceList $priceList): bool
    {
        return $this->can($user, Access::PRICE_LISTS_DELETE);
    }

    public function deleteAny(User $user): bool
    {
        return $this->can($user, Access::PRICE_LISTS_DELETE);
    }
}
