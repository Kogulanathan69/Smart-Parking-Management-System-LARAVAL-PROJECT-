<?php

namespace App\Services\Anpr;

use App\Exceptions\ParkingException;
use App\Http\Controllers\Api\Staff\SessionController;
use App\Models\AnprEvent;
use App\Models\ParkingPackage;
use App\Models\ParkingSession;
use App\Models\Payment;
use App\Models\User;
use App\Models\VehicleCategory;
use App\Models\ZoneType;
use App\Services\BillPresenter;
use App\Services\EntryService;
use App\Services\ExitService;
use App\Services\ReportService;
use App\Services\ReservationService;
use App\Support\Anpr\SriLankaPlateNormalizer;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * ANPR-first entry and exit. Recognition only *proposes* a plate; a staff member verifies it and confirms
 * the entry/exit, and the existing EntryService / ExitService enforce every parking, billing and payment rule.
 * Events are single-use (row lock + consumed_at + unique (session, direction)).
 */
class AnprWorkflowService
{
    public function __construct(
        private AnprEventService $events,
        private EntryService $entries,
        private ExitService $exits,
        private ReservationService $reservations,
        private ReportService $reports,
        private BillPresenter $bills,
        private SriLankaPlateNormalizer $normalizer,
    ) {}

    /* ============================== ENTRY ============================== */

    public function entryPreview(AnprEvent $event): array
    {
        $this->assertUsable($event, 'entry');
        $plate = $event->effectivePlate() ?? $event->normalized_plate;
        ['vehicle' => $vehicle, 'suggestions' => $suggestions] = $this->events->lookup($plate);

        $openSession = $vehicle?->openSession;
        $reservation = $vehicle ? $this->reservations->activeFor($vehicle->id) : null;
        $categoryCode = $vehicle?->category?->code;
        $packages = ParkingPackage::with('zoneTypes')->where('is_active', true)->where('billing_mode', '!=', ParkingPackage::MODE_ADDON)->get();

        $zones = collect($this->reports->occupancy())->where('is_active', true)->map(function ($zone) use ($categoryCode, $packages) {
            $row = $categoryCode ? collect($zone['by_vehicle_category'])->firstWhere('vehicle_category', $categoryCode) : null;
            $typeId = ZoneType::where('code', $zone['zone_type'])->value('id');

            return [
                'zone_id' => $zone['zone_id'], 'code' => $zone['code'], 'name' => $zone['name'],
                'available_for_vehicle' => $categoryCode ? ($row['available'] ?? 0) : null,
                'packages' => $packages->filter(fn ($p) => $p->allowedInZoneType($typeId))->map->only(['id', 'code', 'name', 'guarantees_slot'])->values(),
            ];
        })->values();

        return [
            'event' => $this->eventSummary($event),
            'plate' => $plate,
            'requires_verification' => $this->events->requiresExplicitVerification($event),
            'vehicle' => $vehicle ? ['id' => $vehicle->id, 'identifier' => $vehicle->identifier, 'vehicle_category' => $categoryCode, 'fuel_type' => $vehicle->fuel_type?->value, 'owner_id' => $vehicle->owner_id] : null,
            'is_new_vehicle' => ! $vehicle,
            'suggestions' => $suggestions,
            'blocked' => $openSession ? ['error_code' => 'duplicate_active_entry', 'session_reference' => $openSession->reference] : null,
            'active_reservation' => $reservation ? $this->reservations->summary($reservation) + ['is_valid' => $reservation->isActiveAt()] : null,
            'zones' => $zones,
            'vehicle_categories' => VehicleCategory::where('is_active', true)->orderBy('sort_order')->get(['id', 'code', 'name', 'requires_plate', 'is_motorized']),
        ];
    }

