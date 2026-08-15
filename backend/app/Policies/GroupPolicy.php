<?php

namespace App\Policies;

use App\Models\Group;
use App\Models\User;

class GroupPolicy
{
    public function view(User $user, Group $group)
    {
        return $group->members()->where('user_id', $user->id)->exists();
    }

    public function manage(User $user, Group $group)
    {
        return $group->creator_id == $user->id;
    }
}