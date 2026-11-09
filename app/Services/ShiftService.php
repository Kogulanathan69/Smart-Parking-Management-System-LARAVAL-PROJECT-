<?php

namespace App\Services;

use App\Exceptions\ParkingException;
use App\Models\Attendance;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ShiftService
{
    public function __construct(private SettingsService $settings, private AuditLogger $audit) {}

    public function assign(User $admin, array $data): ShiftAssignment
    {
        $staff = User::findOrFail($data['user_id']);
        if ($staff->role !== User::ROLE_STAFF || ! $staff->isActive()) {
            throw new ParkingException('Shifts can only be assigned to active staff accounts.', 'invalid_staff');
        }

        $exists = ShiftAssignment::where('user_id', $staff->id)->where('shift_id', $data['shift_id'])->whereDate('shift_date', $data['shift_date'])->exists();
        if ($exists) {
            throw new ParkingException('This staff member is already assigned to that shift.', 'duplicate_assignment', 409);
        }

        $assignment = ShiftAssignment::create([
            'shift_id' => $data['shift_id'],
            'user_id' => $staff->id,
            'shift_date' => $data['shift_date'],
            'is_gate_duty' => $data['is_gate_duty'] ?? true,
            'status' => ShiftAssignment::SCHEDULED,
            'assigned_by' => $admin->id,
            'notes' => $data['notes'] ?? null,
        ]);
        $this->audit->log('shift.assigned', $assignment, [], $assignment->only(['shift_id', 'user_id', 'shift_date']));

        return $assignment;
    }

    /**
     * Approve leave and optionally assign a replacement staff member.
     */
    public function approveLeave(ShiftAssignment $assignment, User $admin, ?int $replacementUserId): ?ShiftAssignment
    {
        return DB::transaction(function () use ($assignment, $admin, $replacementUserId) {
            $original = $assignment->getAttributes();
            $assignment->forceFill(['status' => $replacementUserId ? ShiftAssignment::REPLACED : ShiftAssignment::ON_LEAVE])->save();
            $this->audit->logChanges('shift.leave_approved', $assignment, $original);

            if (! $replacementUserId) {
                return null;
            }
            $replacement = $this->assign($admin, [
                'shift_id' => $assignment->shift_id,
                'user_id' => $replacementUserId,
                'shift_date' => $assignment->shift_date->format('Y-m-d'),
                'is_gate_duty' => $assignment->is_gate_duty,
            ]);
            $replacement->forceFill(['replacement_for_id' => $assignment->id])->save();

            return $replacement;
        });
    }

    /**
     * Shifts on a local date with fewer gate-duty staff than required (BRD §10).
     */
    public function coverageGaps(string $date): array
    {
        return Shift::where('is_active', true)->get()->map(function (Shift $shift) use ($date) {
            $count = ShiftAssignment::where('shift_id', $shift->id)->whereDate('shift_date', $date)
                ->where('status', ShiftAssignment::SCHEDULED)->where('is_gate_duty', true)->count();

            return ['shift' => $shift->code, 'required' => $shift->min_gate_staff, 'scheduled' => $count];
        })->filter(fn ($row) => $row['scheduled'] < $row['required'])->values()->all();
    }

    public function checkIn(User $staff, ShiftAssignment $assignment): Attendance
    {
        if ($assignment->user_id !== $staff->id) {
            throw new ParkingException('You can only check in to your own shift.', 'forbidden', 403);
        }
        if ($assignment->status !== ShiftAssignment::SCHEDULED) {
            throw new ParkingException('This shift assignment is not active.', 'invalid_assignment');
        }
        [$start, $end] = $assignment->window();
        $now = now();
        if ($now->lt($start->subHours(1)) || $now->gt($end)) {
            throw new ParkingException('Check-in is only possible from one hour before the shift starts until it ends.', 'outside_shift_window');
        }

        return DB::transaction(function () use ($staff, $assignment, $start, $now) {
            if (Attendance::where('shift_assignment_id', $assignment->id)->lockForUpdate()->exists()) {
                throw new ParkingException('Already checked in for this shift.', 'already_checked_in', 409);
            }
            $late = $now->gt($start->addMinutes((int) $this->settings->get('attendance.late_after_minutes')));
            $attendance = Attendance::create([
                'shift_assignment_id' => $assignment->id,
                'user_id' => $staff->id,
                'check_in_at' => $now,
                'status' => $late ? 'late' : 'present',
            ]);
            $this->audit->log('attendance.check_in', $attendance, [], ['status' => $attendance->status]);

            return $attendance;
        });
    }

    public function checkOut(User $staff, ShiftAssignment $assignment): Attendance
    {
        $attendance = Attendance::where('shift_assignment_id', $assignment->id)->where('user_id', $staff->id)->first();
        if (! $attendance) {
            throw new ParkingException('You have not checked in for this shift.', 'not_checked_in');
        }
        if ($attendance->check_out_at) {
            throw new ParkingException('Already checked out.', 'already_checked_out', 409);
        }
        [, $end] = $assignment->window();
        $attendance->forceFill([
            'check_out_at' => now(),
            'status' => now()->lt($end) && $attendance->status === 'present' ? 'left_early' : $attendance->status,
        ])->save();
        $this->audit->log('attendance.check_out', $attendance);

        return $attendance;
    }
}