    /**
     * @return array{session: ParkingSession, ticket_payload: string, event: AnprEvent}
     */
    public function entryConfirm(AnprEvent $event, User $staff, array $data): array
    {
        $event = $this->ensureVerified($event, $staff, $data['confirm_plate'] ?? null, 'entry');
        $plate = $event->effectivePlate();
        $entryData = array_merge($data, [
            'plate_number' => $plate ? ($this->normalizer->normalize($plate)['display'] ?? $plate) : null,
            'entry_source' => $event->source === 'manual' ? 'manual' : 'anpr',
        ]);
        unset($entryData['confirm_plate'], $entryData['event_uuid']);

        try {
            return DB::transaction(function () use ($event, $staff, $entryData) {
                $locked = AnprEvent::whereKey($event->id)->lockForUpdate()->firstOrFail();
                if ($locked->consumed_at) {
                    throw new ParkingException('This ANPR event has already been used.', 'anpr_event_already_used', 409, ['session_id' => $locked->parking_session_id]);
                }
                [$session, $payload] = $this->entries->recordEntry($staff, $entryData);

                $locked->forceFill(['parking_session_id' => $session->id, 'vehicle_id' => $session->vehicle_id, 'consumed_at' => now()])->save();

                return ['session' => $session, 'ticket_payload' => $payload, 'event' => $locked];
            });
        } catch (UniqueConstraintViolationException $e) {
            if (str_contains($e->getMessage(), 'anpr_events_session_direction_unique')) {
                throw new ParkingException('This session already has an entry event.', 'anpr_event_already_used', 409);
            }
            throw $e;
        }
    }

    /* ============================== EXIT ============================== */

    public function exitPreview(AnprEvent $event, ?string $sessionUuid = null): array
    {
        $this->assertUsable($event, 'exit');
        [$session, $match] = $this->resolveExitSession($event, $sessionUuid, verifiedOnly: false);

        $base = [
            'event' => $this->eventSummary($event),
            'plate' => $event->effectivePlate() ?? $event->normalized_plate,
            'requires_verification' => $this->events->requiresExplicitVerification($event),
            'match' => $match,
        ];
        if (! $session) {
            return $base + ['session' => null, 'bill' => null];
        }
        $session->load(['vehicle', 'zone', 'slot', 'package', 'receipt']);

        return $base + [
            'session' => [
                'uuid' => $session->uuid, 'reference' => $session->reference, 'status' => $session->status,
                'entry_at' => $session->entry_at->toIso8601String(), 'zone' => $session->zone->code, 'slot' => $session->slot->code,
                'package' => $session->package->name, 'reservation_id' => $session->parking_reservation_id,
                'time' => $session->timeStatus(),
            ],
            'bill' => SessionController::billFor($session, $this->exits, $this->bills),
            'requires_exit_inspection' => $session->status === ParkingSession::ACTIVE
                ? $this->exits->buildQuote($session, now())['requires_exit_inspection'] : false,
        ];
    }

    /**
     * Verified exit → authoritative bill → payment → (only then) gate, via the shared settlement core.
     */
    public function exitSettle(AnprEvent $event, User $staff, array $input): array
    {
        // Replays of a completed exit (same idempotency key) are answered by ExitService without side effects.
        if ($event->consumed_at) {
            $payment = Payment::where('idempotency_key', $input['idempotency_key'])->first();
            if (! $payment || $payment->parking_session_id !== $event->parking_session_id) {
                throw new ParkingException('This ANPR event has already been used.', 'anpr_event_already_used', 409);
            }

            return $this->exits->settleSession($staff, $event->session, $input) + ['event' => $event];
        }

        $event = $this->ensureVerified($event, $staff, $input['confirm_plate'] ?? null, 'exit');
        [$session, $match] = $this->resolveExitSession($event, $input['parking_session_uuid'] ?? null, verifiedOnly: true);
        if (! $session) {
            throw new ParkingException('No active parking session matches this vehicle. Check the plate or use manual lookup.', 'no_active_session', 404, ['match' => $match]);
        }

        $result = $this->exits->settleSession($staff, $session, $input);

        if ($result['status'] === 'completed') {
            DB::transaction(function () use ($event, $session) {
                $locked = AnprEvent::whereKey($event->id)->lockForUpdate()->firstOrFail();
                if (! $locked->consumed_at) {
                    try {
                        $locked->forceFill(['parking_session_id' => $session->id, 'vehicle_id' => $session->vehicle_id, 'consumed_at' => now()])->save();
                    } catch (UniqueConstraintViolationException) {
                        // Another exit event was already linked to this session; this one stays unlinked.
                    }
                }
            });
        }

        return $result + ['event' => $event->fresh()];
    }

    /* ============================== shared ============================== */

