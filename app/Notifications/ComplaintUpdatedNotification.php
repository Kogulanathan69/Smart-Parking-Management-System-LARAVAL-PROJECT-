<?php

namespace App\Notifications;

use App\Models\Complaint;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ComplaintUpdatedNotification extends Notification
{
    use Queueable;

    public function __construct(public Complaint $complaint) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'complaint_update',
            'title' => 'Complaint '.$this->complaint->reference.' updated',
            'message' => 'Status: '.$this->complaint->status,
            'complaint_reference' => $this->complaint->reference,
        ];
    }
}
