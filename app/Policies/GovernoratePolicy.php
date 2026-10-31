<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class GovernoratePolicy extends OwnerOnlyPolicy
{
    /**
     * The 18 governorates are fixed: they can be edited or switched off, not added.
     */
    public function create(User $user): bool
    {
        return false;
    }

    public function delete(User $user, Model $record): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
