<?php

namespace App\Http\Controllers\Api\Staff;

use App\Http\Controllers\Controller;
use App\Models\ParkingSession;
use App\Models\PenaltyType;
use App\Models\SessionPenalty;
use App\Services\AuditLogger;
use App\Services\PenaltyService;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PenaltyController extends Controller
{
    public function __construct(private PenaltyService $penalties, private AuditLogger $audit) {}

    /* ---------- staff: apply / list on a session ---------- */

    public function index(ParkingSession $session): JsonResponse
    {
        return response()->json(['data' => $session->penalties()->with('type')->orderBy('id')->get()->map(fn ($p) => $this->present($p))]);
    }

    public function apply(Request $request, ParkingSession $session): JsonResponse
    {
        $data = $request->validate([
            'penalty_type_id' => ['required', 'integer', 'exists:penalty_types,id'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);
        $penalty = $this->penalties->apply($session, PenaltyType::findOrFail($data['penalty_type_id']), $data['reason'], $request->user());

        return response()->json(['data' => $this->present($penalty->load('type'))], 201);
    }

    /* ---------- admin: waive / catalogue ---------- */

    public function waive(Request $request, SessionPenalty $penalty): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:500']]);

        return response()->json(['data' => $this->present($this->penalties->waive($penalty, $data['reason'], $request->user())->load('type'))]);
    }

    public function types(Request $request): JsonResponse
    {
        return response()->json(['data' => PenaltyType::query()
            ->when(! $request->boolean('include_inactive'), fn ($q) => $q->where('is_active', true))
            ->orderBy('name')->get()]);
    }

    public function storeType(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:40', 'alpha_dash', 'lowercase', Rule::unique('penalty_types', 'code')],
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'amount_minor' => ['required', 'integer', 'min:1', 'max:100000000000'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        $type = PenaltyType::create($data + ['updated_by' => $request->user()->id]);
        $this->audit->log('penalty_type.created', $type, [], $type->toArray());

        return response()->json(['data' => $type], 201);
    }

    /**
     * Changing the amount affects only penalties applied afterwards (applied rows keep their own amount).
     */
    public function updateType(Request $request, PenaltyType $type): JsonResponse
    {
        $data = $request->validate([
            'code' => ['prohibited'],
            'name' => ['sometimes', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'amount_minor' => ['sometimes', 'integer', 'min:1', 'max:100000000000'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        $original = $type->getAttributes();
        $type->update($data + ['updated_by' => $request->user()->id]);
        $this->audit->logChanges('penalty_type.updated', $type, $original);

        return response()->json(['data' => $type]);
    }

    public static function present(SessionPenalty $penalty): array
    {
        return [
            'id' => $penalty->id,
            'type' => $penalty->type?->only(['id', 'code', 'name']),
            'amount_minor' => $penalty->amount_minor,
            'amount' => Money::format($penalty->amount_minor),
            'reason' => $penalty->reason,
            'status' => $penalty->status,
            'applied_by' => $penalty->applied_by,
            'applied_at' => $penalty->applied_at?->toIso8601String(),
            'waived_by' => $penalty->waived_by,
            'waived_at' => $penalty->waived_at?->toIso8601String(),
            'waiver_reason' => $penalty->waiver_reason,
        ];
    }
}
