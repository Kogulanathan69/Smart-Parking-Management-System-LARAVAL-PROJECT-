<?php

namespace App\Services;

use App\Exceptions\ParkingException;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleCategory;
use Illuminate\Support\Str;

class VehicleService
{
    public function __construct(private AuditLogger $audit) {}

    public function findByPlate(string $plate): ?Vehicle
    {
        return Vehicle::where('identifier', Vehicle::normalizePlate($plate))->first();
    }

    /**
     * Staff-side lookup or registration at the gate.
     * Plate-less vehicles (e.g. bicycles) get a unique generated reference (BRD §4.2).
     */
    public function findOrRegisterAtGate(?string $plate, VehicleCategory $category, User $staff, ?User $owner = null): Vehicle
    {
        if ($plate === null || trim($plate) === '') {
            if ($category->requires_plate) {
                throw new ParkingException('A licence plate is required for this vehicle category.', 'plate_required');
            }

            return $this->createVehicle(null, $category, $owner, $staff);
        }

        $vehicle = Vehicle::withTrashed()->where('identifier', Vehicle::normalizePlate($plate))->first();
        if ($vehicle) {
            if ($vehicle->trashed()) {
                $vehicle->restore();
            }
            if ($vehicle->vehicle_category_id !== $category->id) {
                throw new ParkingException('This plate is registered under a different vehicle category. Please verify the vehicle.', 'category_mismatch', 409, [
                    'registered_category' => $vehicle->category->code,
                ]);
            }

            return $vehicle;
        }

        return $this->createVehicle($plate, $category, $owner, $staff);
    }

    /**
     * Customer self-registration of their own vehicle.
     */
    public function registerForCustomer(User $customer, array $data): Vehicle
    {
        $category = VehicleCategory::findOrFail($data['vehicle_category_id']);
        $plate = $data['plate_number'] ?? null;

        if ($plate) {
            $existing = Vehicle::withTrashed()->where('identifier', Vehicle::normalizePlate($plate))->first();
            if ($existing && $existing->owner_id && $existing->owner_id !== $customer->id) {
                // Do not reveal who owns it.
                throw new ParkingException('This vehicle cannot be added to your account. Please contact the parking office.', 'vehicle_unavailable');
            }
            if ($existing) {
                if ($existing->trashed()) {
                    $existing->restore();
                }
                $existing->forceFill(['owner_id' => $customer->id] + array_intersect_key($data, array_flip(['make', 'model', 'color'])))->save();
                $this->audit->log('vehicle.claimed', $existing, [], ['owner_id' => $customer->id]);

                return $existing;
            }
        } elseif ($category->requires_plate) {
            throw new ParkingException('A licence plate is required for this vehicle category.', 'plate_required');
        }

        $vehicle = $this->createVehicle($plate, $category, $customer, $customer);
        $vehicle->fill(array_intersect_key($data, array_flip(['make', 'model', 'color'])))->save();

        return $vehicle;
    }

    private function createVehicle(?string $plate, VehicleCategory $category, ?User $owner, User $by): Vehicle
    {
        $identifier = $plate ? Vehicle::normalizePlate($plate) : $this->uniqueReference($category);

        $vehicle = Vehicle::create([
            'identifier' => $identifier,
            'plate_number' => $plate ? strtoupper(trim($plate)) : null,
            'is_reference' => $plate === null,
            'vehicle_category_id' => $category->id,
            'owner_id' => $owner?->id,
            'registered_by' => $by->id,
        ]);
        $this->audit->log('vehicle.registered', $vehicle, [], ['identifier' => $identifier]);

        return $vehicle;
    }

    private function uniqueReference(VehicleCategory $category): string
    {
        do {
            $ref = strtoupper(substr($category->code, 0, 4)).'-'.strtoupper(Str::random(6));
        } while (Vehicle::withTrashed()->where('identifier', $ref)->exists());

        return $ref;
    }
}
