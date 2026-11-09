<?php

namespace App\Services;

use App\Exceptions\ParkingException;
use App\Models\Attendance;
use App\Models\Incident;
use App\Models\ParkingSession;
use App\Models\ParkingZone;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\SafetyInspection;
use App\Models\SessionPenalty;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * Read-only reporting. Date ranges are interpreted in business-local time (Asia/Colombo)
 * and converted to UTC for queries; grouping by local date is done in PHP so it works on MySQL and SQLite.
 */
class ReportService
{
    public function __construct(private ZoneService $zones) {}

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function range(?string $from, ?string $to): array
    {
        $tz = config('parking.business_timezone');
        $start = $from ? CarbonImmutable::parse($from, $tz)->startOfDay() : CarbonImmutable::now($tz)->startOfDay()->subDays(6);
        $end = $to ? CarbonImmutable::parse($to, $tz)->endOfDay() : CarbonImmutable::now($tz)->endOfDay();

        return [$start->utc(), $end->utc()];
    }

    public function occupancy(): array
    {
        $byZone = $this->zones->occupancyFor();

        return ParkingZone::with('zoneType')->orderBy('code')->get()->map(function (ParkingZone $zone) use ($byZone) {
            $categories = array_values($byZone[$zone->id] ?? []);
            $sum = fn (string $k) => array_sum(array_column($categories, $k));

            return [
                'zone_id' => $zone->id, 'code' => $zone->code, 'name' => $zone->name, 'zone_type' => $zone->zoneType->code, 'is_active' => $zone->is_active,
                'total' => $sum('total'), 'available' => $sum('available'), 'occupied' => $sum('occupied'),
                'reserved' => $sum('reserved'), 'unavailable' => $sum('unavailable'), 'by_vehicle_category' => $categories,
            ];
        })->all();
    }

    public function financial(?string $from, ?string $to): array
    {
        [$start, $end] = $this->range($from, $to);
        $tz = config('parking.business_timezone');
        $payments = Payment::with('method')->where('status', Payment::PAID)->whereBetween('paid_at', [$start, $end])->get();

        $groupedByDay = $payments->groupBy(fn (Payment $p) => $p->paid_at->setTimezone($tz)->format('Y-m-d'))
            ->map(fn ($group, $day) => ['date' => $day, 'count' => $group->count(), 'amount_minor' => (int) $group->sum('amount_minor')])
            ->sortKeys();
        $byDay = collect();
        for ($day = $start->setTimezone($tz)->startOfDay(); $day <= $end->setTimezone($tz); $day = $day->addDay()) {
            $key = $day->toDateString();
            $byDay->push($groupedByDay->get($key, ['date' => $key, 'count' => 0, 'amount_minor' => 0]));
        }
        $byMethod = $payments->groupBy(fn (Payment $p) => $p->method->code)
            ->map(fn ($group, $method) => ['method' => $method, 'count' => $group->count(), 'amount_minor' => (int) $group->sum('amount_minor')])->values();

        $refunds = Refund::whereBetween('created_at', [$start, $end])->get();

        return [
            'currency' => config('parking.currency'),
            'from' => $start->setTimezone($tz)->toDateString(),
            'to' => $end->setTimezone($tz)->toDateString(),
            'revenue_minor' => (int) $payments->sum('amount_minor'),
            'payment_count' => $payments->count(),
            'by_day' => $byDay->all(),
            'by_method' => $byMethod->all(),
            'refunds_minor' => (int) $refunds->whereIn('status', [Refund::APPROVED, Refund::PAID])->sum('amount_minor'),
            'refunds_pending' => $refunds->where('status', Refund::PENDING)->count(),
            'penalties' => $this->penaltySummary($start, $end),
            'pending_payments' => Payment::where('status', Payment::PENDING)->count(),
            'failed_payments' => Payment::where('status', Payment::FAILED)->whereBetween('created_at', [$start, $end])->count(),
        ];
    }

    /**
     * Penalties applied in the range (by applied_at), split into applied and waived.
     *
     * @return array{applied_count: int, applied_minor: int, waived_count: int, waived_minor: int, by_type: list<array{code: string, count: int, amount_minor: int}>}
     */
    public function penaltySummary(CarbonImmutable $start, CarbonImmutable $end): array
    {
        $rows = SessionPenalty::with('type')->whereBetween('applied_at', [$start, $end])->get();
        $applied = $rows->where('status', SessionPenalty::APPLIED);
        $waived = $rows->where('status', SessionPenalty::WAIVED);

        return [
            'applied_count' => $applied->count(),
            'applied_minor' => (int) $applied->sum('amount_minor'),
            'waived_count' => $waived->count(),
            'waived_minor' => (int) $waived->sum('amount_minor'),
            'by_type' => $applied->groupBy(fn ($p) => $p->type->code)
                ->map(fn ($g, $code) => ['code' => $code, 'count' => $g->count(), 'amount_minor' => (int) $g->sum('amount_minor')])->values()->all(),
        ];
    }

