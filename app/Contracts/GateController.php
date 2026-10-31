<?php

namespace App\Contracts;

use App\Models\ParkingSession;

interface GateController
{
    /**
     * Instruct the gate to open. Must never be called before payment is confirmed.
     *
     * @return array{status: string, driver: string}
     */
    public function open(ParkingSession $session, string $direction): array;
}
