<?php

namespace App\Services;

use App\Exceptions\ParkingException;
use App\Models\ParkingSlot;
use App\Models\ParkingZone;
use Illuminate\Support\Facades\DB;

/**
 * Transaction-safe slot allocation. Must be called inside a DB transaction.
 *
 * Double allocation is prevented on three levels:
 *  1. SELECT ... FOR UPDATE on the candidate slot row (MySQL row lock),
 *  2. a compare-and-set UPDATE (status must still be "available"),
 *  3. the unique parking_sessions.open_slot_key index.
 */
class SlotAllocator
{
    private const MAX_ATTEMPTS = 5;

    public function allocate(ParkingZone $zone, int $vehicleCategoryId, ?int $preferredSlotId = null): ParkingSlot
    {
        if (DB::transactionLevel() === 0) {
            throw new \LogicException('Slot allocation must run inside a database transaction.');
        }

        for ($attempt = 0; $attempt < self::MAX_ATTEMPTS; $attempt++) {
            $slot = ParkingSlot::query()
                ->where('parking_zone_id', $zone->id)
                ->where('vehicle_category_id', $vehicleCategoryId)
                ->where('status', ParkingSlot::AVAILABLE)
                ->whereNull('decommissioned_at')
                ->when($preferredSlotId, fn ($q) => $q->whereKey($preferredSlotId))
                ->orderBy('code')
                ->lockForUpdate()
                ->first();

            if (! $slot) {
                throw ParkingException::noSlotAvailable();
            }

            if ($this->claim($slot)) {
                return $slot->refresh();
            }
        }

        throw ParkingException::noSlotAvailable();
    }

    /**
     * Compare-and-set: only one concurrent caller can flip available -> occupied.
     */
    public function claim(ParkingSlot $slot): bool
    {
        return ParkingSlot::whereKey($slot->id)
            ->where('status', ParkingSlot::AVAILABLE)
            ->whereNull('decommissioned_at')
            ->update(['status' => ParkingSlot::OCCUPIED, 'lock_version' => DB::raw('lock_version + 1'), 'updated_at' => now()]) === 1;
    }

    public function release(int $slotId): void
    {
        ParkingSlot::whereKey($slotId)
            ->where('status', ParkingSlot::OCCUPIED)
            ->update([
                'status' => DB::raw("CASE WHEN decommissioned_at IS NULL THEN 'available' ELSE 'unavailable' END"),
                'lock_version' => DB::raw('lock_version + 1'),
                'updated_at' => now(),
            ]);
    }
}
