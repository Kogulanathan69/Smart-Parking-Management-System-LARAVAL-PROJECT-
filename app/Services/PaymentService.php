<?php

namespace App\Services;

use App\Contracts\PaymentGateway;
use App\Exceptions\ParkingException;
use App\Models\ParkingSession;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Support\Reference;
use Illuminate\Support\Facades\DB;

class PaymentService
{
    public function __construct(private PaymentGateway $gateway, private AuditLogger $audit) {}

    /**
     * Find a previous attempt with the same idempotency key.
     */
    public function findByIdempotencyKey(string $key): ?Payment
    {
        return Payment::where('idempotency_key', $key)->first();
    }

    /**
     * Record a payment for a session. Caller must hold the session row lock (inside a transaction).
     */
    public function record(ParkingSession $session, PaymentMethod $method, int $amountMinor, User $by, string $idempotencyKey, ?int $tenderedMinor = null, ?string $externalReference = null): Payment
    {
        if (DB::transactionLevel() === 0) {
            throw new \LogicException('Payments must be recorded inside a transaction.');
        }
        if ($amountMinor <= 0) {
            throw new ParkingException('Payment amount must be greater than zero.', 'invalid_amount');
        }
        if (! $method->is_active) {
            throw new ParkingException('This payment method is not available.', 'payment_method_inactive');
        }
        if ($method->requires_reference && ! $externalReference) {
            throw new ParkingException('A payment reference is required for this payment method.', 'reference_required');
        }
        if ($tenderedMinor !== null && $tenderedMinor < $amountMinor) {
            throw new ParkingException('Amount tendered is less than the amount due.', 'insufficient_tender');
        }

        $payment = Payment::create([
            'reference' => Reference::make('PAY'),
            'idempotency_key' => $idempotencyKey,
            'parking_session_id' => $session->id,
            'payment_method_id' => $method->id,
            'amount_minor' => $amountMinor,
            'tendered_minor' => $tenderedMinor,
            'change_minor' => $tenderedMinor !== null ? $tenderedMinor - $amountMinor : 0,
            'currency' => config('parking.currency'),
            'status' => Payment::PENDING,
            'external_reference' => $externalReference,
            'processed_by' => $by->id,
        ]);

        if ($method->isImmediate()) {
            $payment->forceFill(['status' => Payment::PAID, 'paid_at' => now()])->save();
        } else {
            $result = $this->gateway->charge($payment);
            $payment->forceFill([
                'gateway' => $this->gateway->name(),
                'status' => $result['status'],
                'external_reference' => $result['external_reference'] ?? $externalReference,
                'failure_reason' => $result['status'] === Payment::FAILED ? $result['message'] : null,
                'paid_at' => $result['status'] === Payment::PAID ? now() : null,
            ])->save();
        }

        $this->audit->log('payment.recorded', $payment, [], $payment->only(['reference', 'amount_minor', 'status']), ['method' => $method->code, 'session' => $session->reference]);

        return $payment;
    }

    /**
     * Manually confirm or fail a pending (gateway) payment. Idempotent for already-final payments.
     */
    public function resolvePending(Payment $payment, bool $succeeded, User $by, ?string $externalReference = null, ?string $reason = null): Payment
    {
        if ($succeeded) {
            throw new \App\Exceptions\ParkingException(
                'Manual confirmation of gateway payments is disabled until a verified payment gateway is integrated.',
                'gateway_verification_required',
                409
            );
        }
        return DB::transaction(function () use ($payment, $succeeded, $by, $externalReference, $reason) {
            ParkingSession::whereKey($payment->parking_session_id)->lockForUpdate()->first();
            $locked = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== Payment::PENDING) {
                throw new ParkingException('This payment has already been processed.', 'payment_already_processed', 409);
            }
            $original = $locked->getAttributes();
            $locked->forceFill($succeeded
                ? ['status' => Payment::PAID, 'paid_at' => now(), 'external_reference' => $externalReference ?? $locked->external_reference]
                : ['status' => Payment::FAILED, 'failure_reason' => $reason ?? 'Marked as failed'])->save();
            $this->audit->logChanges($succeeded ? 'payment.confirmed' : 'payment.failed', $locked, $original, ['by' => $by->id]);

            return $locked;
        });
    }
}
