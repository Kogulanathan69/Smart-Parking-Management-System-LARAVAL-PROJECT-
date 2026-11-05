<?php

namespace App\Services;

use App\Exceptions\ParkingException;
use App\Models\PackageRate;
use App\Models\ParkingPackage;
use App\Models\ParkingReservation;
use App\Models\ParkingSession;
use App\Models\ParkingSlot;
use App\Models\ParkingZone;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\ReservationPeriod;
use App\Models\SessionReminder;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleCategory;
use App\Support\Reference;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Weekly/monthly guaranteed parking.
 *
 * A reservation owns one slot for its whole validity. Visits (parking sessions) come and go:
 *   first entry  available -> occupied   (reservation + initial period created, billed on that visit)
 *   temp exit    occupied  -> reserved   (only once the vehicle has actually left)
 *   re-entry     reserved  -> occupied   (same slot, same expiry, no new base charge)
 *   expiry       reserved  -> available  (vacant) / stays occupied until departure (vehicle inside)
 *
 * Safety: every state change runs in a transaction, locks the reservation row, flips slot status
 * with compare-and-set updates, and relies on the unique active_vehicle_key / active_slot_key indexes.
 */
class ReservationService
{
    public function __construct(
        private SlotAllocator $allocator,
        private PricingService $pricing,
        private AuditLogger $audit,
    ) {}

    /**
     * The reservation that currently holds ownership for a vehicle (active keys set), optionally row-locked.
     */
    public function activeFor(int $vehicleId, bool $lock = false): ?ParkingReservation
    {
        $found = ParkingReservation::query()->where('active_vehicle_key', $vehicleId)->first();
        if (! $found || ! $lock) {
            return $found;
        }

        // Lock by primary key only once the row is known to exist: a locking read on a missing unique key
        // takes a MySQL gap lock that deadlocks concurrent inserts. The locked read is a "current read",
        // so re-check ownership in case the reservation ended meanwhile.
        $locked = ParkingReservation::whereKey($found->id)->lockForUpdate()->first();

        return $locked && $locked->active_vehicle_key === $vehicleId ? $locked : null;
    }

    /**
     * Create the reservation on first entry. The slot must already be claimed (occupied) by the caller
     * inside the same transaction.
     */
    public function createForEntry(Vehicle $vehicle, ?User $customer, ParkingZone $zone, ParkingSlot $slot, ParkingPackage $package, PackageRate $rate, array $snapshot, CarbonInterface $now): ParkingReservation
    {
        $this->assertInTransaction();
        $expiresAt = $this->pricing->packageEndsAt($snapshot, $now);
        if (! $expiresAt) {
            throw new ParkingException('This package has no fixed validity and cannot hold a guaranteed slot.', 'package_not_reservable');
        }

        $reservation = ParkingReservation::create([
            'vehicle_id' => $vehicle->id,
            'customer_id' => $customer?->id,
            'parking_zone_id' => $zone->id,
            'parking_slot_id' => $slot->id,
            'parking_package_id' => $package->id,
            'package_rate_id' => $rate->id,
            'status' => ParkingReservation::ACTIVE,
            'starts_at' => $now,
            'expires_at' => $expiresAt,
            'pricing_snapshot' => $snapshot,
            'active_vehicle_key' => $vehicle->id,
            'active_slot_key' => $slot->id,
        ]);

        $this->audit->log('reservation.created', $reservation, [], [
            'vehicle' => $vehicle->identifier, 'slot' => $slot->code, 'package' => $package->code,
            'starts_at' => $reservation->starts_at->toIso8601String(), 'expires_at' => $reservation->expires_at->toIso8601String(),
        ]);

        return $reservation;
    }

    /**
     * Record the purchased initial period and bill it on the first visit.
     */
    public function createInitialPeriod(ParkingReservation $reservation, ParkingSession $session, User $by): ReservationPeriod
    {
        return ReservationPeriod::create([
            'parking_reservation_id' => $reservation->id,
            'sequence' => 1,
            'kind' => ReservationPeriod::KIND_INITIAL,
            'starts_at' => $reservation->starts_at,
            'ends_at' => $reservation->expires_at,
            'package_rate_id' => $reservation->package_rate_id,
            'price_minor' => (int) $reservation->pricing_snapshot['rate']['price_minor'],
            'currency' => $reservation->pricing_snapshot['currency'] ?? config('parking.currency'),
            'billed_session_id' => $session->id,
            'status' => ReservationPeriod::BILLED,
            'created_by' => $by->id,
        ]);
    }

