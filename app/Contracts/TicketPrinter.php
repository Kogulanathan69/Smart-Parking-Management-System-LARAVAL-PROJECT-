<?php

namespace App\Contracts;

use App\Models\ParkingSession;

interface TicketPrinter
{
    /**
     * @param  array<string, mixed>  $ticket  printable ticket payload
     * @return array{status: string, driver: string}
     */
    public function print(ParkingSession $session, array $ticket): array;
}
