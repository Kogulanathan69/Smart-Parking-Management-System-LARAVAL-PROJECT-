<?php

namespace App\Services\Integrations;

use App\Contracts\GateController;
use App\Models\ParkingSession;
use Illuminate\Support\Facades\Log;

class LogGateController implements GateController
{
    public function open(ParkingSession $session, string $direction): array
    {
        Log::info('Gate open simulated', ['session' => $session->reference, 'direction' => $direction]);

        return ['status' => 'simulated', 'driver' => 'log'];
    }
}
