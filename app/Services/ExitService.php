<?php

namespace App\Services;

use App\Contracts\GateController;
use App\Exceptions\ParkingException;
use App\Models\CustomerCredit;
use App\Models\ParkingSession;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\QrTicket;
use App\Models\Receipt;
use App\Models\Refund;
use App\Models\SafetyInspection;
use App\Models\User;
use App\Notifications\ParkingReceiptNotification;
use App\Support\Money;
use App\Support\Reference;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Exit settlement (BRD §8). Payment must be confirmed before the exit is authorised,
 * the QR ticket is locked after use, and every step runs inside one transaction.
 */
class ExitService
{
    public function __construct(
        private QrTicketService $tickets,
        private PricingService $pricing,
        private PaymentService $payments,
        private SlotAllocator $allocator,
        private SettingsService $settings,
        private GateController $gate,
        private AuditLogger $audit,
        private BillPresenter $bills,
        private ReservationService $reservations,
    ) {}

    /**
     * Read-only charge preview shown to staff before payment.
     */
    public function preview(string $payload): array
    {
        $ticket = $this->tickets->resolve($payload);
        $session = $ticket->session;
        $this->assertSettleable($ticket, $session, checkInspection: false);

        return $this->buildQuote($session, now());
    }

    /**
     * @param  array{qr_payload: string, idempotency_key: string, payment_method?: ?string, tendered_minor?: ?int, external_reference?: ?string, overpayment_resolution?: ?string}  $input
     * @return array{status: string, session: ParkingSession, quote?: array, payment?: ?Payment, receipt?: ?Receipt, gate?: array}
     */
    public function settle(User $staff, array $input): array
    {
        $previous = $this->payments->findByIdempotencyKey($input['idempotency_key']);
        if ($previous) {
            // A replay must belong to the same ticket and parking session.
            // Resolve even used tickets so legitimate retries remain idempotent.
            $ticket = $this->tickets->resolve($input['qr_payload']);
            if ($previous->parking_session_id !== $ticket->parking_session_id) {
                throw new ParkingException('Idempotency key already used for another session.', 'idempotency_conflict', 409);
            }

            // Reissued (voided) tickets cannot replay an existing payment.
            // The used ticket remains valid for a legitimate completed-exit retry.
            if ($ticket->status === QrTicket::VOID) {
                throw new ParkingException('This QR ticket has been revoked.', 'ticket_already_used', 409);
            }
            $this->assertReplayDetailsMatch($previous, $input);

            return $this->replay($previous);
        }

        $result = DB::transaction(function () use ($staff, $input) {
            $ticket = $this->tickets->resolve($input['qr_payload'], lockSession: true);
            $session = ParkingSession::findOrFail($ticket->parking_session_id);

            // Another request may have completed payment while we waited for
            // the session lock. Recheck under that lock before charging again.
            if ($previous = $this->payments->findByIdempotencyKey($input['idempotency_key'])) {
                if ($previous->parking_session_id !== $session->id) {
                    throw new ParkingException('Idempotency key already used for another session.', 'idempotency_conflict', 409);
                }
                if ($ticket->status === QrTicket::VOID) {
                    throw new ParkingException('This QR ticket has been revoked.', 'ticket_already_used', 409);
                }
                $this->assertReplayDetailsMatch($previous, $input);

                return $this->replay($previous);
            }

            $this->assertSettleable($ticket, $session);

            if ($session->payments()->where('status', Payment::PENDING)->exists()) {
                throw new ParkingException('A payment for this session is still pending confirmation.', 'payment_pending', 409);
            }

            $exitAt = now();
            $quote = $this->buildQuote($session, $exitAt);
            $payment = null;

            if ($quote['balance_minor'] > 0) {
                if (empty($input['payment_method'])) {
                    throw new ParkingException('A payment method is required.', 'payment_method_required');
                }
                $method = PaymentMethod::where('code', $input['payment_method'])->firstOrFail();
                $payment = $this->payments->record($session, $method, $quote['balance_minor'], $staff, $input['idempotency_key'], $input['tendered_minor'] ?? null, $input['external_reference'] ?? null);

                if ($payment->status !== Payment::PAID) {
                    return ['status' => 'payment_'.$payment->status, 'session' => $session, 'quote' => $quote, 'payment' => $payment];
                }
            }

            $receipt = $this->finalize($session, $ticket, $quote, $exitAt, $staff, $input['overpayment_resolution'] ?? null);

            return ['status' => 'completed', 'session' => $session->refresh(), 'quote' => $quote, 'payment' => $payment, 'receipt' => $receipt];
        });

        // A concurrent retry must never reopen the gate or resend a receipt.
        return ! empty($result['replayed']) ? $result : $this->afterSettlement($result);
    }

