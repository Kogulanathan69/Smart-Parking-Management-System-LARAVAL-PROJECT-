<?php

namespace App\Services;

use App\Contracts\PlateRecognizer;
use App\Exceptions\ParkingException;
use App\Models\PackageRate;
use App\Models\ParkingPackage;
use App\Models\ParkingSession;
use App\Models\ParkingZone;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleCategory;
use App\Support\Reference;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EntryService
{
    public function __construct(
        private VehicleService $vehicles,
        private SlotAllocator $allocator,
        private PricingService $pricing,
        private QrTicketService $tickets,
        private SafetyService $safety,
        private PlateRecognizer $plateRecognizer,
        private AuditLogger $audit,
    ) {}

    /**
     * Staff-recorded vehicle entry (BRD §4). Returns the session and the printable QR payload.
     *
     * @param  array{plate_number?: ?string, fuel_type?: ?string, vehicle_category_id: int, parking_zone_id: int, parking_package_id: int, parking_slot_id?: ?int, customer_id?: ?int, customer_name?: ?string, customer_phone?: ?string, notes?: ?string, entry_source?: ?string}  $data
     * @return array{0: ParkingSession, 1: string}
     */
    public function recordEntry(User $staff, array $data): array
    {
        $category = VehicleCategory::where('is_active', true)->findOrFail($data['vehicle_category_id']);
        $zone = ParkingZone::with('zoneType')->where('is_active', true)->findOrFail($data['parking_zone_id']);
        $package = ParkingPackage::where('is_active', true)->findOrFail($data['parking_package_id']);
        $plate = $this->plateRecognizer->recognize(null, $data['plate_number'] ?? null)['plate'];

        if ($package->isAddon()) {
            throw new ParkingException('Add-on packages cannot be selected as the main package.', 'invalid_package');
        }
        if (! $package->allowedInZoneType($zone->zone_type_id)) {
            throw new ParkingException('The selected package is not offered in this zone.', 'package_zone_mismatch');
        }
        if (! $zone->acceptsCategory($category->id)) {
            throw new ParkingException('This zone does not accept this vehicle type.', 'category_not_allowed');
        }

        $now = now();
        $rate = $package->rateFor($category->id, $now);
        if (! $rate) {
            throw new ParkingException('No price is configured for this package and vehicle type.', 'rate_not_configured');
        }

        $hasSafety = (bool) $zone->zoneType->is_safety;
        $safetyRate = null;
        if ($hasSafety) {
            $safetyPackage = ParkingPackage::where('billing_mode', ParkingPackage::MODE_ADDON)->where('is_active', true)->orderBy('id')->first();
            $safetyRate = $safetyPackage?->rateFor($category->id, $now);
            if (! $safetyRate) {
                throw new ParkingException('No safety parking price is configured for this vehicle type.', 'safety_rate_not_configured');
            }
        }

        $customer = $this->resolveCustomer($data);

        for ($attempt = 1; ; $attempt++) {
            try {
                return $this->persistEntry($staff, $data, $category, $zone, $package, $rate, $safetyRate, $hasSafety, $customer, $plate, $now);
            } catch (UniqueConstraintViolationException $e) {
                $message = $e->getMessage();
                if (str_contains($message, 'open_vehicle_key')) {
                    throw ParkingException::duplicateActiveEntry();
                }
                // Another gate registered the same new vehicle at the same moment: retry once and reuse it.
                if (str_contains($message, 'identifier') && $attempt < 2) {
                    continue;
                }
                throw ParkingException::noSlotAvailable();
            }
        }
    }

    private function persistEntry(User $staff, array $data, VehicleCategory $category, ParkingZone $zone, ParkingPackage $package, PackageRate $rate, ?PackageRate $safetyRate, bool $hasSafety, ?User $customer, ?string $plate, CarbonInterface $now): array
    {

        return DB::transaction(function () use ($staff, $data, $category, $zone, $package, $rate, $safetyRate, $hasSafety, $customer, $plate, $now) {
            $vehicle = $this->vehicles->findOrRegisterAtGate($plate, $category, $staff, null, $data['fuel_type'] ?? null);
            Vehicle::whereKey($vehicle->id)->lockForUpdate()->first();

            if (ParkingSession::where('open_vehicle_key', $vehicle->id)->exists()) {
                throw ParkingException::duplicateActiveEntry();
            }

            if (! $customer && $vehicle->owner_id) {
                $customer = User::whereKey($vehicle->owner_id)->where('role', User::ROLE_CUSTOMER)->first();
            }

            $slot = $this->allocator->allocate($zone, $category->id, $data['parking_slot_id'] ?? null);
            $snapshot = $this->pricing->snapshot($package, $rate, $safetyRate);
            $snapshot['vehicle'] = ['category' => $category->code, 'fuel_type' => $vehicle->fuel_type?->value];

            $session = ParkingSession::create([
                'uuid' => (string) Str::uuid(),
                'reference' => Reference::make('PS'),
                'vehicle_id' => $vehicle->id,
                'vehicle_category_id' => $category->id,
                'fuel_type' => $vehicle->fuel_type,
                'customer_id' => $customer?->id,
                'customer_name' => $data['customer_name'] ?? $customer?->name,
                'customer_phone' => $data['customer_phone'] ?? $customer?->phone,
                'parking_zone_id' => $zone->id,
                'parking_slot_id' => $slot->id,
                'parking_package_id' => $package->id,
                'package_rate_id' => $rate->id,
                'safety_rate_id' => $safetyRate?->id,
                'has_safety' => $hasSafety,
                'status' => ParkingSession::ACTIVE,
                'entry_at' => $now,
                'package_ends_at' => $this->pricing->packageEndsAt($snapshot, $now),
                'entry_staff_id' => $staff->id,
                'pricing_snapshot' => $snapshot,
                'open_vehicle_key' => $vehicle->id,
                'open_slot_key' => $slot->id,
                'entry_source' => $data['entry_source'] ?? 'manual',
                'notes' => $data['notes'] ?? null,
            ]);

            [, $payload] = $this->tickets->issue($session);

            if ($hasSafety) {
                $this->safety->scheduleForSession($session);
            }

            $this->audit->log('session.entry_recorded', $session, [], [
                'vehicle' => $vehicle->identifier, 'category' => $category->code, 'fuel_type' => $vehicle->fuel_type?->value, 'slot' => $slot->code, 'package' => $package->code, 'rate_version' => $rate->version,
            ]);

            return [$session, $payload];
        }, 3);

    }

    private function resolveCustomer(array $data): ?User
    {
        if (! empty($data['customer_id'])) {
            $customer = User::whereKey($data['customer_id'])->where('role', User::ROLE_CUSTOMER)->first();
            if (! $customer || ! $customer->isActive()) {
                throw new ParkingException('Customer account not found or not active.', 'invalid_customer');
            }

            return $customer;
        }
        if (! empty($data['customer_phone'])) {
            return User::where('role', User::ROLE_CUSTOMER)->where('status', User::STATUS_ACTIVE)->where('phone', $data['customer_phone'])->first();
        }

        return null;
    }
}
