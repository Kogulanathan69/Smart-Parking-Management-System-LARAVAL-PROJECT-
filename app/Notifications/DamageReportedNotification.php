<?php

namespace App\Notifications;

use App\Models\Incident;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class DamageReportedNotification extends Notification
{
    use Queueable;

    public function __construct(public Incident $incident, public bool $forCustomer) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'damage_report',
            'title' => 'Damage report '.$this->incident->reference,
            'message' => $this->forCustomer
                ? 'Our staff recorded possible damage to your vehicle. Please contact the parking office.'
                : 'New damage reported ('.$this->incident->severity.').',
            'incident_reference' => $this->incident->reference,
        ];
    }
}
