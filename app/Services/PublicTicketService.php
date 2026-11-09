<?php

namespace App\Services;

use App\Models\ParkingSession;
use App\Models\PublicTicketToken;
use App\Support\Money;
use Carbon\CarbonImmutable;

class PublicTicketService
{
    public function __construct(private ExitService $exits) {}

    public function find(string $token): ?PublicTicketToken
    {
        if (! preg_match('/\A[a-f0-9]{64}\z/', $token)) {
            return null;
        }

        return PublicTicketToken::with([
            'qrTicket.session.vehicle.category',
            'qrTicket.session.zone',
            'qrTicket.session.slot',
            'qrTicket.session.package',
            'qrTicket.session.receipt',
        ])->where('token_hash', hash('sha256', $token))->first();
    }

    public function present(PublicTicketToken $public): array
    {
        $session = $public->qrTicket?->session;
        abort_unless($session && $session->vehicle && $session->zone && $session->slot && $session->package, 404);
        $tz = config('parking.business_timezone');
        $closed = in_array($session->status, [ParkingSession::COMPLETED, ParkingSession::CANCELLED], true);
        $asOf = $closed && $session->exit_at ? $session->exit_at : now();
        $quote = $closed ? ($session->charge_breakdown ?? []) : $this->exits->buildQuote($session, $asOf);
        $totalMinor = (int) ($closed ? ($session->total_charge_minor ?? 0) : ($quote['total_minor'] ?? 0));
        $entry = CarbonImmutable::instance($session->entry_at);
        $minutes = max(0, (int) ($quote['duration_minutes'] ?? $entry->diffInMinutes($asOf)));

        return [
            'status' => match ($session->status) {
                ParkingSession::COMPLETED => 'checked_out',
                ParkingSession::CANCELLED => 'cancelled',
                ParkingSession::EXIT_AUTHORIZED => 'exit_authorized',
                default => 'parked',
            },
            'ticket_reference' => $session->reference,
            'plate' => $session->vehicle->plate_number ?: $session->vehicle->identifier,
            'vehicle' => [
                'category' => $session->vehicle->category?->name,
                'fuel_type' => $session->vehicle->fuel_type?->label(),
                'make_model' => trim(implode(' ', array_filter([$session->vehicle->make, $session->vehicle->model]))) ?: null,
            ],
            'parking' => [
                'zone' => $session->zone->name,
                'zone_code' => $session->zone->code,
                'slot' => $session->slot->code,
                'package' => $session->package->name,
                'billing_mode' => $session->pricing_snapshot['package']['billing_mode'] ?? null,
            ],
            'entry_at' => $entry->setTimezone($tz)->toIso8601String(),
            'exit_at' => $session->exit_at?->setTimezone($tz)->toIso8601String(),
            'server_now' => now()->setTimezone($tz)->toIso8601String(),
            'duration_minutes' => $minutes,
            'charges' => [
                'estimated' => ! $closed,
                'currency' => $quote['currency'] ?? config('parking.currency'),
                'total_minor' => $totalMinor,
                'total' => Money::format($totalMinor),
                'paid_minor' => (int) ($quote['paid_minor'] ?? $session->paidAmountMinor()),
                'balance_minor' => (int) ($quote['balance_minor'] ?? max(0, $totalMinor - $session->paidAmountMinor())),
                'lines' => collect($quote['lines'] ?? [])->map(fn ($line) => [
                    'label' => $line['label'] ?? 'Charge',
                    'amount' => Money::format((int) ($line['amount_minor'] ?? 0)),
                ])->values()->all(),
            ],
            'payment_status' => $session->receipt ? 'paid' : ($session->paidAmountMinor() > 0 ? 'part_paid' : 'unpaid'),
            'receipt_reference' => $session->receipt?->number,
        ];
    }
}
