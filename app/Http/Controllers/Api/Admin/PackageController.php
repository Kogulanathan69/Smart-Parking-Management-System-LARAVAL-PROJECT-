<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\PricingPreviewRequest;
use App\Http\Requests\StorePackageRequest;
use App\Http\Requests\StoreRateRequest;
use App\Http\Requests\UpdatePackageRequest;
use App\Http\Resources\PackageResource;
use App\Http\Resources\RateResource;
use App\Models\ParkingPackage;
use App\Services\AuditLogger;
use App\Services\PricingService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PackageController extends Controller
{
    public function __construct(private PricingService $pricing, private AuditLogger $audit) {}

    public function index(): AnonymousResourceCollection
    {
        return PackageResource::collection(ParkingPackage::with(['zoneTypes', 'rates' => fn ($q) => $q->whereNull('effective_to')])->orderBy('id')->get());
    }

    public function store(StorePackageRequest $request): JsonResponse
    {
        $package = ParkingPackage::create($request->safe()->except('zone_type_ids'));
        $package->zoneTypes()->sync($request->validated('zone_type_ids', []));
        $this->audit->log('package.created', $package, [], $package->toArray() + ['zone_type_ids' => $request->validated('zone_type_ids', [])]);

        return (new PackageResource($package->load('zoneTypes')))->response()->setStatusCode(201);
    }

    public function show(ParkingPackage $package): PackageResource
    {
        return new PackageResource($package->load(['zoneTypes', 'rates' => fn ($q) => $q->whereNull('effective_to')]));
    }

    /**
     * Billing mode and duration are immutable once created (sessions depend on them); create a new package instead.
     */
    public function update(UpdatePackageRequest $request, ParkingPackage $package): PackageResource
    {
        // Only fixed/night packages have a validity period that can hold a slot. Turning the flag off
        // affects new purchases only; existing reservations keep their slot until they end.
        abort_if($request->boolean('guarantees_slot') && ! in_array($package->billing_mode, [ParkingPackage::MODE_FIXED, ParkingPackage::MODE_NIGHT], true),
            422, 'Only fixed-duration or night packages can guarantee a slot.');
        $original = $package->getAttributes();
        $package->update($request->safe()->except('zone_type_ids'));
        if ($request->has('zone_type_ids')) {
            $package->zoneTypes()->sync($request->validated('zone_type_ids'));
        }
        $this->audit->logChanges('package.updated', $package, $original, ['zone_type_ids' => $request->validated('zone_type_ids')]);

        return $this->show($package);
    }

    /**
     * Full rate history (all versions).
     */
    public function rates(ParkingPackage $package): AnonymousResourceCollection
    {
        return RateResource::collection($package->rates()->orderBy('vehicle_category_id')->orderByDesc('version')->get());
    }

    public function storeRate(StoreRateRequest $request, ParkingPackage $package): JsonResponse
    {
        $from = $request->validated('effective_from') ? CarbonImmutable::parse($request->validated('effective_from')) : null;
        $rate = $this->pricing->publishRate($package, $request->integer('vehicle_category_id'), $request->validated(), $request->user(), $from);

        return (new RateResource($rate))->response()->setStatusCode(201);
    }

    /**
     * BRD §14: preview a calculation with the current rates before publishing/using them.
     */
    public function preview(PricingPreviewRequest $request): JsonResponse
    {
        $package = ParkingPackage::findOrFail($request->validated('parking_package_id'));
        $entry = CarbonImmutable::parse($request->validated('entry_at'), config('parking.business_timezone'));
        $exit = CarbonImmutable::parse($request->validated('exit_at'), config('parking.business_timezone'));
        $rate = $package->rateFor($request->integer('vehicle_category_id'), $entry);
        abort_if(! $rate, 422, 'No rate configured for this package and vehicle type at the entry time.');

        $safetyRate = null;
        if ($request->boolean('with_safety')) {
            $safetyRate = ParkingPackage::where('billing_mode', ParkingPackage::MODE_ADDON)->first()?->rateFor($request->integer('vehicle_category_id'), $entry);
        }

        $snapshot = $this->pricing->snapshot($package, $rate, $safetyRate);

        return response()->json(['quote' => $this->pricing->calculate($snapshot, $entry, $exit, (bool) $safetyRate)]);
    }
}
