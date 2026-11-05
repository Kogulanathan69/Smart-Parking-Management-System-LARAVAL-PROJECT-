<?php

namespace App\Notifications;

use App\Models\Receipt;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ParkingReceiptNotification extends Notification
{
    use Queueable;

    public function __construct(public Receipt $receipt) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'receipt',
            'title' => 'Parking receipt '.$this->receipt->number,
            'message' => 'You paid '.$this->receipt->currency.' '.Money::format($this->receipt->paid_minor).'.',
            'receipt_number' => $this->receipt->number,
            'total_minor' => $this->receipt->total_minor,
        ];
    }
}
