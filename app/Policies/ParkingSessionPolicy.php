<?php

namespace App\Policies;

use App\Models\ParkingSession;
use App\Models\User;

class ParkingSessionPolicy
{
    /**
     * Customers only see sessions linked to their own account (prevents IDOR via UUID guessing).
     */
    public function view(User $user, ParkingSession $session): bool
    {
        return $user->isInternal() || $session->customer_id === $user->id;
    }

    public function operate(User $user, ParkingSession $session): bool
    {
        return $user->hasAnyRole(User::ROLE_STAFF, User::ROLE_ADMIN, User::ROLE_SUPER_ADMIN);
    }
}
