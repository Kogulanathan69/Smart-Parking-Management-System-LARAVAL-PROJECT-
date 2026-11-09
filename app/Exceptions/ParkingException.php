<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Expected business-rule failure, rendered as a consistent JSON error.
 */
class ParkingException extends RuntimeException
{
    public function __construct(string $message, public readonly string $errorCode = 'parking_error', public readonly int $status = 422, public readonly array $context = [])
    {
        parent::__construct($message);
    }

    public static function noSlotAvailable(): self
    {
        return new self('No compatible parking slot is available in this zone.', 'no_slot_available', 409);
    }

    public static function duplicateActiveEntry(): self
    {
        return new self('This vehicle already has an active parking session.', 'duplicate_active_entry', 409);
    }

    public static function invalidTicket(): self
    {
        return new self('QR ticket is invalid or has already been used.', 'invalid_ticket', 404);
    }

    public function render(): JsonResponse
    {
        return response()->json(array_filter([
            'message' => $this->getMessage(),
            'error_code' => $this->errorCode,
            'context' => $this->context ?: null,
        ]), $this->status);
    }
}
