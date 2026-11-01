<?php

namespace App\Services;

use App\Models\ParkingSession;
use App\Models\SessionReminder;
use App\Models\User;
use App\Notifications\PackageExpiryNotification;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Log;

/**
 * Package expiry reminders. Safe to run repeatedly or concurrently: each reminder is "claimed"
 * by inserting a row guarded by a unique index BEFORE notifying, so it is sent at most once.
 */
class ReminderService
{
    public function __construct(private SettingsService $settings) {}

    /**
     * @return array{expiring: int, expired: int}
     */
    public function process(?CarbonInterface $now = null): array
    {
        $now ??= now();
        $sent = ['expiring' => 0, 'expired' => 0];
        if (! $this->settings->get('reminders.enabled')) {
            return $sent;
        }

        $thresholds = collect($this->settings->get('reminders.before_expiry_minutes', []))
            ->map(fn ($m) => (int) $m)->filter(fn ($m) => $m > 0)->unique()->sort()->values();
        $horizon = $now->copy()->addMinutes((int) ($thresholds->max() ?? 0));
        $notifyExpired = (bool) $this->settings->get('reminders.notify_on_expiry');

        ParkingSession::query()
            ->with(['vehicle', 'customer'])
            ->where('status', ParkingSession::ACTIVE)
            ->whereNotNull('customer_id')
            ->whereNotNull('package_ends_at')
            ->where('package_ends_at', '<=', $horizon)
            ->chunkById(200, function ($sessions) use ($now, $thresholds, $notifyExpired, &$sent) {
                foreach ($sessions as $session) {
                    $customer = $session->customer;
                    if (! $customer || ! $customer->isActive() || $customer->role !== User::ROLE_CUSTOMER) {
                        continue;
                    }
                    $seconds = $session->package_ends_at->getTimestamp() - $now->getTimestamp();

                    if ($seconds <= 0) {
                        if ($notifyExpired && $this->claim($session, SessionReminder::EXPIRED, 0, $now)) {
                            $this->send($customer, new PackageExpiryNotification($session, 'expired'));
                            $sent['expired']++;
                        }

                        continue;
                    }

                    $remaining = (int) ceil($seconds / 60);
                    $crossed = $thresholds->filter(fn ($t) => $remaining <= $t);
                    if ($crossed->isEmpty()) {
                        continue;
                    }
                    // Claim every crossed threshold, but notify only once (for the tightest one) so a
                    // late first run never sends a burst of reminders.
                    $claimedAny = false;
                    foreach ($crossed as $threshold) {
                        $claimedAny = $this->claim($session, SessionReminder::EXPIRING, $threshold, $now) || $claimedAny;
                    }
                    if ($claimedAny) {
                        $this->send($customer, new PackageExpiryNotification($session, 'expiring', $remaining));
                        $sent['expiring']++;
                    }
                }
            });

        return $sent;
    }

    private function claim(ParkingSession $session, string $kind, int $threshold, CarbonInterface $now): bool
    {
        return SessionReminder::query()->insertOrIgnore([
            'parking_session_id' => $session->id,
            'kind' => $kind,
            'threshold_minutes' => $threshold,
            'sent_at' => $now,
        ]) === 1;
    }

    private function send(User $customer, PackageExpiryNotification $notification): void
    {
        try {
            $customer->notify($notification);
        } catch (\Throwable $e) {
            // The claim row stays, so a failing channel cannot cause repeated sends; the failure is logged.
            Log::warning('Package expiry reminder failed', ['session' => $notification->session->reference, 'error' => $e->getMessage()]);
        }
    }
}
