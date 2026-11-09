<?php

namespace App\Services;

use App\Models\ParkingSession;
use App\Models\Payment;
use Carbon\CarbonImmutable;

/**
 * Builds the customer-facing final bill (BRD §8 exit record + requirement 5) from a quote.
 * Pure presentation: never changes money values, which always come from the quote.
 */
class BillPresenter
{
    public function present(ParkingSession $session, array $quote, ?string $receiptNumber = null): array
    {
        $session->loadMissing(['vehicle', 'vehicleCategory', 'zone', 'package']);
        // Always read payments fresh: the bill reflects money actually received, not the quote's snapshot.
        $payments = $session->payments()->with('method')->orderBy('id')->get();
        $tz = config('parking.business_timezone');
        $minutes = (int) ($quote['duration_minutes'] ?? 0);
        $paid = $payments->where('status', Payment::PAID);
        $total = (int) ($quote['total_minor'] ?? 0);
        $paidMinor = (int) $paid->sum('amount_minor');
        $charges = $quote['charges'] ?? [];

        return [
            'session_reference' => $session->reference,
            'status' => $session->status,
            'vehicle_number' => $session->vehicle?->identifier,
            'plate_number' => $session->vehicle?->plate_number,
            'vehicle_category' => $session->vehicleCategory?->code ?? ($session->pricing_snapshot['vehicle']['category'] ?? null),
            'fuel_type' => $session->fuel_type?->value ?? ($session->pricing_snapshot['vehicle']['fuel_type'] ?? null),
            'zone' => $session->zone?->code,
            'package' => $session->package?->name,
            'entry_at' => $quote['entry_at'] ?? $session->entry_at?->toIso8601String(),
            'exit_at' => $quote['exit_at'] ?? null,
            'entry_at_local' => $session->entry_at?->setTimezone($tz)->format('Y-m-d H:i'),
            'exit_at_local' => isset($quote['exit_at']) ? CarbonImmutable::parse($quote['exit_at'])->setTimezone($tz)->format('Y-m-d H:i') : null,
            'duration_minutes' => $minutes,
            'duration_human' => self::humanDuration($minutes),
            'currency' => $quote['currency'] ?? config('parking.currency'),
            'base_charge_minor' => $charges['base_minor'] ?? 0,
            'overtime_charge_minor' => $charges['overtime_minor'] ?? 0,
            'penalty_charge_minor' => $charges['penalty_minor'] ?? 0,
            'safety_charge_minor' => $charges['safety_minor'] ?? 0,
            'tax_minor' => $charges['tax_minor'] ?? ($quote['tax_minor'] ?? 0),
            'total_payable_minor' => $total,
            'amount_paid_minor' => $paidMinor,
            'balance_minor' => max(0, $total - $paidMinor),
            'overpaid_minor' => max(0, $paidMinor - $total),
            'lines' => $quote['lines'] ?? [],
            'payment_methods' => $paid->map(fn (Payment $p) => $p->method->code)->unique()->values()->all(),
            'payments' => $payments->map(fn (Payment $p) => [
                'reference' => $p->reference, 'method' => $p->method->code, 'status' => $p->status,
                'amount_minor' => $p->amount_minor, 'paid_at' => $p->paid_at?->toIso8601String(),
            ])->values()->all(),
            'receipt_number' => $receiptNumber,
        ];
    }

    public static function humanDuration(int $minutes): string
    {
        $days = intdiv($minutes, 1440);
        $hours = intdiv($minutes % 1440, 60);
        $mins = $minutes % 60;

        return trim(($days ? "{$days}d " : '').($hours || $days ? "{$hours}h " : '')."{$mins}m");
    }
}
