<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateComplaintRequest;
use App\Http\Requests\UpdateIncidentRequest;
use App\Http\Resources\ComplaintResource;
use App\Http\Resources\IncidentResource;
use App\Http\Resources\RefundResource;
use App\Models\Complaint;
use App\Models\Incident;
use App\Models\Refund;
use App\Notifications\ComplaintUpdatedNotification;
use App\Services\AuditLogger;
use App\Services\SafetyService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

/**
 * Admin handling of incidents, complaints and refunds.
 */
class OperationsController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    public function incidents(Request $request): AnonymousResourceCollection
    {
        return IncidentResource::collection(Incident::with('evidence')
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->latest()->paginate(25));
    }

    public function updateIncident(UpdateIncidentRequest $request, Incident $incident, SafetyService $safety): IncidentResource
    {
        return new IncidentResource($safety->updateIncident($incident, $request->user(), $request->validated()));
    }

    public function complaints(Request $request): AnonymousResourceCollection
    {
        return ComplaintResource::collection(Complaint::query()
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->latest()->paginate(25));
    }

    public function updateComplaint(UpdateComplaintRequest $request, Complaint $complaint): ComplaintResource
    {
        $this->authorize('update', $complaint);
        $original = $complaint->getAttributes();
        $complaint->fill($request->validated());
        if (in_array($complaint->status, ['resolved', 'closed'], true) && ! $complaint->resolved_at) {
            $complaint->resolved_at = now();
        }
        $complaint->save();
        $this->audit->logChanges('complaint.updated', $complaint, $original);
        $complaint->customer->notify(new ComplaintUpdatedNotification($complaint));

        return new ComplaintResource($complaint);
    }

    public function refunds(Request $request): AnonymousResourceCollection
    {
        return RefundResource::collection(Refund::query()
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->latest()->paginate(25));
    }

    public function decideRefund(Request $request, Refund $refund): RefundResource
    {
        $data = $request->validate(['decision' => ['required', 'in:approve,reject,mark_paid']]);

        return new RefundResource(DB::transaction(function () use ($refund, $data, $request) {
            $locked = Refund::whereKey($refund->id)->lockForUpdate()->firstOrFail();
            $allowed = ['approve' => [Refund::PENDING], 'reject' => [Refund::PENDING], 'mark_paid' => [Refund::APPROVED]][$data['decision']];
            abort_unless(in_array($locked->status, $allowed, true), 409, 'Refund cannot move to that state.');

            $original = $locked->getAttributes();
            $locked->forceFill(match ($data['decision']) {
                'approve' => ['status' => Refund::APPROVED, 'approved_by' => $request->user()->id, 'approved_at' => now()],
                'reject' => ['status' => Refund::REJECTED, 'approved_by' => $request->user()->id, 'approved_at' => now()],
                'mark_paid' => ['status' => Refund::PAID],
            })->save();
            $this->audit->logChanges('refund.'.$data['decision'], $locked, $original);

            return $locked;
        }));
    }
}
