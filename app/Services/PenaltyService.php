<?php

namespace App\Services;

use App\Exceptions\ParkingException;
use App\Models\ParkingSession;
use App\Models\Payment;
use App\Models\PenaltyType;
use App\Models\SessionPenalty;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Manually applied, admin-configured penalties (lost ticket, rule violation, ...).
 * Kept separate from overtime. Nothing here is applied automatically.
 */
class PenaltyService
{
    public function __construct(private AuditLogger $audit) {}

    public function apply(ParkingSession $session, PenaltyType $type, string $reason, User $by): SessionPenalty
    {
        try {
            return DB::transaction(function () use ($session, $type, $reason, $by) {
                $locked = $this->lockOpenSession($session);
                if (! $type->is_active) {
                    throw new ParkingException('This penalty type is not active.', 'penalty_type_inactive');
                }

                $penalty = SessionPenalty::create([
                    'parking_session_id' => $locked->id,
                    'penalty_type_id' => $type->id,
                    'amount_minor' => $type->amount_minor,
                    'reason' => $reason,
                    'status' => SessionPenalty::APPLIED,
                    'applied_by' => $by->id,
                    'applied_at' => now(),
                    'active_key' => $locked->id.':'.$type->id,
                ]);

                $this->audit->log('penalty.applied', $locked, [], [
                    'penalty_id' => $penalty->id, 'type' => $type->code, 'amount_minor' => $penalty->amount_minor, 'reason' => $reason,
                ]);

                return $penalty;
            });
        } catch (UniqueConstraintViolationException) {
            throw new ParkingException('This penalty has already been applied to the session.', 'duplicate_penalty', 409);
        }
    }

    public function waive(SessionPenalty $penalty, string $reason, User $by): SessionPenalty
    {
        return DB::transaction(function () use ($penalty, $reason, $by) {
            $session = $this->lockOpenSession($penalty->session);
            $locked = SessionPenalty::whereKey($penalty->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== SessionPenalty::APPLIED) {
                throw new ParkingException('This penalty has already been waived.', 'penalty_already_waived', 409);
            }

            $locked->forceFill([
                'status' => SessionPenalty::WAIVED,
                'waived_by' => $by->id,
                'waived_at' => now(),
                'waiver_reason' => $reason,
                'active_key' => null,
            ])->save();

            $this->audit->log('penalty.waived', $session, ['penalty_id' => $locked->id, 'status' => SessionPenalty::APPLIED], [
                'status' => SessionPenalty::WAIVED, 'amount_minor' => $locked->amount_minor, 'reason' => $reason,
            ]);

            return $locked;
        });
    }

    /**
     * Penalties can only change while the session is open and no payment is awaiting confirmation,
     * so the amount a customer was asked to pay never changes under them.
     */
    private function lockOpenSession(ParkingSession $session): ParkingSession
    {
        $locked = ParkingSession::whereKey($session->id)->lockForUpdate()->firstOrFail();
        if ($locked->status !== ParkingSession::ACTIVE) {
            throw new ParkingException('Penalties can only be changed on an active parking session.', 'invalid_session_state', 409);
        }
        if ($locked->payments()->where('status', Payment::PENDING)->exists()) {
            throw new ParkingException('A payment for this session is awaiting confirmation.', 'payment_pending', 409);
        }

        return $locked;
    }
}