    /**
     * Called after a pending (gateway) payment is confirmed. Uses the original exit time.
     */
    public function completeAfterPayment(Payment $payment, User $staff): array
    {
        $result = DB::transaction(function () use ($payment, $staff) {
            $session = ParkingSession::whereKey($payment->parking_session_id)->lockForUpdate()->firstOrFail();
            $ticket = $session->qrTicket;
            if ($session->status !== ParkingSession::ACTIVE || ! $ticket || $ticket->status !== QrTicket::ACTIVE) {
                return ['status' => 'already_completed', 'session' => $session, 'payment' => $payment, 'receipt' => $session->receipt];
            }
            $exitAt = $payment->created_at;
            $quote = $this->buildQuote($session, $exitAt);
            if ($quote['balance_minor'] > 0) {
                return ['status' => 'balance_due', 'session' => $session, 'quote' => $quote, 'payment' => $payment];
            }
            $receipt = $this->finalize($session, $ticket, $quote, $exitAt, $staff, null);

            return ['status' => 'completed', 'session' => $session->refresh(), 'quote' => $quote, 'payment' => $payment, 'receipt' => $receipt];
        });

        return $this->afterSettlement($result);
    }

    /**
     * Gate/staff confirmation that the vehicle physically left (only when exit.release_slot_on = gate).
     */
    public function confirmVehicleLeft(ParkingSession $session, User $staff): ParkingSession
    {
        return DB::transaction(function () use ($session, $staff) {
            $locked = ParkingSession::whereKey($session->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== ParkingSession::EXIT_AUTHORIZED) {
                throw new ParkingException('This session is not waiting for exit confirmation.', 'invalid_session_state', 409);
            }
            $this->closeAndRelease($locked);
            $this->audit->log('session.vehicle_left', $locked, [], ['confirmed_by' => $staff->id]);

            return $locked->refresh();
        });
    }

    /**
     * Prepayment (e.g. weekly/monthly up front). Unused amounts become credit/refund at exit.
     */
    public function prepay(ParkingSession $session, User $staff, array $input): Payment
    {
        if ($previous = $this->payments->findByIdempotencyKey($input['idempotency_key'])) {
            if ($previous->parking_session_id !== $session->id) {
                throw new ParkingException('Idempotency key already used for another session.', 'idempotency_conflict', 409);
            }

            // Retrying a key must not silently accept a different amount or method.
            $requestedMethod = PaymentMethod::where('code', $input['payment_method'])->first();
            if (! $requestedMethod
                || $previous->payment_method_id !== $requestedMethod->id
                || $previous->amount_minor !== (int) $input['amount_minor']) {
                throw new ParkingException('Idempotency key already used with different payment details.', 'idempotency_conflict', 409);
            }

            return $previous;
        }

        return DB::transaction(function () use ($session, $staff, $input) {
            $locked = ParkingSession::whereKey($session->id)->lockForUpdate()->firstOrFail();
            if ($previous = $this->payments->findByIdempotencyKey($input['idempotency_key'])) {
                if ($previous->parking_session_id !== $locked->id) {
                    throw new ParkingException('Idempotency key already used for another session.', 'idempotency_conflict', 409);
                }
                $method = PaymentMethod::where('code', $input['payment_method'])->first();
                if (! $method || $previous->payment_method_id !== $method->id || $previous->amount_minor !== (int) $input['amount_minor']) {
                    throw new ParkingException('Idempotency key already used with different payment details.', 'idempotency_conflict', 409);
                }

                return $previous;
            }
            if ($locked->status !== ParkingSession::ACTIVE) {
                throw new ParkingException('Session is not active.', 'invalid_session_state', 409);
            }
            $method = PaymentMethod::where('code', $input['payment_method'])->firstOrFail();

            return $this->payments->record($locked, $method, (int) $input['amount_minor'], $staff, $input['idempotency_key'], $input['tendered_minor'] ?? null, $input['external_reference'] ?? null);
        });
    }

