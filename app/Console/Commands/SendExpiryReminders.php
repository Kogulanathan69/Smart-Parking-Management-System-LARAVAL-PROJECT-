<?php

namespace App\Console\Commands;

use App\Services\ReminderService;
use Illuminate\Console\Command;

class SendExpiryReminders extends Command
{
    protected $signature = 'parking:send-expiry-reminders';

    protected $description = 'Send in-app reminders for parking packages that are about to expire or have expired (idempotent).';

    public function handle(ReminderService $reminders): int
    {
        $sent = $reminders->process();
        $this->info("Sent {$sent['expiring']} expiring and {$sent['expired']} expired reminders.");

        return self::SUCCESS;
    }
}
