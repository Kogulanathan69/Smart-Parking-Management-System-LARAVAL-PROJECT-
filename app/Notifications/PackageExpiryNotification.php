<?php

namespace App\Notifications;

use App\Models\ParkingSession;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * In-app (database) reminder. SMS/WhatsApp/email are NOT integrated yet.
 */
class PackageExpiryNotification extends Notification
{
    use Queueable;

    public function __construct(public ParkingSession $session, public string $kind, public ?int $remainingMinutes = null) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $tz = config('parking.business_timezone');
        $endsLocal = $this->session->package_ends_at?->setTimezone($tz)->format('Y-m-d H:i');
        $vehicle = $this->session->vehicle?->identifier;

        return [
            'kind' => $this->kind === 'expired' ? 'package_expired' : 'package_expiring',
            'title' => $this->kind === 'expired' ? 'Parking package expired' : 'Parking package ending soon',
            'message' => $this->kind === 'expired'
                ? "The parking package for {$vehicle} ended at {$endsLocal}. Overtime charges may now apply."
                : "The parking package for {$vehicle} ends at {$endsLocal} (about {$this->remainingMinutes} minutes).",
            'session_uuid' => $this->session->uuid,
            'session_reference' => $this->session->reference,
            'package_ends_at' => $this->session->package_ends_at?->toIso8601String(),
            'remaining_minutes' => $this->remainingMinutes,
        ];
    }
}
