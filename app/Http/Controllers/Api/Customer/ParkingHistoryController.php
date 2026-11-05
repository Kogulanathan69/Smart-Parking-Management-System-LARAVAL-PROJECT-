<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Api\Staff\SessionController;
use App\Http\Controllers\Controller;
use App\Http\Requests\SessionFilterRequest;
use App\Http\Resources\ParkingSessionResource;
use App\Http\Resources\ReceiptResource;
use App\Models\CustomerCredit;
use App\Models\ParkingSession;
use App\Models\Receipt;
use App\Services\BillPresenter;
use App\Services\ExitService;
use App\Support\SessionFilters;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ParkingHistoryController extends Controller
{
    /**
     * Own sessions only (customer_id scope is applied before any filter).
     */
    public function sessions(SessionFilterRequest $request): AnonymousResourceCollection
    {
        $query = $request->user()->parkingSessions()->with(['vehicle.category', 'vehicleCategory', 'zone', 'slot', 'package', 'receipt'])->getQuery();

        return ParkingSessionResource::collection(
            SessionFilters::apply($query, $request->validated())->latest('entry_at')->orderByDesc('id')->paginate($request->integer('per_page', 20))->withQueryString()
        );
    }

    public function showSession(ParkingSession $session): ParkingSessionResource
    {
        $this->authorize('view', $session);

        return new ParkingSessionResource($session->load(['vehicle.category', 'zone', 'slot', 'package', 'receipt']));
    }

    public function bill(ParkingSession $session, ExitService $exits, BillPresenter $bills): JsonResponse
    {
        $this->authorize('view', $session);

        return response()->json(['data' => SessionController::billFor($session, $exits, $bills)]);
    }

    public function receipts(Request $request): AnonymousResourceCollection
    {
        return ReceiptResource::collection($request->user()->receipts()->with('session.vehicle')->latest('issued_at')->paginate(20));
    }

    public function showReceipt(Receipt $receipt): ReceiptResource
    {
        $this->authorize('view', $receipt);

        return new ReceiptResource($receipt->load('session.vehicle'));
    }

    public function credits(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'balance_minor' => CustomerCredit::balanceFor($user->id),
            'currency' => config('parking.currency'),
            'entries' => $user->credits()->latest()->limit(100)->get(['amount_minor', 'reason', 'expires_at', 'created_at']),
        ]);
    }
}