    /**
     * Re-entry: validate ownership and compatibility, then flip the owner's slot reserved -> occupied.
     * The reservation must be row-locked by the caller.
     */
    public function claimForReentry(ParkingReservation $reservation, ParkingZone $zone, ParkingPackage $package, VehicleCategory $category, CarbonInterface $now): ParkingSlot
    {
        $this->assertInTransaction();
        if (! $reservation->isActiveAt($now)) {
            throw new ParkingException('This reservation is no longer valid.', 'reservation_expired', 409);
        }
        $context = $this->summary($reservation);
        if ($package->id !== $reservation->parking_package_id) {
            throw new ParkingException('This vehicle holds an active reservation; select its reserved package.', 'reservation_package_mismatch', 409, $context);
        }
        if ($zone->id !== $reservation->parking_zone_id) {
            throw new ParkingException('This vehicle holds a reserved slot in another zone.', 'reservation_zone_mismatch', 409, $context);
        }

        $slot = ParkingSlot::whereKey($reservation->parking_slot_id)->lockForUpdate()->firstOrFail();
        if ($slot->vehicle_category_id !== $category->id) {
            throw new ParkingException('The reserved slot is not suitable for this vehicle type.', 'reservation_category_mismatch', 409, $context);
        }
        if (! $this->allocator->claim($slot) && ! $this->allocator->claimReserved($slot->id)) {
            // Usually a concurrent re-entry of the same vehicle won the slot: report it as a duplicate entry.
            // (Current read: a plain read may still see this transaction's older snapshot.)
            if (ParkingSession::where('open_vehicle_key', $reservation->vehicle_id)->lockForUpdate()->exists()) {
                throw ParkingException::duplicateActiveEntry();
            }
            throw new ParkingException('The reserved slot is not free. Please contact the parking office.', 'reserved_slot_unavailable', 409, $context);
        }

        return $slot->refresh();
    }

    /**
     * Renewal periods sold while the vehicle was away are billed on the next visit.
     */
    public function attachPendingPeriods(ParkingReservation $reservation, ParkingSession $session): void
    {
        ReservationPeriod::where('parking_reservation_id', $reservation->id)
            ->where('status', ReservationPeriod::PENDING)
            ->update(['status' => ReservationPeriod::BILLED, 'billed_session_id' => $session->id, 'updated_at' => now()]);
    }

    /**
     * Called by ExitService once the vehicle has physically departed (settlement or gate confirmation),
     * inside its transaction. Keeps the slot for a still-valid reservation; otherwise releases it.
     */
    public function handleDeparture(ParkingSession $session, CarbonInterface $now): void
    {
        $this->assertInTransaction();
        $reservation = ParkingReservation::whereKey($session->parking_reservation_id)->lockForUpdate()->first();

        if ($reservation && $reservation->status === ParkingReservation::ACTIVE && $reservation->expires_at->greaterThan($now)) {
            $this->allocator->releaseToReserved($session->parking_slot_id);
            $this->audit->log('reservation.slot_held', $reservation, [], ['slot_id' => $session->parking_slot_id, 'session' => $session->reference]);

            return;
        }

        if ($reservation && $reservation->status === ParkingReservation::ACTIVE) {
            $this->markEnded($reservation, ParkingReservation::EXPIRED, 'Expired; released at departure');
        }
        $this->allocator->release($session->parking_slot_id);
    }

    /**
     * Expire one reservation if it is due. Idempotent and safe to call concurrently.
     * A vacant reserved slot is released; a slot with the vehicle inside stays occupied until departure.
     */
    public function expire(ParkingReservation $reservation, CarbonInterface $now): bool
    {
        return DB::transaction(function () use ($reservation, $now) {
            $locked = ParkingReservation::whereKey($reservation->id)->lockForUpdate()->first();
            if (! $locked || $locked->status !== ParkingReservation::ACTIVE || $locked->expires_at->greaterThan($now)) {
                return false;
            }
            $vehicleInside = ParkingSession::where('open_slot_key', $locked->parking_slot_id)
                ->where('parking_reservation_id', $locked->id)->exists();

            $this->markEnded($locked, ParkingReservation::EXPIRED, $vehicleInside ? 'Expired while vehicle on site' : 'Expired');
            if (! $vehicleInside) {
                ParkingSlot::whereKey($locked->parking_slot_id)->lockForUpdate()->first();
                $this->allocator->releaseReserved($locked->parking_slot_id);
            }

            return true;
        });
    }

