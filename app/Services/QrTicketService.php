<?php

namespace App\Services;

use App\Exceptions\ParkingException;
use App\Models\ParkingSession;
use App\Models\QrTicket;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * QR tickets carry only a random 192-bit token (no personal data). Only its SHA-256 hash
 * is searchable; the token itself is stored encrypted for reprints.
 */
class QrTicketService
{
    public function issue(ParkingSession $session): array
    {
        QrTicket::where('parking_session_id', $session->id)->where('status', QrTicket::ACTIVE)->update(['status' => QrTicket::VOID]);

        $token = bin2hex(random_bytes(24));
        $ticket = QrTicket::create([
            'parking_session_id' => $session->id,
            'token_hash' => $this->hash($token),
            'token_encrypted' => $token,
            'status' => QrTicket::ACTIVE,
            'issued_at' => now(),
        ]);

        return [$ticket, $this->payload($token)];
    }

    public function payload(string $token): string
    {
        return config('parking.qr.prefix').$token;
    }

    /**
     * Resolve a scanned payload to its ticket, optionally locking the session row.
     */
    public function resolve(string $payload, bool $lockSession = false): QrTicket
    {
        $prefix = config('parking.qr.prefix');
        $payload = trim($payload);
        $token = str_starts_with($payload, $prefix) ? substr($payload, strlen($prefix)) : $payload;

        if (! preg_match('/^[a-f0-9]{48}$/', $token)) {
            throw ParkingException::invalidTicket();
        }

        $ticket = QrTicket::where('token_hash', $this->hash($token))->first();
        if (! $ticket) {
            throw ParkingException::invalidTicket();
        }

        if ($lockSession) {
            ParkingSession::whereKey($ticket->parking_session_id)->lockForUpdate()->first();
            $ticket->refresh();
        }

        return $ticket;
    }

    public function printable(ParkingSession $session): array
    {
        $ticket = $session->qrTicket;
        if (! $ticket || $ticket->status !== QrTicket::ACTIVE) {
            throw ParkingException::invalidTicket();
        }
        $payload = $this->payload($ticket->token_encrypted);
        $ticket->increment('print_count');
        $tz = config('parking.business_timezone');

        return [
            'qr_payload' => $payload,
            'qr_svg' => $this->svg($payload),
            'session_reference' => $session->reference,
            'vehicle' => $session->vehicle->identifier,
            'zone' => $session->zone->code,
            'slot' => $session->slot->code,
            'package' => $session->package->name,
            'entry_at_local' => $session->entry_at->setTimezone($tz)->format('Y-m-d H:i'),
            'package_ends_at_local' => $session->package_ends_at?->setTimezone($tz)->format('Y-m-d H:i'),
            'print_count' => $ticket->print_count,
        ];
    }

    public function markUsed(QrTicket $ticket): void
    {
        $ticket->forceFill(['status' => QrTicket::USED, 'used_at' => now()])->save();
    }

    public function svg(string $payload): string
    {
        $writer = new Writer(new ImageRenderer(new RendererStyle(240, 1), new SvgImageBackEnd));

        return $writer->writeString($payload);
    }

    private function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
