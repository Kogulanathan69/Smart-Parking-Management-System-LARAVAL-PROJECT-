<?php

namespace App\Policies;

use App\Models\ParkingReservation;
use App\Models\User;

class ParkingReservationPolicy
{
    /**
     * Customers may only see reservations on their own account.
     */
    public function view(User $user, ParkingReservation $reservation): bool
    {
        return $user->isInternal() || $reservation->customer_id === $user->id;
    }
}
