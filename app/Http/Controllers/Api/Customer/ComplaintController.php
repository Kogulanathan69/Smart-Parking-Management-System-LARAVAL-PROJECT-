<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreComplaintRequest;
use App\Http\Resources\ComplaintResource;
use App\Models\Complaint;
use App\Models\ParkingSession;
use App\Services\AuditLogger;
use App\Support\Reference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ComplaintController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        return ComplaintResource::collection($request->user()->complaints()->latest()->paginate(20));
    }

    public function store(StoreComplaintRequest $request, AuditLogger $audit): JsonResponse
    {
        $sessionId = null;
        if ($uuid = $request->validated('parking_session_uuid')) {
            // Only the customer's own sessions can be referenced.
            $sessionId = ParkingSession::where('uuid', $uuid)->where('customer_id', $request->user()->id)->value('id');
            abort_if(! $sessionId, 422, 'Parking session not found.');
        }

        $complaint = Complaint::create([
            'reference' => Reference::make('CMP'),
            'customer_id' => $request->user()->id,
            'parking_session_id' => $sessionId,
            'subject' => $request->validated('subject'),
            'description' => $request->validated('description'),
            'status' => 'open',
        ]);
        $audit->log('complaint.created', $complaint);

        return (new ComplaintResource($complaint))->response()->setStatusCode(201);
    }

    public function show(Complaint $complaint): ComplaintResource
    {
        $this->authorize('view', $complaint);

        return new ComplaintResource($complaint);
    }
}
