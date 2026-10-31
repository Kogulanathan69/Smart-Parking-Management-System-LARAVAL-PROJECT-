<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Vehicle;

class VehiclePolicy
{
    public function view(User $user, Vehicle $vehicle): bool
    {
        return $user->isInternal() || $vehicle->owner_id === $user->id;
    }

    public function update(User $user, Vehicle $vehicle): bool
    {
        return $user->hasRole(User::ROLE_CUSTOMER) && $vehicle->owner_id === $user->id;
    }

    public function delete(User $user, Vehicle $vehicle): bool
    {
        return $this->update($user, $vehicle);
    }
}
