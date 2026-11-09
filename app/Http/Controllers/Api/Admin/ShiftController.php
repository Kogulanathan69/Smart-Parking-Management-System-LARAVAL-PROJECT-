<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ApproveLeaveRequest;
use App\Http\Requests\StoreShiftAssignmentRequest;
use App\Http\Requests\StoreShiftRequest;
use App\Http\Resources\AttendanceResource;
use App\Http\Resources\ShiftAssignmentResource;
use App\Models\Attendance;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Services\AuditLogger;
use App\Services\ShiftService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ShiftController extends Controller
{
    public function __construct(private ShiftService $shifts, private AuditLogger $audit) {}

    public function index(): JsonResponse
    {
        return response()->json(['data' => Shift::orderBy('start_time')->get()]);
    }

    public function store(StoreShiftRequest $request): JsonResponse
    {
        $shift = Shift::create($request->validated());
        $this->audit->log('shift.created', $shift, [], $shift->toArray());

        return response()->json(['data' => $shift], 201);
    }

    public function update(StoreShiftRequest $request, Shift $shift): JsonResponse
    {
        $original = $shift->getAttributes();
        $shift->update($request->safe()->except('code'));
        $this->audit->logChanges('shift.updated', $shift, $original);

        return response()->json(['data' => $shift]);
    }

    public function assignments(Request $request): AnonymousResourceCollection
    {
        $request->validate(['from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d']]);
        $from = $request->query('from', now(config('parking.business_timezone'))->toDateString());
        $to = $request->query('to', $from);

        return ShiftAssignmentResource::collection(ShiftAssignment::with(['shift', 'user', 'attendance'])
            ->whereDate('shift_date', '>=', $from)->whereDate('shift_date', '<=', $to)
            ->when($request->query('user_id'), fn ($q, $u) => $q->where('user_id', $u))
            ->orderBy('shift_date')->orderBy('shift_id')->paginate(100));
    }

    public function assign(StoreShiftAssignmentRequest $request): JsonResponse
    {
        $assignment = $this->shifts->assign($request->user(), $request->validated());

        return (new ShiftAssignmentResource($assignment->load(['shift', 'user'])))->response()->setStatusCode(201);
    }

    public function approveLeave(ApproveLeaveRequest $request, ShiftAssignment $assignment): JsonResponse
    {
        $replacement = $this->shifts->approveLeave($assignment, $request->user(), $request->validated('replacement_user_id'));

        return response()->json([
            'assignment' => new ShiftAssignmentResource($assignment->refresh()->load(['shift', 'user'])),
            'replacement' => $replacement ? new ShiftAssignmentResource($replacement->load(['shift', 'user'])) : null,
        ]);
    }

    public function coverage(Request $request): JsonResponse
    {
        $request->validate(['date' => ['nullable', 'date_format:Y-m-d']]);

        return response()->json(['gaps' => $this->shifts->coverageGaps($request->query('date', now(config('parking.business_timezone'))->toDateString()))]);
    }

    public function attendance(Request $request): AnonymousResourceCollection
    {
        return AttendanceResource::collection(Attendance::query()
            ->when($request->query('user_id'), fn ($q, $u) => $q->where('user_id', $u))
            ->latest('check_in_at')->paginate(50));
    }
}
