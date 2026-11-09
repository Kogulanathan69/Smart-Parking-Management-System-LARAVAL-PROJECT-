<?php

namespace App\Services;

use App\Exceptions\ParkingException;
use App\Models\ParkingSession;
use App\Models\PublicTicketToken;
use App\Models\QrTicket;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\Schema;

/**
 * Staff checkout tickets retain the legacy random 192-bit credential. Printable tickets may
 * carry a separate 256-bit, view-only public token; both are searchable only by SHA-256 hash.
 */
class QrTicketService
{
    public function issue(ParkingSession $session): array
    {
        $oldIds = QrTicket::where('parking_session_id', $session->id)->where('status', QrTicket::ACTIVE)->pluck('id');
        QrTicket::whereIn('id', $oldIds)->update(['status' => QrTicket::VOID]);
        if ($oldIds->isNotEmpty() && Schema::hasTable('public_ticket_tokens')) {
            PublicTicketToken::whereIn('qr_ticket_id', $oldIds)->where('status', PublicTicketToken::ACTIVE)
                ->update(['status' => PublicTicketToken::REVOKED, 'revoked_at' => now()]);
        }

        $token = bin2hex(random_bytes(24));
        $ticket = QrTicket::create([
            'parking_session_id' => $session->id,
            'token_hash' => $this->hash($token),
            'token_encrypted' => $token,
            'status' => QrTicket::ACTIVE,
            'issued_at' => now(),
        ]);
        $this->issuePublicToken($ticket);

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
        $payload = $this->legacyPayloadFromScan(trim($payload));
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
        $legacyPayload = $this->payload($ticket->token_encrypted);
        $publicUrl = $this->publicUrlFor($ticket);
        $payload = $publicUrl ?? $legacyPayload;
        $ticket->increment('print_count');
        $tz = config('parking.business_timezone');

        return [
            'qr_payload' => $legacyPayload,
            'qr_content' => $payload,
            'public_url' => $publicUrl,
            'qr_svg' => $this->svg($payload),
            'session_reference' => $session->reference,
            'vehicle' => $session->vehicle->identifier,
            'zone' => $session->zone->code,
            'slot' => $session->slot->code,
            'package' => $session->package->name,
            'entry_at_local' => $session->entry_at->setTimezone($tz)->format('Y-m-d H:i'),
            'package_ends_at_local' => $session->package_ends_at?->setTimezone($tz)->format('Y-m-d H:i'),
            'print_count' => $ticket->print_count,
            'instruction' => $publicUrl ? 'Scan to view parking details' : 'Present this QR to parking staff',
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

    private function issuePublicToken(QrTicket $ticket): void
    {
        if (! Schema::hasTable('public_ticket_tokens') || ! $this->publicBaseUrl()) {
            return;
        }
        $token = bin2hex(random_bytes(32));
        PublicTicketToken::create([
            'qr_ticket_id' => $ticket->id,
            'token_hash' => $this->hash($token),
            'token_encrypted' => $token,
            'status' => PublicTicketToken::ACTIVE,
            'issued_at' => now(),
        ]);
    }

    public function publicUrlFor(QrTicket $ticket): ?string
    {
        $base = $this->publicBaseUrl();
        if (! $base || ! Schema::hasTable('public_ticket_tokens')) {
            return null;
        }
        $public = $ticket->relationLoaded('publicToken') ? $ticket->publicToken : $ticket->publicToken()->first();

        return $public && $public->status === PublicTicketToken::ACTIVE
            ? $base.'/t/'.$public->token_encrypted : null;
    }

    private function legacyPayloadFromScan(string $scan): string
    {
        if (! str_contains($scan, '://')) {
            return $scan;
        }
        $base = $this->publicBaseUrl();
        if (! $base || ! Schema::hasTable('public_ticket_tokens')) {
            throw ParkingException::invalidTicket();
        }
        $scanParts = parse_url($scan);
        $baseParts = parse_url($base);
        if (! $scanParts || ! $baseParts
            || strtolower($scanParts['scheme'] ?? '') !== strtolower($baseParts['scheme'] ?? '')
            || strtolower($scanParts['host'] ?? '') !== strtolower($baseParts['host'] ?? '')
            || (int) ($scanParts['port'] ?? $this->defaultPort($scanParts['scheme'] ?? '')) !== (int) ($baseParts['port'] ?? $this->defaultPort($baseParts['scheme'] ?? ''))
            || isset($scanParts['query']) || isset($scanParts['fragment'])) {
            throw ParkingException::invalidTicket();
        }
        $basePath = rtrim($baseParts['path'] ?? '', '/');
        $path = $scanParts['path'] ?? '';
        $pattern = '#^'.preg_quote($basePath, '#').'/t/([a-f0-9]{64})$#';
        if (! preg_match($pattern, $path, $matches)) {
            throw ParkingException::invalidTicket();
        }
        $public = PublicTicketToken::with('qrTicket')
            ->where('token_hash', $this->hash($matches[1]))
            ->where('status', PublicTicketToken::ACTIVE)->first();
        if (! $public || ! $public->qrTicket) {
            throw ParkingException::invalidTicket();
        }

        return $this->payload($public->qrTicket->token_encrypted);
    }

    private function publicBaseUrl(): ?string
    {
        $url = rtrim(trim((string) config('parking.qr.public_base_url')), '/');
        $parts = parse_url($url);
        if (! $url || ! $parts || ! isset($parts['scheme'], $parts['host'])
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
            return null;
        }
        if (strtolower($parts['scheme']) !== 'https' && ! config('parking.qr.allow_insecure_http')) {
            return null;
        }

        return $url;
    }

    private function defaultPort(string $scheme): int
    {
        return strtolower($scheme) === 'https' ? 443 : 80;
    }
}
