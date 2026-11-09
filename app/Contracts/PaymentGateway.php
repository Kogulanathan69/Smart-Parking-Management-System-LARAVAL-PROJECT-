<?php

namespace App\Contracts;

use App\Models\Payment;

/**
 * Card / online payment gateway boundary. No real gateway is integrated yet.
 */
interface PaymentGateway
{
    public function name(): string;

    /**
     * Start a charge. Must return "pending" unless the provider confirmed synchronously.
     *
     * @return array{status: 'pending'|'paid'|'failed', external_reference: ?string, message: ?string}
     */
    public function charge(Payment $payment): array;
}
