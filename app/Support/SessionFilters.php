<?php

namespace App\Support;

use App\Models\ParkingSession;
use App\Models\Payment;
use App\Models\Vehicle;
use App\Models\VehicleCategory;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * One implementation of the parking-record filters, shared by staff, report and customer lists.
 * Dates are business-local (Asia/Colombo) calendar days converted to UTC instants.
 * Category and fuel filters use the values recorded on the session at entry (historical truth).
 */
final class SessionFilters
{
    /** entry = entered that day; exit = exited that day; present = parked at any time during the day. */
    public const DATE_FIELDS = ['entry', 'exit', 'present'];

    /** paid/pending/failed/refunded = has a payment in that state; unpaid = no paid payment at all. */
    public const PAYMENT_STATUSES = ['paid', 'pending', 'failed', 'refunded', 'unpaid'];

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}|null
     */
    public static function range(array $filters): ?array
    {
        $tz = config('parking.business_timezone');
        $from = $filters['date'] ?? $filters['from'] ?? null;
        $to = $filters['date'] ?? $filters['to'] ?? null;
        if (! $from && ! $to) {
            return null;
        }
        $start = $from ? CarbonImmutable::parse($from, $tz)->startOfDay() : CarbonImmutable::parse('2000-01-01', $tz);
        $end = $to ? CarbonImmutable::parse($to, $tz)->endOfDay() : CarbonImmutable::now($tz)->endOfDay();

        return [$start->utc(), $end->utc()];
    }

    public static function apply(Builder $query, array $filters): Builder
    {
        $categoryId = $filters['vehicle_category_id']
            ?? (isset($filters['vehicle_category']) ? VehicleCategory::where('code', $filters['vehicle_category'])->value('id') : null);
        $plate = $filters['plate'] ?? $filters['q'] ?? null;

        $query
            ->when($filters['vehicle_id'] ?? null, fn ($q, $id) => $q->where('parking_sessions.vehicle_id', $id))
            ->when($categoryId, fn ($q, $id) => $q->where('parking_sessions.vehicle_category_id', $id))
            ->when($filters['fuel_type'] ?? null, fn ($q, $fuel) => $q->where('parking_sessions.fuel_type', $fuel))
            ->when($filters['status'] ?? null, fn ($q, $statuses) => $q->whereIn('parking_sessions.status', (array) $statuses))
            ->when($filters['zone_id'] ?? null, fn ($q, $zone) => $q->where('parking_sessions.parking_zone_id', $zone))
            ->when($filters['package_id'] ?? null, fn ($q, $package) => $q->where('parking_sessions.parking_package_id', $package))
            ->when($plate, fn ($q, $p) => $q->whereIn('parking_sessions.vehicle_id', Vehicle::withTrashed()->select('id')->where('identifier', 'like', '%'.Vehicle::normalizePlate($p).'%')));

        if ($range = self::range($filters)) {
            [$start, $end] = $range;
            match ($filters['date_field'] ?? 'entry') {
                'exit' => $query->whereBetween('parking_sessions.exit_at', [$start, $end]),
                'present' => $query->where('parking_sessions.entry_at', '<=', $end)
                    ->where(fn ($q) => $q->whereNull('parking_sessions.exit_at')->orWhere('parking_sessions.exit_at', '>=', $start)),
                default => $query->whereBetween('parking_sessions.entry_at', [$start, $end]),
            };
        }

        if ($payment = $filters['payment_status'] ?? null) {
            $payment === 'unpaid'
                ? $query->whereDoesntHave('payments', fn ($q) => $q->where('status', Payment::PAID))
                : $query->whereHas('payments', fn ($q) => $q->where('status', $payment));
        }

        return $query;
    }

    /**
     * Options for building the filter UI.
     */
    public static function options(): array
    {
        return [
            'primary' => config('parking.session_filters.primary'),
            'available' => config('parking.session_filters.available'),
            'statuses' => [ParkingSession::ACTIVE, ParkingSession::EXIT_AUTHORIZED, ParkingSession::COMPLETED, ParkingSession::CANCELLED],
            'payment_statuses' => self::PAYMENT_STATUSES,
            'date_fields' => self::DATE_FIELDS,
        ];
    }
}
