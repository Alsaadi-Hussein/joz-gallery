<?php

namespace App\Policies;

use App\Models\User;

abstract class StaffPolicy extends RolePolicy
{
    protected function allows(User $user): bool
    {
        return true;
    }
}
