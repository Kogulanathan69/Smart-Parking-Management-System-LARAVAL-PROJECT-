<?php

namespace App\Http\Controllers\Api\Staff;

use App\Contracts\TicketPrinter;
use App\Http\Controllers\Controller;
use App\Http\Requests\PrepayRequest;
use App\Http\Resources\InspectionResource;
use App\Http\Resources\ParkingSessionResource;
use App\Http\Resources\PaymentResource;
use App\Models\ParkingSession;
use App\Models\Vehicle;
use App\Services\AuditLogger;
use App\Services\ExitService;
use App\Services\QrTicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SessionController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $sessions = ParkingSession::with(['vehicle.category', 'zone', 'slot', 'package'])
            ->whereIn('status', [ParkingSession::ACTIVE, ParkingSession::EXIT_AUTHORIZED])
            ->when($request->query('zone_id'), fn ($q, $z) => $q->where('parking_zone_id', $z))
            ->when($request->query('q'), fn ($q, $term) => $q->whereHas('vehicle', fn ($v) => $v->where('identifier', 'like', '%'.Vehicle::normalizePlate($term).'%')))
            ->latest('entry_at')
            ->paginate(25);

        return ParkingSessionResource::collection($sessions);
    }

    public function show(ParkingSession $session): JsonResponse
    {
        $session->load(['vehicle.category', 'zone', 'slot', 'package', 'receipt', 'payments.method', 'inspections']);

        return response()->json([
            'data' => new ParkingSessionResource($session),
            'payments' => PaymentResource::collection($session->payments),
            'inspections' => InspectionResource::collection($session->inspections),
        ]);
    }

    /**
     * Printable QR ticket (reprint is audited and counted).
     */
    public function ticket(ParkingSession $session, QrTicketService $tickets, TicketPrinter $printer, AuditLogger $audit): JsonResponse
    {
        $ticket = $tickets->printable($session->load(['vehicle', 'zone', 'slot', 'package', 'qrTicket']));
        $audit->log('ticket.printed', $session, [], [], ['print_count' => $ticket['print_count']]);

        return response()->json(['ticket' => $ticket, 'printer' => $printer->print($session, $ticket)]);
    }

    /**
     * Lost ticket: void the old QR and issue a new one.
     */
    public function reissueTicket(ParkingSession $session, QrTicketService $tickets, AuditLogger $audit): JsonResponse
    {
        abort_unless($session->status === ParkingSession::ACTIVE, 409, 'Session is not active.');
        $tickets->issue($session);
        $audit->log('ticket.reissued', $session);

        return response()->json(['ticket' => $tickets->printable($session->fresh(['vehicle', 'zone', 'slot', 'package', 'qrTicket']))]);
    }

    public function prepay(PrepayRequest $request, ParkingSession $session, ExitService $exits): JsonResponse
    {
        $payment = $exits->prepay($session, $request->user(), $request->validated());

        return (new PaymentResource($payment->load('method')))->response()->setStatusCode(201);
    }

    public function confirmLeft(Request $request, ParkingSession $session, ExitService $exits): ParkingSessionResource
    {
        return new ParkingSessionResource($exits->confirmVehicleLeft($session, $request->user()));
    }
}