    /**
     * Scheduler entry point: expire every reservation due at $now. Each one in its own transaction.
     *
     * @return array{expired: int}
     */
    public function expireDue(?CarbonInterface $now = null): array
    {
        $now ??= now();
        $count = 0;
        ParkingReservation::where('status', ParkingReservation::ACTIVE)
            ->where('expires_at', '<=', $now)
            ->orderBy('id')
            ->chunkById(200, function ($reservations) use ($now, &$count) {
                foreach ($reservations as $reservation) {
                    try {
                        $count += $this->expire($reservation, $now) ? 1 : 0;
                    } catch (\Throwable $e) {
                        Log::error('Reservation expiry failed', ['reservation' => $reservation->id, 'error' => $e->getMessage()]);
                    }
                }
            });

        return ['expired' => $count];
    }

    /**
     * Renew for one more package period, starting at the current expiry (never resets ownership).
     * Billed on the visit in progress, or on the next visit if the vehicle is away.
     */
    public function renew(ParkingReservation $reservation, User $by, ?CarbonInterface $now = null): ReservationPeriod
    {
        $now ??= now();

        return DB::transaction(function () use ($reservation, $by, $now) {
            $locked = ParkingReservation::with(['package', 'slot'])->whereKey($reservation->id)->lockForUpdate()->firstOrFail();
            if (! $locked->isActiveAt($now)) {
                throw new ParkingException('Only an active, unexpired reservation can be renewed. Start a new package at the next entry.', 'reservation_not_renewable', 409);
            }
            if (ReservationPeriod::where('parking_reservation_id', $locked->id)->where('status', ReservationPeriod::PENDING)->exists()) {
                throw new ParkingException('A renewal is already waiting to be billed on the next visit.', 'renewal_pending', 409);
            }
            $package = $locked->package;
            if (! $package->is_active || ! $package->guaranteesSlot()) {
                throw new ParkingException('This package is no longer offered for guaranteed parking.', 'package_not_reservable', 409);
            }
            $rate = $package->rateFor($locked->slot->vehicle_category_id, $now);
            if (! $rate) {
                throw new ParkingException('No price is configured for this package and vehicle type.', 'rate_not_configured');
            }

            $open = $this->lockOpenSession($locked);
            $start = $locked->expires_at;
            $end = $this->pricing->packageEndsAt($this->pricing->snapshot($package, $rate, null), $start);

            $period = ReservationPeriod::create([
                'parking_reservation_id' => $locked->id,
                'sequence' => (int) ReservationPeriod::where('parking_reservation_id', $locked->id)->max('sequence') + 1,
                'kind' => ReservationPeriod::KIND_RENEWAL,
                'starts_at' => $start,
                'ends_at' => $end,
                'package_rate_id' => $rate->id,
                'price_minor' => $rate->price_minor,
                'currency' => $rate->currency,
                'billed_session_id' => $open?->id,
                'status' => $open ? ReservationPeriod::BILLED : ReservationPeriod::PENDING,
                'created_by' => $by->id,
            ]);

            $old = $locked->expires_at->toIso8601String();
            $locked->forceFill(['expires_at' => $end])->save();
            if ($open) {
                $open->forceFill(['package_ends_at' => $end])->save();
                // Allow expiry reminders to fire again for the new expiry.
                SessionReminder::where('parking_session_id', $open->id)->delete();
            }

            $this->audit->log('reservation.renewed', $locked, ['expires_at' => $old], [
                'expires_at' => $end->toIso8601String(), 'period' => $period->sequence, 'price_minor' => $period->price_minor,
                'billed_on' => $open?->reference ?? 'next visit',
            ]);

            return $period;
        });
    }

    /**
     * Cancel ownership. Never frees a slot that still contains the vehicle; future (not yet started)
     * periods are voided; refunds are never automatic — an admin may request one explicitly, which
     * then follows the existing refund approval workflow.
     */
    public function cancel(ParkingReservation $reservation, User $by, string $reason, int $refundMinor = 0, ?CarbonInterface $now = null): ParkingReservation
    {
        $now ??= now();

        return DB::transaction(function () use ($reservation, $by, $reason, $refundMinor, $now) {
            $locked = ParkingReservation::whereKey($reservation->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== ParkingReservation::ACTIVE) {
                throw new ParkingException('This reservation is not active.', 'reservation_not_active', 409);
            }
            $open = $this->lockOpenSession($locked);

            $voided = ReservationPeriod::where('parking_reservation_id', $locked->id)
                ->where('starts_at', '>', $now)
                ->where(fn ($q) => $q->where('status', ReservationPeriod::PENDING)
                    ->orWhere(fn ($b) => $b->where('status', ReservationPeriod::BILLED)->where('billed_session_id', $open?->id ?? 0)))
                ->update(['status' => ReservationPeriod::VOID, 'updated_at' => now()]);

            $refund = $refundMinor > 0 ? $this->requestRefund($locked, $refundMinor, $reason, $by) : null;

            $this->markEnded($locked, ParkingReservation::CANCELLED, $reason, $now);

            if ($open) {
                // Vehicle still inside: keep it occupied; it is released normally at departure.
                // Overtime after cancellation is measured from the end of the last valid period.
                $lastEnd = ReservationPeriod::where('parking_reservation_id', $locked->id)->where('status', '!=', ReservationPeriod::VOID)->max('ends_at');
                $open->forceFill(['package_ends_at' => CarbonImmutable::parse($lastEnd)])->save();
            } else {
                ParkingSlot::whereKey($locked->parking_slot_id)->lockForUpdate()->first();
                $this->allocator->releaseReserved($locked->parking_slot_id);
            }

            $this->audit->log('reservation.cancelled', $locked, ['status' => ParkingReservation::ACTIVE], [
                'status' => ParkingReservation::CANCELLED, 'reason' => $reason, 'voided_periods' => $voided,
                'vehicle_on_site' => (bool) $open, 'refund_requested_minor' => $refund?->amount_minor,
            ]);

            return $locked->refresh();
        });
    }