    public function buildQuote(ParkingSession $session, CarbonInterface $exitAt): array
    {
        $quote = $this->pricing->quote($session, $exitAt);

        // Guaranteed parking: each reservation period's package price is billed once, on the visit it is attached to.
        $periodLines = [];
        foreach ($session->billedPeriods()->get() as $period) {
            $periodLines[] = [
                'code' => 'package',
                'label' => 'Reserved '.($session->pricing_snapshot['package']['code'] ?? 'package').' — period '.$period->sequence.($period->kind === 'renewal' ? ' (renewal)' : ''),
                'amount_minor' => $period->price_minor,
                'detail' => ['reservation_period_id' => $period->id, 'starts_at' => $period->starts_at->toIso8601String(), 'ends_at' => $period->ends_at->toIso8601String()],
            ];
        }
        if ($periodLines) {
            $quote['lines'] = array_merge($periodLines, $quote['lines']);
            $quote['subtotal_minor'] += (int) array_sum(array_column($periodLines, 'amount_minor'));
            $bp = (int) ($session->pricing_snapshot['policy']['tax_basis_points'] ?? 0);
            $quote['tax_minor'] = Money::divRound($quote['subtotal_minor'] * $bp, 10000);
            $quote['total_minor'] = $quote['subtotal_minor'] + $quote['tax_minor'];
        }
        if ($session->parking_reservation_id) {
            $quote['reservation_id'] = $session->parking_reservation_id;
        }

        // Penalties are separate from overtime and are never taxed (tax rules are pending, BRD §19).
        $penaltyMinor = 0;
        foreach ($session->appliedPenalties()->with('type')->orderBy('id')->get() as $penalty) {
            $quote['lines'][] = [
                'code' => 'penalty',
                'label' => 'Penalty: '.$penalty->type->name,
                'amount_minor' => $penalty->amount_minor,
                'detail' => ['penalty_id' => $penalty->id, 'penalty_code' => $penalty->type->code, 'reason' => $penalty->reason],
            ];
            $penaltyMinor += $penalty->amount_minor;
        }
        $quote['parking_total_minor'] = $quote['total_minor'];
        $quote['penalty_minor'] = $penaltyMinor;
        $quote['total_minor'] += $penaltyMinor;
        $quote['charges'] = $this->summariseCharges($quote);

        $paid = $session->paidAmountMinor();
        $quote['paid_minor'] = $paid;
        $quote['balance_minor'] = max(0, $quote['total_minor'] - $paid);
        $quote['overpaid_minor'] = max(0, $paid - $quote['total_minor']);
        $quote['session_reference'] = $session->reference;
        $quote['requires_exit_inspection'] = $this->exitInspectionMissing($session);

        return $quote;
    }

    /**
     * Totals per charge type for the final bill.
     *
     * @return array{base_minor: int, overtime_minor: int, safety_minor: int, penalty_minor: int, tax_minor: int, total_minor: int}
     */
    private function summariseCharges(array $quote): array
    {
        $sum = fn (array $codes) => (int) collect($quote['lines'])->whereIn('code', $codes)->sum('amount_minor');

        return [
            'base_minor' => $sum(['parking', 'package']),
            'overtime_minor' => $sum(['overtime']),
            'safety_minor' => $sum(['safety', 'safety_overtime']),
            'penalty_minor' => $sum(['penalty']),
            'tax_minor' => (int) $quote['tax_minor'],
            'total_minor' => (int) $quote['total_minor'],
        ];
    }

    /**
     * Retrying an idempotency key with a different payment method is a different request, not a retry.
     */
    private function assertReplayDetailsMatch(Payment $previous, array $input): void
    {
        if (! empty($input['payment_method'])) {
            $methodId = PaymentMethod::where('code', $input['payment_method'])->value('id');
            if ($methodId !== $previous->payment_method_id) {
                throw new ParkingException('Idempotency key already used with different payment details.', 'idempotency_conflict', 409);
            }
        }
    }

