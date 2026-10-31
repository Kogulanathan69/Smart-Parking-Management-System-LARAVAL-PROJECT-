<?php

namespace App\Services;

use App\Exceptions\ParkingException;
use App\Models\ParkingSlot;
use App\Models\ParkingZone;
use App\Models\VehicleCategory;
use Illuminate\Support\Facades\DB;

class ZoneService
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * Set the number of in-service slots for one vehicle category in a zone.
     * Reducing decommissions available slots only; it can never go below occupied/reserved slots (BRD §5).
     */
    public function setCapacity(ParkingZone $zone, VehicleCategory $category, int $capacity): array
    {
        if ($capacity < 0) {
            throw new ParkingException('Capacity cannot be negative.', 'invalid_capacity');
        }
        if (! $zone->acceptsCategory($category->id)) {
            throw new ParkingException('This zone does not accept the selected vehicle category.', 'category_not_allowed');
        }

        return DB::transaction(function () use ($zone, $category, $capacity) {
            $slots = ParkingSlot::where('parking_zone_id', $zone->id)
                ->where('vehicle_category_id', $category->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $inService = $slots->whereNull('decommissioned_at');
            $current = $inService->count();
            $busy = $inService->whereIn('status', [ParkingSlot::OCCUPIED, ParkingSlot::RESERVED])->count();

            if ($capacity < $busy) {
                throw new ParkingException("Capacity cannot be reduced below the {$busy} occupied or reserved slots.", 'capacity_below_occupied');
            }

            if ($capacity > $current) {
                $needed = $capacity - $current;
                foreach ($slots->whereNotNull('decommissioned_at')->take($needed) as $slot) {
                    $slot->forceFill(['decommissioned_at' => null, 'status' => ParkingSlot::AVAILABLE])->save();
                    $needed--;
                }
                $next = $slots->count() + 1;
                while ($needed > 0) {
                    do {
                        $code = sprintf('%s-%s-%03d', $zone->code, strtoupper(substr($category->code, 0, 3)), $next++);
                    } while (ParkingSlot::where('parking_zone_id', $zone->id)->where('code', $code)->exists());
                    ParkingSlot::create(['parking_zone_id' => $zone->id, 'vehicle_category_id' => $category->id, 'code' => $code]);
                    $needed--;
                }
            } elseif ($capacity < $current) {
                $toRemove = $current - $capacity;
                $removable = $inService->whereIn('status', [ParkingSlot::AVAILABLE, ParkingSlot::UNAVAILABLE])->sortByDesc('id')->take($toRemove);
                foreach ($removable as $slot) {
                    $slot->forceFill(['decommissioned_at' => now(), 'status' => ParkingSlot::UNAVAILABLE])->save();
                }
            }

            $this->audit->log('zone.capacity_changed', $zone, ['capacity' => $current], ['capacity' => $capacity], ['vehicle_category' => $category->code]);

            return $this->occupancy($zone)[$category->code] ?? [];
        });
    }

    public function setSlotStatus(ParkingSlot $slot, string $status, ?string $notes = null): ParkingSlot
    {
        if (! in_array($status, [ParkingSlot::AVAILABLE, ParkingSlot::RESERVED, ParkingSlot::UNAVAILABLE], true)) {
            throw new ParkingException('Slots become occupied only through a vehicle entry.', 'invalid_slot_status');
        }

        return DB::transaction(function () use ($slot, $status, $notes) {
            $locked = ParkingSlot::whereKey($slot->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === ParkingSlot::OCCUPIED || $locked->decommissioned_at) {
                throw new ParkingException('Occupied or decommissioned slots cannot be changed manually.', 'slot_locked', 409);
            }
            $original = $locked->getAttributes();
            $locked->forceFill(['status' => $status, 'notes' => $notes])->save();
            $this->audit->logChanges('slot.status_changed', $locked, $original);

            return $locked;
        });
    }

    /**
     * Live occupancy per vehicle category for one zone.
     *
     * @return array<string, array{vehicle_category: string, total: int, available: int, occupied: int, reserved: int, unavailable: int}>
     */
    public function occupancy(ParkingZone $zone): array
    {
        return $this->occupancyFor([$zone->id])[$zone->id] ?? [];
    }

    /**
     * @param  array<int>|null  $zoneIds
     * @return array<int, array<string, array<string, int|string>>>
     */
    public function occupancyFor(?array $zoneIds = null): array
    {
        $rows = DB::table('parking_slots')
            ->join('vehicle_categories', 'vehicle_categories.id', '=', 'parking_slots.vehicle_category_id')
            ->whereNull('parking_slots.decommissioned_at')
            ->when($zoneIds, fn ($q) => $q->whereIn('parking_zone_id', $zoneIds))
            ->groupBy('parking_zone_id', 'vehicle_categories.code', 'parking_slots.status')
            ->select('parking_zone_id', 'vehicle_categories.code as category', 'parking_slots.status', DB::raw('count(*) as aggregate'))
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $entry = $result[$row->parking_zone_id][$row->category] ??= [
                'vehicle_category' => $row->category, 'total' => 0, 'available' => 0, 'occupied' => 0, 'reserved' => 0, 'unavailable' => 0,
            ];
            $entry[$row->status] += (int) $row->aggregate;
            $entry['total'] += (int) $row->aggregate;
            $result[$row->parking_zone_id][$row->category] = $entry;
        }

        return $result;
    }
}