    /**
     * Compact description used in error contexts and lookups.
     */
    public function summary(ParkingReservation $reservation): array
    {
        $reservation->loadMissing(['slot', 'zone', 'package']);

        return [
            'reservation_id' => $reservation->id,
            'slot' => $reservation->slot?->code,
            'zone_id' => $reservation->parking_zone_id,
            'zone' => $reservation->zone?->code,
            'package_id' => $reservation->parking_package_id,
            'package' => $reservation->package?->code,
            'expires_at' => $reservation->expires_at?->toIso8601String(),
        ];
    }

    private function requestRefund(ParkingReservation $reservation, int $amountMinor, string $reason, User $by): Refund
    {
        $paidPeriods = ReservationPeriod::with('billedSession')->where('parking_reservation_id', $reservation->id)->get()->filter->isPaid();
        $refundable = (int) $paidPeriods->sum('price_minor')
            - (int) Refund::whereIn('parking_session_id', $paidPeriods->pluck('billed_session_id'))->whereIn('status', [Refund::PENDING, Refund::APPROVED, Refund::PAID])->sum('amount_minor');
        if ($amountMinor > $refundable) {
            throw new ParkingException("The refund cannot exceed the refundable paid amount ({$refundable}).", 'refund_exceeds_paid', 422, ['refundable_minor' => $refundable]);
        }

        return Refund::create([
            'reference' => Reference::make('RF'),
            'parking_session_id' => $paidPeriods->sortByDesc('sequence')->first()->billed_session_id,
            'amount_minor' => $amountMinor,
            'status' => Refund::PENDING,
            'reason' => 'Reservation cancelled: '.$reason,
            'requested_by' => $by->id,
        ]);
    }

    /**
     * The open visit for this reservation, locked; refuses changes while a payment awaits confirmation
     * (the amount the customer was asked to pay must not change underneath them).
     */
    private function lockOpenSession(ParkingReservation $reservation): ?ParkingSession
    {
        $open = ParkingSession::where('parking_reservation_id', $reservation->id)
            ->whereIn('status', [ParkingSession::ACTIVE, ParkingSession::EXIT_AUTHORIZED])
            ->lockForUpdate()->first();
        if ($open && $open->payments()->where('status', Payment::PENDING)->exists()) {
            throw new ParkingException('A payment for the current visit is awaiting confirmation.', 'payment_pending', 409);
        }
        if ($open && $open->status === ParkingSession::EXIT_AUTHORIZED) {
            throw new ParkingException('The current visit has already been settled; try again after the vehicle has left.', 'invalid_session_state', 409);
        }

        return $open;
    }

    private function markEnded(ParkingReservation $reservation, string $status, string $note, ?CarbonInterface $at = null): void
    {
        $reservation->forceFill([
            'status' => $status,
            'cancelled_at' => $status === ParkingReservation::CANCELLED ? ($at ?? now()) : $reservation->cancelled_at,
            'active_vehicle_key' => null,
            'active_slot_key' => null,
            'notes' => trim(($reservation->notes ? $reservation->notes."\n" : '').$note),
        ])->save();

        if ($status === ParkingReservation::EXPIRED) {
            $this->audit->log('reservation.expired', $reservation, [], ['note' => $note]);
        }
    }

    private function assertInTransaction(): void
    {
        if (DB::transactionLevel() === 0) {
            throw new \LogicException('Reservation changes must run inside a database transaction.');
        }
    }
}
