<?php

namespace App\Http\Controllers\Api\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEvidenceRequest;
use App\Http\Requests\StoreIncidentRequest;
use App\Http\Requests\StoreInspectionRequest;
use App\Http\Resources\EvidenceResource;
use App\Http\Resources\IncidentResource;
use App\Http\Resources\InspectionResource;
use App\Models\EvidenceFile;
use App\Models\Incident;
use App\Models\ParkingSession;
use App\Models\SafetyInspection;
use App\Services\EvidenceService;
use App\Services\SafetyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SafetyController extends Controller
{
    public function due(Request $request): AnonymousResourceCollection
    {
        $due = SafetyInspection::with('session')
            ->where('status', SafetyInspection::SCHEDULED)
            ->where('scheduled_for', '<=', now()->addHours((int) $request->query('hours', 2)))
            ->orderBy('scheduled_for')
            ->paginate(50);

        return InspectionResource::collection($due);
    }

    public function history(ParkingSession $session): AnonymousResourceCollection
    {
        return InspectionResource::collection($session->inspections()->with(['evidence', 'session'])->orderBy('scheduled_for')->get());
    }

    public function storeInspection(StoreInspectionRequest $request, ParkingSession $session, SafetyService $safety): JsonResponse
    {
        $inspection = $safety->recordInspection($session, $request->user(), $request->validated());

        return (new InspectionResource($inspection->load('session')))->response()->setStatusCode(201);
    }

    public function storeIncident(StoreIncidentRequest $request, SafetyService $safety): JsonResponse
    {
        $data = $request->validated();
        if (! empty($data['parking_session_uuid'])) {
            $data['parking_session_id'] = ParkingSession::where('uuid', $data['parking_session_uuid'])->value('id');
        }
        $incident = $safety->reportIncident($request->user(), $data);

        return (new IncidentResource($incident))->response()->setStatusCode(201);
    }

    public function storeEvidence(StoreEvidenceRequest $request, EvidenceService $evidence): JsonResponse
    {
        $model = match ($request->validated('attachable_type')) {
            'inspection' => SafetyInspection::findOrFail($request->validated('attachable_id')),
            'incident' => Incident::findOrFail($request->validated('attachable_id')),
        };
        $file = $evidence->store($model, $request->file('file'), $request->user());

        return (new EvidenceResource($file))->response()->setStatusCode(201);
    }

    public function showEvidence(EvidenceFile $evidence, EvidenceService $service)
    {
        $this->authorize('view', $evidence);

        return $service->download($evidence);
    }
}
