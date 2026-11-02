<?php

namespace App\Http\Controllers\Api\Staff;

use App\Contracts\TicketPrinter;
use App\Http\Controllers\Controller;
use App\Http\Requests\PrepayRequest;
use App\Http\Requests\SessionFilterRequest;
use App\Http\Resources\InspectionResource;
use App\Http\Resources\ParkingSessionResource;
use App\Http\Resources\PaymentResource;
use App\Models\ParkingSession;
use App\Services\AuditLogger;
use App\Services\BillPresenter;
use App\Services\ExitService;
use App\Services\QrTicketService;
use App\Support\SessionFilters;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SessionController extends Controller
{
    /**
     * Gate session list. Defaults to open sessions (previous behaviour); pass `status` and/or date
     * filters to browse history. All SessionFilters are combinable.
     */
    public function index(SessionFilterRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();
        $filters['status'] ??= [ParkingSession::ACTIVE, ParkingSession::EXIT_AUTHORIZED];

        $query = SessionFilters::apply(ParkingSession::query()->with(['vehicle.category', 'vehicleCategory', 'zone', 'slot', 'package']), $filters);

        return ParkingSessionResource::collection($query->latest('entry_at')->orderByDesc('id')->paginate($request->integer('per_page', 25))->withQueryString());
    }

    /**
     * Open sessions whose fixed package ends within `within` minutes (default 60), plus expired ones.
     */
    public function expiring(Request $request): AnonymousResourceCollection
    {
        $within = (int) ($request->validate(['within' => ['nullable', 'integer', 'min:1', 'max:10080']])['within'] ?? 60);

        return ParkingSessionResource::collection(ParkingSession::with(['vehicle.category', 'vehicleCategory', 'zone', 'slot', 'package'])
            ->where('status', ParkingSession::ACTIVE)
            ->whereNotNull('package_ends_at')
            ->where('package_ends_at', '<=', now()->addMinutes($within))
            ->orderBy('package_ends_at')
            ->paginate(50));
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

    /**
     * Final bill: live quote for open sessions, the stored settlement for closed ones.
     */
    public function bill(ParkingSession $session, ExitService $exits, BillPresenter $bills): JsonResponse
    {
        return response()->json(['data' => self::billFor($session, $exits, $bills)]);
    }

    public static function billFor(ParkingSession $session, ExitService $exits, BillPresenter $bills): array
    {
        $quote = $session->status === ParkingSession::ACTIVE
            ? $exits->buildQuote($session, now())
            : ($session->charge_breakdown ?? []);

        return $bills->present($session, $quote, $session->receipt?->number);
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
