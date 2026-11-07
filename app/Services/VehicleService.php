<?php

namespace App\Services;

use App\Enums\FuelType;
use App\Exceptions\ParkingException;
use App\Models\ParkingReservation;
use App\Models\ParkingSession;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
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
     * A fuel type supplied by staff corrects the stored value (audited).
     */
    public function findOrRegisterAtGate(?string $plate, VehicleCategory $category, User $staff, ?User $owner = null, ?string $fuelType = null): Vehicle
    {
        $fuel = $fuelType !== null ? $this->resolveFuel($category, $fuelType) : null;

        if ($plate === null || trim($plate) === '') {
            if ($category->requires_plate) {
                throw new ParkingException('A licence plate is required for this vehicle category.', 'plate_required');
            }

            return $this->createVehicle(null, $category, $owner, $staff, $fuel);
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
            if ($fuel && $vehicle->fuel_type !== $fuel) {
                $old = $vehicle->fuel_type?->value;
                $vehicle->forceFill(['fuel_type' => $fuel])->save();
                $this->audit->log('vehicle.fuel_type_corrected', $vehicle, ['fuel_type' => $old], ['fuel_type' => $fuel->value]);
            }

            return $vehicle;
        }

        return $this->createVehicle($plate, $category, $owner, $staff, $fuel);
    }

    /**
     * Customer self-registration of their own vehicle.
     */
    public function registerForCustomer(User $customer, array $data): Vehicle
    {
        $category = $this->activeCategory((int) $data['vehicle_category_id']);
        $fuel = $this->resolveFuel($category, $data['fuel_type'] ?? null);
        $plate = $data['plate_number'] ?? null;
        $details = array_intersect_key($data, array_flip(['make', 'model', 'color']));

        if (! $plate && $category->requires_plate) {
            throw new ParkingException('A licence plate is required for this vehicle category.', 'plate_required');
        }

        $identifier = $plate ? Vehicle::normalizePlate($plate) : null;

        try {
            return DB::transaction(function () use ($customer, $category, $fuel, $plate, $details, $identifier) {
                if ($identifier && Vehicle::withTrashed()->where('identifier', $identifier)->lockForUpdate()->exists()) {
                    throw $this->customerVehicleConflict();
                }

                $vehicle = $this->createVehicle($plate, $category, $customer, $customer, $fuel);
                $vehicle->fill($details)->save();

                return $vehicle;
            });
        } catch (UniqueConstraintViolationException $e) {
            if ($this->isIdentifierConflict($e)) {
                throw $this->customerVehicleConflict();
            }

            throw $e;
        }
    }

    /**
     * Remove an eligible vehicle from a customer's account without deleting history.
     *
     * @throws AuthorizationException
     */
    public function unlinkForCustomer(Vehicle $vehicle, User $customer): void
    {
        DB::transaction(function () use ($vehicle, $customer) {
            $locked = Vehicle::whereKey($vehicle->id)->lockForUpdate()->firstOrFail();

            if ($locked->owner_id !== $customer->id) {
                throw new AuthorizationException;
            }
            if (ParkingSession::where('open_vehicle_key', $locked->id)->exists()) {
                throw new ParkingException('This vehicle cannot be removed while it is parked.', 'vehicle_in_active_session', 409);
            }
            if (ParkingReservation::where('active_vehicle_key', $locked->id)->exists()) {
                throw new ParkingException('This vehicle cannot be removed while it has an active reserved space.', 'vehicle_has_reservation', 409);
            }

            $locked->forceFill(['owner_id' => null])->save();
            $this->audit->log('vehicle.unlinked', $locked);
        });
    }

    /**
     * Update vehicle details (customer on own vehicle, or admin).
     * A category change is refused while the vehicle has an open parking session,
     * because the session was priced and allocated for the old category.
     *
     * @param  array{vehicle_category_id?: int, fuel_type?: string, make?: ?string, model?: ?string, color?: ?string, owner_id?: ?int}  $data
     */
    public function update(Vehicle $vehicle, array $data, User $by): Vehicle
    {
        return DB::transaction(function () use ($vehicle, $data, $by) {
            $locked = Vehicle::whereKey($vehicle->id)->lockForUpdate()->firstOrFail();
            $original = $locked->getAttributes();
            $category = $locked->category;

            if (isset($data['vehicle_category_id']) && (int) $data['vehicle_category_id'] !== $locked->vehicle_category_id) {
                if (ParkingSession::where('open_vehicle_key', $locked->id)->exists()) {
                    throw new ParkingException('The vehicle type cannot be changed while the vehicle is parked.', 'vehicle_in_active_session', 409);
                }
                if (ParkingReservation::where('active_vehicle_key', $locked->id)->exists()) {
                    throw new ParkingException('The vehicle type cannot be changed while it holds a reserved slot.', 'vehicle_has_reservation', 409);
                }
                $category = $this->activeCategory((int) $data['vehicle_category_id']);
                if ($category->requires_plate && $locked->is_reference) {
                    throw new ParkingException('This vehicle type requires a licence plate.', 'plate_required');
                }
                $locked->vehicle_category_id = $category->id;
                // Re-validate the fuel type against the new category (e.g. bicycle -> not_applicable).
                $data['fuel_type'] ??= in_array($locked->fuel_type?->value, FuelType::allowedFor($category->is_motorized), true)
                    ? $locked->fuel_type->value
                    : FuelType::defaultFor($category->is_motorized)->value;
            }

            if (array_key_exists('fuel_type', $data)) {
                $locked->fuel_type = $this->resolveFuel($category, $data['fuel_type']);
            }

            if (array_key_exists('owner_id', $data)) {
                if ($data['owner_id'] !== null && ! User::whereKey($data['owner_id'])->where('role', User::ROLE_CUSTOMER)->exists()) {
                    throw new ParkingException('Vehicles can only be linked to customer accounts.', 'invalid_customer');
                }
                $locked->owner_id = $data['owner_id'];
            }

            $locked->fill(array_intersect_key($data, array_flip(['make', 'model', 'color'])))->save();
            $this->audit->logChanges('vehicle.updated', $locked, $original, ['by' => $by->id]);

            return $locked->load('category');
        });
    }

    /**
     * Shared, validated filters for vehicle lists.
     *
     * @param  array{q?: ?string, vehicle_category_id?: ?int, fuel_type?: ?string, owner_id?: ?int}  $filters
     */
    public function applyFilters(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where('identifier', 'like', '%'.Vehicle::normalizePlate($term).'%'))
            ->when($filters['vehicle_category_id'] ?? null, fn ($q, $id) => $q->where('vehicle_category_id', $id))
            ->when($filters['fuel_type'] ?? null, fn ($q, $fuel) => $q->where('fuel_type', $fuel))
            ->when($filters['owner_id'] ?? null, fn ($q, $owner) => $q->where('owner_id', $owner));
    }

    public function resolveFuel(VehicleCategory $category, ?string $fuelType): FuelType
    {
        if ($fuelType === null || $fuelType === '') {
            return FuelType::defaultFor($category->is_motorized);
        }
        $fuel = FuelType::tryFrom($fuelType);
        if (! $fuel || ! in_array($fuel->value, FuelType::allowedFor($category->is_motorized), true)) {
            throw new ParkingException(
                $category->is_motorized
                    ? 'Choose a fuel type for this vehicle (not_applicable is only for non-motorised vehicles).'
                    : 'Non-motorised vehicles must use the fuel type not_applicable.',
                'fuel_type_not_allowed',
                422,
                ['allowed' => FuelType::allowedFor($category->is_motorized)]
            );
        }

        return $fuel;
    }

    private function activeCategory(int $id): VehicleCategory
    {
        $category = VehicleCategory::find($id);
        if (! $category || ! $category->is_active) {
            throw new ParkingException('This vehicle type is not available.', 'category_inactive');
        }

        return $category;
    }

    private function createVehicle(?string $plate, VehicleCategory $category, ?User $owner, User $by, ?FuelType $fuel = null): Vehicle
    {
        $identifier = $plate ? Vehicle::normalizePlate($plate) : $this->uniqueReference($category);

        $vehicle = Vehicle::create([
            'identifier' => $identifier,
            'plate_number' => $plate ? strtoupper(trim($plate)) : null,
            'is_reference' => $plate === null,
            'vehicle_category_id' => $category->id,
            'fuel_type' => $fuel ?? FuelType::defaultFor($category->is_motorized),
            'owner_id' => $owner?->id,
            'registered_by' => $by->id,
        ]);
        $this->audit->log('vehicle.registered', $vehicle, [], ['identifier' => $identifier, 'fuel_type' => $vehicle->fuel_type->value]);

        return $vehicle;
    }

    private function uniqueReference(VehicleCategory $category): string
    {
        do {
            $ref = strtoupper(substr($category->code, 0, 4)).'-'.strtoupper(Str::random(6));
        } while (Vehicle::withTrashed()->where('identifier', $ref)->exists());

        return $ref;
    }

    private function customerVehicleConflict(): ParkingException
    {
        return new ParkingException(
            'This vehicle is already registered. Please contact parking staff for assistance.',
            'vehicle_already_registered',
            409
        );
    }

    private function isIdentifierConflict(UniqueConstraintViolationException $e): bool
    {
        $message = strtolower($e->getMessage());

        return str_contains($message, 'vehicles_identifier_unique')
            || str_contains($message, 'vehicles.identifier');
    }
}
