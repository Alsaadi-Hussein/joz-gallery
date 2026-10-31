<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class UserPolicy extends OwnerOnlyPolicy
{
    /**
     * An owner cannot delete themselves, so the store always keeps one owner.
     *
     * @param  User  $record
     */
    public function delete(User $user, Model $record): bool
    {
        return $user->isOwner() && ! $user->is($record);
    }
}
