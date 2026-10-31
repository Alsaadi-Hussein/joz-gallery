<?php

namespace App\Policies;

use App\Models\User;

abstract class OwnerOnlyPolicy extends RolePolicy
{
    protected function allows(User $user): bool
    {
        return $user->isOwner();
    }
}
