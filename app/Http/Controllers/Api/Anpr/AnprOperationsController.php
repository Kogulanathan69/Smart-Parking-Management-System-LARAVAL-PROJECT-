<?php

namespace App\Http\Controllers\Api\Anpr;

use App\Http\Controllers\Controller;
use App\Http\Requests\AnprConfirmEntryRequest;
use App\Http\Requests\AnprSettleExitRequest;
use App\Http\Resources\AnprEventResource;
use App\Http\Resources\ParkingSessionResource;
use App\Http\Resources\PaymentResource;
use App\Http\Resources\ReceiptResource;
use App\Models\AnprCamera;
use App\Models\AnprEvent;
use App\Models\AnprEventImage;
use App\Services\Anpr\AnprEventService;
use App\Services\Anpr\AnprWorkflowService;
use App\Services\QrTicketService;
use App\Support\Anpr\SriLankaPlateNormalizer;
use App\Support\Anpr\AnprSchema;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

/**
 * Staff ANPR operations: review events, verify/correct readings, manual identification, ANPR entry and exit.
 */
class AnprOperationsController extends Controller
{
    public function __construct(private AnprEventService $events, private AnprWorkflowService $workflow) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $f = $request->validate([
            'direction' => ['nullable', 'in:entry,exit'],
            'recognition_status' => ['nullable', Rule::in(['recognized', 'low_confidence', 'unreadable', 'engine_error', 'manual', 'duplicate'])],
            'verification_status' => ['nullable', Rule::in(['pending', 'verified', 'corrected', 'rejected'])],
            'camera' => ['nullable', 'string', 'max:40'],
            'plate' => ['nullable', 'string', 'max:30'],
            'unused' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        if (! AnprSchema::events()) {
            return AnprEventResource::collection(new LengthAwarePaginator([], 0, $request->integer('per_page', 25), 1, ['path' => $request->url()]))
                ->additional(['configuration_state' => 'not_configured']);
        }

        return AnprEventResource::collection(AnprEvent::with('camera')
            ->when($f['direction'] ?? null, fn ($q, $v) => $q->where('direction', $v))
            ->when($f['recognition_status'] ?? null, fn ($q, $v) => $q->where('recognition_status', $v))
            ->when($f['verification_status'] ?? null, fn ($q, $v) => $q->where('verification_status', $v))
            ->when($f['camera'] ?? null, fn ($q, $v) => $q->whereHas('camera', fn ($c) => $c->where('code', $v)))
            ->when($f['plate'] ?? null, fn ($q, $v) => $q->where(fn ($w) => $w->where('normalized_plate', 'like', '%'.SriLankaPlateNormalizer::identifier($v).'%')->orWhere('verified_plate', 'like', '%'.SriLankaPlateNormalizer::identifier($v).'%')))
            ->when($request->boolean('unused'), fn ($q) => $q->whereNull('consumed_at'))
            ->latest('captured_at')->orderByDesc('id')
            ->paginate($request->integer('per_page', 25))->withQueryString());
    }

    public function show(AnprEvent $event): AnprEventResource
    {
        return new AnprEventResource($event->load(['camera', 'images', 'reviews']));
    }

    public function image(Request $request, AnprEvent $event, string $image)
    {
        $model = AnprEventImage::where('anpr_event_id', $event->id)->where('uuid', $image)->firstOrFail();

        return $this->events->imageResponse($model, $request->user());
    }

    /**
     * Recognise an image submitted by staff (booth/USB camera or re-capture) with the local engine.
     */
    public function recognize(Request $request): JsonResponse
    {
        $data = $request->validate([
            'direction' => ['required', 'in:entry,exit'],
            'camera_code' => array_values(array_filter([
                'nullable', 'string', AnprSchema::cameras() ? 'exists:anpr_cameras,code' : null,
            ])),
            'image' => ['required', File::image()->types(config('parking.anpr.image_mimes'))->max(config('parking.anpr.image_max_kilobytes'))],
            'idempotency_key' => ['nullable', 'string', 'min:8', 'max:100'],
        ]);
        // Validate the upload first so malformed/oversize files still receive a precise 422.
        // Events and private image references cannot be safely recorded without this optional schema.
        if (! AnprSchema::configured()) {
            return response()->json(AnprSchema::unavailable('ANPR image recognition') + [
                'manual_fallback' => true,
            ], 409);
        }

        $camera = isset($data['camera_code']) ? AnprCamera::where('code', $data['camera_code'])->first() : null;
        $event = $this->events->recognizeUpload($request->user(), $camera, $data['direction'], $request->file('image'), $data['idempotency_key'] ?? null);

        return response()->json(['data' => new AnprEventResource($event->load('images'))], $event->recognition_status === AnprEvent::ENGINE_ERROR ? 503 : 201);
    }

    public function verify(Request $request, AnprEvent $event): AnprEventResource
    {
        $data = $request->validate([
            'action' => ['required', 'in:confirm,correct,reject'],
            'plate_number' => ['nullable', 'required_if:action,correct', 'string', 'max:30'],
            'reason' => ['nullable', 'required_unless:action,confirm', Rule::in(AnprEvent::OVERRIDE_REASONS)],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        return new AnprEventResource($this->events->verify($event, $request->user(), $data['action'], $data['plate_number'] ?? null, $data['reason'] ?? null, $data['notes'] ?? null)->load('reviews'));
    }

    public function manualReview(Request $request): JsonResponse
    {
        $data = $request->validate([
            'direction' => ['required', 'in:entry,exit'],
            'anpr_camera_id' => ['nullable', 'integer', 'exists:anpr_cameras,id'],
            'plate_number' => ['nullable', 'string', 'max:30', 'regex:/^[\pL\pN \-]+$/u'],
            'reason' => ['required', Rule::in(AnprEvent::OVERRIDE_REASONS)],
            'notes' => ['nullable', 'string', 'max:500'],
            'original_event_uuid' => ['nullable', 'uuid', 'exists:anpr_events,uuid'],
        ]);

        return (new AnprEventResource($this->events->createManual($request->user(), $data)->load('reviews')))->response()->setStatusCode(201);
    }

    public function entryPreview(Request $request): JsonResponse
    {
        $data = $request->validate(['event_uuid' => ['required', 'uuid', 'exists:anpr_events,uuid']]);

        return response()->json(['data' => $this->workflow->entryPreview(AnprEvent::where('uuid', $data['event_uuid'])->firstOrFail())]);
    }

    public function entryConfirm(AnprConfirmEntryRequest $request, QrTicketService $tickets): JsonResponse
    {
        $event = AnprEvent::where('uuid', $request->validated('event_uuid'))->firstOrFail();
        $result = $this->workflow->entryConfirm($event, $request->user(), $request->validated());
        $session = $result['session']->load(['vehicle.category', 'zone', 'slot', 'package']);

        return response()->json([
            'data' => new ParkingSessionResource($session),
            'event' => new AnprEventResource($result['event']),
            // Legacy compatibility only; the ANPR workflow does not need the QR ticket.
            'legacy_ticket' => $tickets->printable($session),
            'gate' => ['status' => 'not_requested', 'note' => 'Entry barrier control is a pending client/hardware decision.'],
        ], 201);
    }

    public function exitPreview(Request $request): JsonResponse
    {
        $data = $request->validate([
            'event_uuid' => ['required', 'uuid', 'exists:anpr_events,uuid'],
            'parking_session_uuid' => ['nullable', 'uuid'],
        ]);

        return response()->json(['data' => $this->workflow->exitPreview(AnprEvent::where('uuid', $data['event_uuid'])->firstOrFail(), $data['parking_session_uuid'] ?? null)]);
    }

    public function exitSettle(AnprSettleExitRequest $request): JsonResponse
    {
        $event = AnprEvent::where('uuid', $request->validated('event_uuid'))->firstOrFail();
        $result = $this->workflow->exitSettle($event, $request->user(), $request->validated());
        $completed = $result['status'] === 'completed';

        return response()->json([
            'status' => $result['status'],
            'exit_authorized' => $completed,
            'replayed' => $result['replayed'] ?? false,
            'session' => new ParkingSessionResource($result['session']->load(['vehicle', 'zone', 'slot', 'package'])),
            'quote' => $result['quote'] ?? null,
            'payment' => isset($result['payment']) ? new PaymentResource($result['payment']->load('method')) : null,
            'receipt' => ! empty($result['receipt']) ? new ReceiptResource($result['receipt']) : null,
            'gate' => $result['gate'] ?? null,
            'event' => new AnprEventResource($result['event']),
        ], $completed ? 200 : 202);
    }
}
