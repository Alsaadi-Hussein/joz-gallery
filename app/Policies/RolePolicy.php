<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Every admin ability funnels through allows(), so each model's policy only has
 * to say who it is for. Filament checks these on every page and action, which
 * keeps access closed even when someone types the URL directly.
 */
abstract class RolePolicy
{
    abstract protected function allows(User $user): bool;

    public function viewAny(User $user): bool
    {
        return $this->allows($user);
    }

    public function view(User $user, Model $record): bool
    {
        return $this->allows($user);
    }

    public function create(User $user): bool
    {
        return $this->allows($user);
    }

    public function update(User $user, Model $record): bool
    {
        return $this->allows($user);
    }

    public function delete(User $user, Model $record): bool
    {
        return $this->allows($user);
    }

    public function deleteAny(User $user): bool
    {
        return $this->allows($user);
    }

    public function replicate(User $user, Model $record): bool
    {
        return $this->allows($user);
    }

    public function reorder(User $user): bool
    {
        return $this->allows($user);
    }
}
