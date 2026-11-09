<?php

namespace App\Policies;

use App\Models\Complaint;
use App\Models\User;

class ComplaintPolicy
{
    public function view(User $user, Complaint $complaint): bool
    {
        return $user->hasAnyRole(User::ROLE_ADMIN, User::ROLE_SUPER_ADMIN, User::ROLE_AUDITOR) || $complaint->customer_id === $user->id;
    }

    public function update(User $user, Complaint $complaint): bool
    {
        return $user->hasAnyRole(User::ROLE_ADMIN, User::ROLE_SUPER_ADMIN);
    }
}
