<?php

namespace App\Policies;

use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class CategoryPolicy extends StaffPolicy
{
    /**
     * @param  Category  $record
     */
    public function delete(User $user, Model $record): bool
    {
        return ! $record->products()->exists() && ! $record->children()->exists();
    }
}
