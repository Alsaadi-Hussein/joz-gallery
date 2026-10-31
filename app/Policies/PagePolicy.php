<?php

namespace App\Policies;

use App\Models\Page;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class PagePolicy extends OwnerOnlyPolicy
{
    /**
     * The pages the header and footer link to can be edited or hidden, not deleted.
     *
     * @param  Page  $record
     */
    public function delete(User $user, Model $record): bool
    {
        return $user->isOwner() && ! $record->isCore();
    }
}
