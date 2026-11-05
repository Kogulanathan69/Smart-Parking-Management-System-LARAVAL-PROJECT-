<?php

namespace App\Http\Controllers\Api\Anpr;

use App\Http\Controllers\Controller;
use App\Models\GateCommand;
use App\Models\ParkingSession;
use App\Services\GateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GateCommandController extends Controller
{
    /**
     * Retry a failed gate open for a settled exit. Refused if the gate already opened (no replay).
     */
    public function retryExit(Request $request, ParkingSession $session, GateService $gates): JsonResponse
    {
        return response()->json(['gate' => $gates->openForExit($session, $request->user())]);
    }

    public function history(ParkingSession $session): JsonResponse
    {
        return response()->json(['data' => GateCommand::where('parking_session_id', $session->id)->orderBy('id')->get(['direction', 'status', 'driver', 'result', 'requested_by', 'created_at'])]);
    }
}