    private function finalize(ParkingSession $session, QrTicket $ticket, array $quote, CarbonInterface $exitAt, User $staff, ?string $resolution): Receipt
    {
        $paid = $session->paidAmountMinor();
        $overpaid = max(0, $paid - $quote['total_minor']);
        $credit = 0;
        $refund = 0;

        if ($overpaid > 0) {
            $resolution ??= $this->settings->get('refund.default_resolution');
            if ($resolution === 'credit' && $session->customer_id) {
                $days = $this->settings->get('refund.credit_expiry_days');
                CustomerCredit::create([
                    'customer_id' => $session->customer_id,
                    'parking_session_id' => $session->id,
                    'amount_minor' => $overpaid,
                    'reason' => 'Unused prepaid amount',
                    'expires_at' => $days ? now()->addDays((int) $days) : null,
                    'created_by' => $staff->id,
                ]);
                $credit = $overpaid;
            } else {
                $autoApprove = $overpaid <= (int) $this->settings->get('refund.auto_approve_limit_minor');
                Refund::create([
                    'reference' => Reference::make('RF'),
                    'parking_session_id' => $session->id,
                    'amount_minor' => $overpaid,
                    'status' => $autoApprove ? Refund::APPROVED : Refund::PENDING,
                    'reason' => 'Unused prepaid amount',
                    'requested_by' => $staff->id,
                    'approved_by' => $autoApprove ? $staff->id : null,
                    'approved_at' => $autoApprove ? now() : null,
                ]);
                $refund = $overpaid;
            }
        }

        $session->forceFill([
            'exit_at' => $exitAt,
            'exit_staff_id' => $staff->id,
            'charge_breakdown' => $quote,
            'total_charge_minor' => $quote['total_minor'],
        ]);

        if ($this->settings->get('exit.release_slot_on') === 'gate') {
            $session->status = ParkingSession::EXIT_AUTHORIZED;
            $session->save();
        } else {
            $this->closeAndRelease($session);
        }

        $this->tickets->markUsed($ticket);

        $number = Reference::make('RCP');
        $receipt = Receipt::create([
            'number' => $number,
            'parking_session_id' => $session->id,
            'customer_id' => $session->customer_id,
            'total_minor' => $quote['total_minor'],
            'paid_minor' => $paid,
            'credit_issued_minor' => $credit,
            'refund_minor' => $refund,
            'currency' => $quote['currency'],
            'breakdown' => [
                'quote' => $quote,
                'bill' => $this->bills->present($session->fresh(), $quote, $number),
                'payments' => $session->payments()->with('method')->where('status', Payment::PAID)->get()
                    ->map(fn (Payment $p) => ['reference' => $p->reference, 'method' => $p->method->code, 'amount_minor' => $p->amount_minor, 'paid_at' => $p->paid_at?->toIso8601String()])->all(),
            ],
            'issued_by' => $staff->id,
            'issued_at' => now(),
        ]);

        $this->audit->log('session.exit_settled', $session, [], [
            'total_minor' => $quote['total_minor'], 'paid_minor' => $paid, 'credit_minor' => $credit, 'refund_minor' => $refund, 'receipt' => $receipt->number,
        ]);

        return $receipt;
    }

    /**
     * Runs only once the vehicle has physically left (at settlement, or at gate confirmation in gate mode).
     * Guaranteed-parking visits keep their slot reserved for the owner; everything else is released.
     */
    private function closeAndRelease(ParkingSession $session): void
    {
        $slotId = $session->parking_slot_id;
        $session->forceFill(['status' => ParkingSession::COMPLETED, 'open_vehicle_key' => null, 'open_slot_key' => null])->save();

        if ($session->parking_reservation_id) {
            $this->reservations->handleDeparture($session, now());

            return;
        }
        $this->allocator->release($slotId);
    }

    private function afterSettlement(array $result): array
    {
        if ($result['status'] === 'completed') {
            $session = $result['session'];
            $result['gate'] = $this->gate->open($session, 'exit');
            if ($session->customer) {
                $session->customer->notify(new ParkingReceiptNotification($result['receipt']));
            }
        }

        return $result;
    }

    private function replay(Payment $payment): array
    {
        $session = $payment->session()->first();

        return [
            'status' => $payment->status === Payment::PAID
                ? ($session->isOpen() && $session->status === ParkingSession::ACTIVE ? 'payment_paid' : 'completed')
                : 'payment_'.$payment->status,
            'session' => $session,
            'payment' => $payment,
            'receipt' => $session->receipt,
            'replayed' => true,
        ];
    }

    private function assertSettleable(QrTicket $ticket, ParkingSession $session, bool $checkInspection = true): void
    {
        if ($ticket->status !== QrTicket::ACTIVE || $session->status !== ParkingSession::ACTIVE) {
            throw new ParkingException('This ticket has already been used or the session is closed.', 'ticket_already_used', 409);
        }
        if ($checkInspection && $this->exitInspectionMissing($session)) {
            throw new ParkingException('An exit condition inspection must be recorded for safety parking before exit.', 'exit_inspection_required');
        }
    }

    private function exitInspectionMissing(ParkingSession $session): bool
    {
        return $session->has_safety
            && $this->settings->get('safety.require_exit_inspection')
            && ! $session->inspections()->where('type', SafetyInspection::TYPE_EXIT)->where('status', SafetyInspection::COMPLETED)->exists();
    }
}
