<?php

namespace App\Services\Integrations;

use App\Contracts\PaymentGateway;
use App\Models\Payment;

/**
 * Placeholder: online payments stay "pending" until an authorised user confirms them manually.
 */
class NullPaymentGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'none';
    }

    public function charge(Payment $payment): array
    {
        return ['status' => 'pending', 'external_reference' => null, 'message' => 'No payment gateway is configured; awaiting manual confirmation.'];
    }
}