    /**
     * Per business-local day: entries, exits, sessions still parked at the end of the day,
     * revenue received (by paid_at) and penalties applied (by applied_at).
     *
     * @return array{from: string, to: string, timezone: string, currency: string, days: list<array<string, mixed>>}
     */
    public function calendar(?string $month, ?string $from, ?string $to): array
    {
        $tz = config('parking.business_timezone');
        if ($month) {
            $startLocal = CarbonImmutable::parse($month.'-01', $tz)->startOfMonth();
            $endLocal = $startLocal->endOfMonth();
        } elseif ($from && $to) {
            $startLocal = CarbonImmutable::parse($from, $tz)->startOfDay();
            $endLocal = CarbonImmutable::parse($to, $tz)->endOfDay();
        } else {
            $startLocal = CarbonImmutable::now($tz)->startOfMonth();
            $endLocal = $startLocal->endOfMonth();
        }
        if ($startLocal->diffInDays($endLocal) > 62) {
            throw new ParkingException('The calendar range is limited to 62 days.', 'range_too_large');
        }
        [$start, $end] = [$startLocal->utc(), $endLocal->utc()];
        $day = fn ($instant) => CarbonImmutable::instance($instant)->setTimezone($tz)->toDateString();

        $sessions = ParkingSession::query()
            ->select(['id', 'entry_at', 'exit_at', 'status'])
            ->where('entry_at', '<=', $end)
            ->where(fn ($q) => $q->whereNull('exit_at')->orWhere('exit_at', '>=', $start))
            ->get();
        $payments = Payment::where('status', Payment::PAID)->whereBetween('paid_at', [$start, $end])->get(['amount_minor', 'paid_at']);
        $penalties = SessionPenalty::where('status', SessionPenalty::APPLIED)->whereBetween('applied_at', [$start, $end])->get(['amount_minor', 'applied_at']);

        $days = [];
        for ($cursor = $startLocal; $cursor <= $endLocal; $cursor = $cursor->addDay()) {
            $dayEnd = $cursor->endOfDay()->utc();
            $days[$cursor->toDateString()] = [
                'date' => $cursor->toDateString(),
                'entries' => 0,
                'exits' => 0,
                'parked_at_end_of_day' => $sessions->filter(fn ($s) => $s->entry_at <= $dayEnd && ($s->exit_at === null || $s->exit_at > $dayEnd))->count(),
                'revenue_minor' => 0,
                'penalties_minor' => 0,
            ];
        }
        foreach ($sessions as $s) {
            if ($s->entry_at >= $start && isset($days[$day($s->entry_at)])) {
                $days[$day($s->entry_at)]['entries']++;
            }
            if ($s->exit_at && $s->exit_at <= $end && isset($days[$day($s->exit_at)])) {
                $days[$day($s->exit_at)]['exits']++;
            }
        }
        foreach ($payments as $p) {
            $days[$day($p->paid_at)]['revenue_minor'] += $p->amount_minor;
        }
        foreach ($penalties as $p) {
            $days[$day($p->applied_at)]['penalties_minor'] += $p->amount_minor;
        }

        return [
            'from' => $startLocal->toDateString(),
            'to' => $endLocal->toDateString(),
            'timezone' => $tz,
            'currency' => config('parking.currency'),
            'days' => array_values($days),
        ];
    }

    public function staffPerformance(?string $from, ?string $to): array
    {
        [$start, $end] = $this->range($from, $to);

        return User::where('role', User::ROLE_STAFF)->orderBy('name')->get()->map(function (User $staff) use ($start, $end) {
            $attendance = Attendance::where('user_id', $staff->id)->whereBetween('check_in_at', [$start, $end])->get();

            return [
                'user_id' => $staff->id,
                'name' => $staff->name,
                'status' => $staff->status,
                'entries' => ParkingSession::where('entry_staff_id', $staff->id)->whereBetween('entry_at', [$start, $end])->count(),
                'exits' => ParkingSession::where('exit_staff_id', $staff->id)->whereBetween('exit_at', [$start, $end])->count(),
                'collected_minor' => (int) Payment::where('processed_by', $staff->id)->where('status', Payment::PAID)->whereBetween('paid_at', [$start, $end])->sum('amount_minor'),
                'inspections' => SafetyInspection::where('inspected_by', $staff->id)->whereBetween('performed_at', [$start, $end])->count(),
                'incidents_reported' => Incident::where('reported_by', $staff->id)->whereBetween('created_at', [$start, $end])->count(),
                'shifts_attended' => $attendance->count(),
                'late_check_ins' => $attendance->where('status', 'late')->count(),
            ];
        })->all();
    }

    public function overview(): array
    {
        $tz = config('parking.business_timezone');
        $todayStart = CarbonImmutable::now($tz)->startOfDay()->utc();

        return [
            'occupancy' => $this->occupancy(),
            'active_sessions' => ParkingSession::where('status', ParkingSession::ACTIVE)->count(),
            'entries_today' => ParkingSession::where('entry_at', '>=', $todayStart)->count(),
            'exits_today' => ParkingSession::where('exit_at', '>=', $todayStart)->count(),
            'revenue_today_minor' => (int) Payment::where('status', Payment::PAID)->where('paid_at', '>=', $todayStart)->sum('amount_minor'),
            'open_incidents' => Incident::whereIn('status', ['open', 'investigating'])->count(),
            'overdue_inspections' => SafetyInspection::where('status', SafetyInspection::SCHEDULED)->where('scheduled_for', '<', now())->count(),
            'pending_refunds' => Refund::where('status', Refund::PENDING)->count(),
            'overdue_sessions' => ParkingSession::whereIn('status', [ParkingSession::ACTIVE, ParkingSession::EXIT_AUTHORIZED])
                ->whereNotNull('package_ends_at')->where('package_ends_at', '<', now())->count(),
            'business_date' => CarbonImmutable::now($tz)->toDateString(),
            'timezone' => $tz,
            'users_by_role' => User::query()->selectRaw('role, status, count(*) as total')->groupBy('role', 'status')->get()
                ->map(fn ($r) => ['role' => $r->role, 'status' => $r->status, 'total' => (int) $r->total])->all(),
        ];
    }
}
