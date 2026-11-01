<?php

namespace App\Policies;

use App\Enums\OrderStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class OrderPolicy extends StaffPolicy
{
    /**
     * Orders only come from the checkout, which is what keeps stock and prices right.
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Only once it is finished: deleting an open order would leave its stock and
     * coupon use taken for good, and cancelling is what gives them back.
     */
    public function delete(User $user, Model $record): bool
    {
        return $user->isOwner() && in_array($record->status, [OrderStatus::Cancelled, OrderStatus::Delivered], true);
    }

    public function deleteAny(User $user): bool
    {
        return $user->isOwner();
    }
}