    /**
     * Verification gate. In "always" mode (and for low-confidence, corrected, unknown-format or unreadable
     * readings) the event must have been verified through /verify first. Otherwise the staff member's
     * explicit confirm_plate equal to the reading counts as verification (recorded in the review trail).
     * In every case confirm_plate must equal the verified plate, so staff cannot act on a stale screen.
     */
    private function ensureVerified(AnprEvent $event, User $staff, ?string $confirmPlate, string $direction): AnprEvent
    {
        $this->assertUsable($event, $direction);
        $confirm = $confirmPlate !== null && $confirmPlate !== '' ? SriLankaPlateNormalizer::identifier($confirmPlate) : null;

        if (! $event->isVerified()) {
            if ($this->events->requiresExplicitVerification($event)) {
                throw new ParkingException('This reading must be verified (confirmed or corrected) before use.', 'verification_required', 409);
            }
            if ($confirm === null || $confirm !== SriLankaPlateNormalizer::identifier((string) $event->normalized_plate)) {
                throw new ParkingException('Confirm the plate exactly as read, or correct it first.', 'plate_confirmation_mismatch', 409);
            }
            $event = $this->events->verify($event, $staff, 'confirm');
        }

        $verified = $event->effectivePlate();
        // Manual "no plate" events have no plate to confirm.
        if ($verified !== null && $confirm !== SriLankaPlateNormalizer::identifier($verified)) {
            throw new ParkingException('The confirmed plate does not match the verified reading.', 'plate_confirmation_mismatch', 409);
        }

        return $event;
    }

    /**
     * Exact match only: verified plate → registered vehicle → its open session. Plate-less manual events
     * may name the session explicitly (authorised manual lookup).
     *
     * @return array{0: ?ParkingSession, 1: array<string, mixed>}
     */
    private function resolveExitSession(AnprEvent $event, ?string $sessionUuid, bool $verifiedOnly): array
    {
        $plate = $verifiedOnly ? $event->effectivePlate() : ($event->effectivePlate() ?? $event->normalized_plate);

        if (! $plate) {
            if ($event->source !== 'manual' || ! $sessionUuid) {
                return [null, ['result' => 'no_plate', 'hint' => 'Use manual identification with the parking session reference.']];
            }
            $session = ParkingSession::where('uuid', $sessionUuid)->whereIn('status', [ParkingSession::ACTIVE, ParkingSession::EXIT_AUTHORIZED])->first();

            return [$session, ['result' => $session ? 'manual_session' : 'no_active_session']];
        }

        ['vehicle' => $vehicle, 'suggestions' => $suggestions] = $this->events->lookup($plate);
        if (! $vehicle) {
            return [null, ['result' => 'unknown_vehicle', 'suggestions' => $suggestions]];
        }
        $session = ParkingSession::where('open_vehicle_key', $vehicle->id)->first()
            ?? ParkingSession::where('vehicle_id', $vehicle->id)->where('status', ParkingSession::EXIT_AUTHORIZED)->first();
        if ($sessionUuid && $session && $session->uuid !== $sessionUuid) {
            throw new ParkingException('The selected session does not belong to this vehicle.', 'session_vehicle_mismatch', 409);
        }

        return [$session, ['result' => $session ? 'matched' : 'no_active_session', 'vehicle_identifier' => $vehicle->identifier]];
    }

    private function assertUsable(AnprEvent $event, string $direction): void
    {
        if ($event->direction !== $direction) {
            throw new ParkingException("This is an {$event->direction} event.", 'anpr_direction_mismatch', 409);
        }
        if ($event->recognition_status === AnprEvent::DUPLICATE) {
            throw new ParkingException('Duplicate reading; use the original event.', 'anpr_event_duplicate', 409, [
                'original_event' => $event->duplicate_of_id ? AnprEvent::find($event->duplicate_of_id)?->uuid : null,
            ]);
        }
        if ($event->verification_status === AnprEvent::REJECTED) {
            throw new ParkingException('This reading was rejected; use manual identification.', 'anpr_event_rejected', 409);
        }
        if ($event->consumed_at) {
            throw new ParkingException('This ANPR event has already been used.', 'anpr_event_already_used', 409);
        }
        if (in_array($event->recognition_status, [AnprEvent::UNREADABLE, AnprEvent::ENGINE_ERROR], true) && ! $event->isVerified()) {
            throw new ParkingException('No usable reading. Correct it or use manual identification.', 'anpr_no_reading', 409);
        }
    }

    private function eventSummary(AnprEvent $event): array
    {
        return [
            'uuid' => $event->uuid, 'direction' => $event->direction, 'source' => $event->source,
            'recognition_status' => $event->recognition_status, 'verification_status' => $event->verification_status,
            'raw_text' => $event->raw_text, 'normalized_plate' => $event->normalized_plate, 'verified_plate' => $event->verified_plate,
            'plate_format' => $event->plate_format, 'confidence_percent' => $event->confidence_bp !== null ? round($event->confidence_bp / 100, 2) : null,
            'candidates' => $event->candidates,
        ];
    }
}
