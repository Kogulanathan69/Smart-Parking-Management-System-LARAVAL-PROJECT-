<?php

namespace App\Http\Controllers\Api\Report;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAuditFindingRequest;
use App\Http\Resources\AuditLogResource;
use App\Models\AuditFinding;
use App\Models\AuditLog;
use App\Models\Incident;
use App\Models\ParkingSession;
use App\Models\Payment;
use App\Models\SafetyInspection;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AuditController extends Controller
{
    private const SUBJECTS = [
        'session' => ParkingSession::class,
        'payment' => Payment::class,
        'user' => User::class,
        'inspection' => SafetyInspection::class,
        'incident' => Incident::class,
    ];

    public function logs(Request $request): AnonymousResourceCollection
    {
        $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date']]);

        return AuditLogResource::collection(AuditLog::with('actor')
            ->when($request->query('action'), fn ($q, $a) => $q->where('action', 'like', $a.'%'))
            ->when($request->query('actor_id'), fn ($q, $a) => $q->where('actor_id', $a))
            ->when($request->query('from'), fn ($q, $f) => $q->where('created_at', '>=', $f))
            ->when($request->query('to'), fn ($q, $t) => $q->where('created_at', '<=', $t))
            ->latest('id')->paginate(50));
    }

    public function findings(): JsonResponse
    {
        return response()->json(AuditFinding::with('auditor:id,name')->latest()->paginate(25));
    }

    /**
     * Auditors may ADD findings; they never modify original operational records.
     */
    public function storeFinding(StoreAuditFindingRequest $request): JsonResponse
    {
        $data = $request->validated();
        $subjectType = isset($data['subject_type']) ? self::SUBJECTS[$data['subject_type']] : null;
        if ($subjectType) {
            abort_unless($subjectType::whereKey($data['subject_id'])->exists(), 422, 'Referenced record not found.');
        }

        $finding = AuditFinding::create([
            'auditor_id' => $request->user()->id,
            'title' => $data['title'],
            'body' => $data['body'],
            'severity' => $data['severity'] ?? 'info',
            'subject_type' => $subjectType ? (new $subjectType)->getMorphClass() : null,
            'subject_id' => $data['subject_id'] ?? null,
        ]);

        return response()->json(['data' => $finding], 201);
    }
}
