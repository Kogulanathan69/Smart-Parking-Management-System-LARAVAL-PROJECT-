<?php

namespace App\Services;

use App\Exceptions\ParkingException;
use App\Models\Incident;
use App\Models\ParkingSession;
use App\Models\SafetyInspection;
use App\Models\User;
use App\Notifications\DamageReportedNotification;
use App\Support\Reference;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Safety / premium parking (BRD §9): entry & exit condition records,
 * routine inspections at a configurable frequency, damage incidents and alerts.
 */
class SafetyService
{
    public function __construct(private SettingsService $settings, private AuditLogger $audit) {}

    /**
     * Create the entry inspection and the routine schedule for the next 24 hours.
     */
    public function scheduleForSession(ParkingSession $session): void
    {
        SafetyInspection::create([
            'parking_session_id' => $session->id,
            'type' => SafetyInspection::TYPE_ENTRY,
            'status' => SafetyInspection::SCHEDULED,
            'scheduled_for' => $session->entry_at,
        ]);
        $this->scheduleRoutine($session);
    }

    /**
     * Ensure routine inspections exist for the next 24 hours. Idempotent: safe to run repeatedly.
     */
    public function scheduleRoutine(ParkingSession $session): int
    {
        $perDay = max(1, (int) $this->settings->get('safety.inspections_per_day'));
        $interval = intdiv(1440, $perDay);
        $last = $session->inspections()->where('type', SafetyInspection::TYPE_ROUTINE)->max('scheduled_for');
        $next = $last ? CarbonImmutable::parse($last)->addMinutes($interval) : CarbonImmutable::instance($session->entry_at)->addMinutes($interval);
        $horizon = now()->addDay();
        $created = 0;

        while ($next <= $horizon) {
            SafetyInspection::create([
                'parking_session_id' => $session->id,
                'type' => SafetyInspection::TYPE_ROUTINE,
                'status' => SafetyInspection::SCHEDULED,
                'scheduled_for' => $next,
            ]);
            $next = $next->addMinutes($interval);
            $created++;
        }

        return $created;
    }

    /**
     * Record an inspection result. Creates the inspection if it was not scheduled (e.g. exit check).
     *
     * @param  array{type: string, condition: string, notes?: ?string, new_damage_found?: bool, safety_inspection_id?: ?int}  $data
     */
    public function recordInspection(ParkingSession $session, User $staff, array $data): SafetyInspection
    {
        if (! $session->has_safety) {
            throw new ParkingException('Inspections apply to safety parking sessions only.', 'not_safety_session');
        }
        if (! $session->isOpen()) {
            throw new ParkingException('This session is closed.', 'invalid_session_state', 409);
        }

        return DB::transaction(function () use ($session, $staff, $data) {
            $inspection = ! empty($data['safety_inspection_id'])
                ? $session->inspections()->whereKey($data['safety_inspection_id'])->lockForUpdate()->firstOrFail()
                : $session->inspections()->where('type', $data['type'])->where('status', SafetyInspection::SCHEDULED)->orderBy('scheduled_for')->lockForUpdate()->first();

            if ($inspection && $inspection->status === SafetyInspection::COMPLETED) {
                throw new ParkingException('This inspection has already been recorded.', 'inspection_completed', 409);
            }

            $inspection ??= new SafetyInspection(['parking_session_id' => $session->id, 'type' => $data['type']]);
            $inspection->fill([
                'status' => SafetyInspection::COMPLETED,
                'performed_at' => now(),
                'inspected_by' => $staff->id,
                'condition' => $data['condition'],
                'notes' => $data['notes'] ?? null,
                'new_damage_found' => (bool) ($data['new_damage_found'] ?? false),
            ])->save();

            $this->audit->log('safety.inspection_recorded', $inspection, [], $inspection->only(['type', 'condition', 'new_damage_found']));

            if ($inspection->new_damage_found) {
                $this->reportIncident($staff, [
                    'parking_session_id' => $session->id,
                    'safety_inspection_id' => $inspection->id,
                    'type' => 'damage',
                    'severity' => $data['condition'] === 'major_damage' ? 'high' : 'medium',
                    'description' => $data['notes'] ?? 'New damage found during '.$inspection->type.' inspection.',
                ]);
            }

            return $inspection;
        });
    }

    public function reportIncident(User $reporter, array $data): Incident
    {
        $session = isset($data['parking_session_id']) ? ParkingSession::find($data['parking_session_id']) : null;

        $incident = Incident::create([
            'reference' => Reference::make('INC'),
            'parking_session_id' => $session?->id,
            'vehicle_id' => $data['vehicle_id'] ?? $session?->vehicle_id,
            'safety_inspection_id' => $data['safety_inspection_id'] ?? null,
            'reported_by' => $reporter->id,
            'type' => $data['type'],
            'severity' => $data['severity'] ?? 'medium',
            'description' => $data['description'],
            'status' => 'open',
        ]);
        $this->audit->log('incident.reported', $incident, [], $incident->only(['reference', 'type', 'severity']));

        if ($incident->type === 'damage') {
            DB::afterCommit(fn () => $this->notifyDamage($incident));
        }

        return $incident;
    }

    public function updateIncident(Incident $incident, User $by, array $data): Incident
    {
        $original = $incident->getAttributes();
        $incident->fill(array_intersect_key($data, array_flip(['status', 'resolution', 'compensation_minor', 'severity'])));
        if (in_array($incident->status, ['resolved', 'closed'], true) && ! $incident->resolved_at) {
            $incident->forceFill(['resolved_by' => $by->id, 'resolved_at' => now()]);
        }
        $incident->save();
        $this->audit->logChanges('incident.updated', $incident, $original);

        return $incident;
    }

    /**
     * Mark overdue routine inspections as missed (run by the scheduler).
     */
    public function markMissed(int $graceMinutes = 60): int
    {
        return SafetyInspection::where('status', SafetyInspection::SCHEDULED)
            ->where('type', SafetyInspection::TYPE_ROUTINE)
            ->where('scheduled_for', '<', now()->subMinutes($graceMinutes))
            ->update(['status' => SafetyInspection::MISSED]);
    }

    private function notifyDamage(Incident $incident): void
    {
        $customer = $incident->session?->customer;
        $customer?->notify(new DamageReportedNotification($incident, forCustomer: true));

        if ($this->settings->get('notifications.notify_admins_on_damage')) {
            $admins = User::whereIn('role', [User::ROLE_ADMIN, User::ROLE_SUPER_ADMIN])->where('status', User::STATUS_ACTIVE)->get();
            Notification::send($admins, new DamageReportedNotification($incident, forCustomer: false));
        }
    }
}
