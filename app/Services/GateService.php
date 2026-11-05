<?php

namespace App\Services;

use App\Contracts\GateController;
use App\Exceptions\ParkingException;
use App\Models\GateCommand;
use App\Models\ParkingSession;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The only path to the physical gate. Every attempt is recorded; a gate opens at most once per
 * authorised exit (unique success key), and only for a session whose settlement is complete.
 * An ANPR reading on its own can never reach this service.
 */
class GateService
{
    public function __construct(private GateController $gate, private AuditLogger $audit) {}

    /**
     * @return array{status: string, driver: string, command_id?: int}
     */
    public function openForExit(ParkingSession $session, ?User $by): array
    {
        // The session row lock serialises concurrent open requests so the physical gate is pulsed at most
        // once per successful exit; the unique success_key is the backstop.
        return DB::transaction(function () use ($session, $by) {
            $session = ParkingSession::whereKey($session->id)->lockForUpdate()->firstOrFail();
            if (! in_array($session->status, [ParkingSession::COMPLETED, ParkingSession::EXIT_AUTHORIZED], true) || ! $session->receipt) {
                throw new ParkingException('The gate can only open after the exit has been settled.', 'exit_not_settled', 409);
            }
            if (GateCommand::where('success_key', $session->id.':exit')->exists()) {
                throw new ParkingException('The gate has already been opened for this exit.', 'gate_already_opened', 409);
            }

            try {
                $result = $this->gate->open($session, 'exit');
                $ok = in_array($result['status'] ?? null, ['opened', 'simulated', 'ok'], true);
            } catch (\Throwable $e) {
                Log::error('Gate controller failure', ['session' => $session->reference, 'error' => class_basename($e)]);
                $result = ['status' => 'failed', 'driver' => config('parking.drivers.gate_controller')];
                $ok = false;
            }

            try {
                $command = GateCommand::create([
                    'parking_session_id' => $session->id,
                    'direction' => 'exit',
                    'status' => $ok ? 'success' : 'failed',
                    'driver' => (string) ($result['driver'] ?? 'unknown'),
                    'result' => mb_substr((string) ($result['status'] ?? ''), 0, 255),
                    'requested_by' => $by?->id,
                    'success_key' => $ok ? $session->id.':exit' : null,
                ]);
            } catch (UniqueConstraintViolationException) {
                throw new ParkingException('The gate has already been opened for this exit.', 'gate_already_opened', 409);
            }
            $this->audit->log($ok ? 'gate.opened' : 'gate.open_failed', $session, [], ['direction' => 'exit', 'driver' => $command->driver, 'result' => $command->result]);

            return ['status' => $ok ? ($result['status'] ?? 'opened') : 'failed', 'driver' => $command->driver, 'command_id' => $command->id];
        });
    }
}
