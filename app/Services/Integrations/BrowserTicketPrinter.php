<?php

namespace App\Services\Integrations;

use App\Contracts\TicketPrinter;
use App\Models\ParkingSession;

/**
 * The browser prints the ticket returned by the API (window.print()); nothing is sent to hardware.
 */
class BrowserTicketPrinter implements TicketPrinter
{
    public function print(ParkingSession $session, array $ticket): array
    {
        return ['status' => 'rendered_for_browser', 'driver' => 'browser'];
    }
}
