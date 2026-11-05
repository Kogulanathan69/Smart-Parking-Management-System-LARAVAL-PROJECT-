<?php

namespace App\Services;

use App\Exceptions\ParkingException;
use App\Models\PackageRate;
use App\Models\ParkingPackage;
use App\Models\ParkingSession;
use App\Models\User;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Pricing rules (BRD §6–7). All amounts are integer minor units.
 *
 * The rate values AND the pricing policy in force at entry are copied into
 * parking_sessions.pricing_snapshot, so later price or policy changes never
 * apply retroactively to a session.
 */
class PricingService
{
    public function __construct(private SettingsService $settings, private AuditLogger $audit) {}

    /**
     * Append a new rate version. The previous open version is closed at the same instant.
     */
    public function publishRate(ParkingPackage $package, int $vehicleCategoryId, array $values, User $by, ?CarbonInterface $effectiveFrom = null): PackageRate
    {
        $effectiveFrom ??= now();

        return DB::transaction(function () use ($package, $vehicleCategoryId, $values, $by, $effectiveFrom) {
            $current = PackageRate::where('parking_package_id', $package->id)
                ->where('vehicle_category_id', $vehicleCategoryId)
                ->orderByDesc('version')
                ->lockForUpdate()
                ->first();

            if ($current && $current->effective_from > $effectiveFrom) {
                throw new ParkingException('A new rate cannot start before the current version.', 'invalid_effective_date');
            }
            $current?->forceFill(['effective_to' => $effectiveFrom])->save();

            $rate = PackageRate::create([
                'parking_package_id' => $package->id,
                'vehicle_category_id' => $vehicleCategoryId,
                'version' => ($current->version ?? 0) + 1,
                'price_minor' => $values['price_minor'],
                'overtime_hourly_minor' => $values['overtime_hourly_minor'] ?? 0,
                'overtime_daily_cap_minor' => $values['overtime_daily_cap_minor'] ?? null,
                'grace_minutes' => $values['grace_minutes'] ?? (int) $this->settings->get('pricing.grace_minutes'),
                'currency' => config('parking.currency'),
                'effective_from' => $effectiveFrom,
                'created_by' => $by->id,
            ]);

            $this->audit->log('pricing.rate_published', $rate, $current ? $current->only(['version', 'price_minor', 'overtime_hourly_minor', 'overtime_daily_cap_minor', 'grace_minutes']) : [], $rate->only(['version', 'price_minor', 'overtime_hourly_minor', 'overtime_daily_cap_minor', 'grace_minutes']), ['package' => $package->code]);

            return $rate;
        });
    }

    /**
     * Build the immutable snapshot stored on the session at entry.
     */
    public function snapshot(ParkingPackage $package, PackageRate $rate, ?PackageRate $safetyRate): array
    {
        return [
            'currency' => $rate->currency,
            'package' => [
                'id' => $package->id,
                'code' => $package->code,
                'billing_mode' => $package->billing_mode,
                'duration_minutes' => $package->duration_minutes,
                'allows_early_exit_credit' => $package->allows_early_exit_credit,
            ],
            'rate' => $rate->only(['id', 'version', 'price_minor', 'overtime_hourly_minor', 'overtime_daily_cap_minor', 'grace_minutes']),
            'safety' => $safetyRate?->only(['id', 'version', 'price_minor', 'overtime_hourly_minor', 'overtime_daily_cap_minor']),
            'policy' => [
                'billing_block_minutes' => (int) $this->settings->get('pricing.billing_block_minutes'),
                'overtime_starts_from' => $this->settings->get('pricing.overtime_starts_from'),
                'night_end_time' => $this->settings->get('pricing.night_end_time'),
                'early_exit_method' => $this->settings->get('pricing.early_exit_method'),
                'early_exit_minimum_days' => (int) $this->settings->get('pricing.early_exit_minimum_days'),
                'tax_basis_points' => (int) $this->settings->get('pricing.tax_basis_points'),
                'timezone' => config('parking.business_timezone'),
            ],
        ];
    }

    /**
     * When the package covered by the entry ends (null for open-ended hourly parking).
     */
    public function packageEndsAt(array $snapshot, CarbonInterface $entryAt): ?CarbonImmutable
    {
        $entry = CarbonImmutable::instance($entryAt);

        return match ($snapshot['package']['billing_mode']) {
            ParkingPackage::MODE_FIXED => $entry->addMinutes((int) $snapshot['package']['duration_minutes']),
            ParkingPackage::MODE_NIGHT => $this->nextNightEnd($entry, $snapshot['policy']['night_end_time'], $snapshot['policy']['timezone']),
            default => null,
        };
    }

    /**
     * Full charge calculation for a session if it exited at $exitAt. Pure: does not write anything.
     *
     * @return array{currency: string, entry_at: string, exit_at: string, duration_minutes: int, package_ends_at: ?string, lines: list<array{code: string, label: string, amount_minor: int, detail?: array}>, subtotal_minor: int, tax_minor: int, total_minor: int, early_exit_unused_minor: int, overtime_minutes: int}
     */
    public function quote(ParkingSession $session, CarbonInterface $exitAt): array
    {
        $snapshot = $session->pricing_snapshot;
        if (! empty($snapshot['reservation']) && $session->package_ends_at) {
            // Guaranteed parking: overtime is measured against the reservation's CURRENT expiry
            // (kept on the session and extended on renewal), not against this visit's entry time.
            $snapshot['reservation']['ends_at'] = $session->package_ends_at->toIso8601String();
        }

        return $this->calculate($snapshot, $session->entry_at, $exitAt, $session->has_safety);
    }

    public function calculate(array $snapshot, CarbonInterface $entryAt, CarbonInterface $exitAt, bool $hasSafety): array
    {
        $entry = CarbonImmutable::instance($entryAt)->utc();
        $exit = CarbonImmutable::instance($exitAt)->utc();
        if ($exit < $entry) {
            throw new ParkingException('Exit time cannot be before entry time.', 'invalid_exit_time');
        }

        $rate = $snapshot['rate'];
        $policy = $snapshot['policy'];
        $block = max(1, (int) $policy['billing_block_minutes']);
        $minutes = $this->minutesBetween($entry, $exit);
        $reservation = $snapshot['reservation'] ?? null;
        $endsAt = $reservation && ! empty($reservation['ends_at'])
            ? CarbonImmutable::parse($reservation['ends_at'])->utc()
            : $this->packageEndsAt($snapshot, $entry);
        $lines = [];
        $overtimeMinutes = 0;
        $unused = 0;

        if ($snapshot['package']['billing_mode'] === ParkingPackage::MODE_HOURLY) {
            $amount = $this->blockCharge(max($minutes, 1), $block, (int) $rate['price_minor'], $rate['overtime_daily_cap_minor']);
            $lines[] = ['code' => 'parking', 'label' => 'Short-time parking', 'amount_minor' => $amount, 'detail' => ['minutes' => $minutes, 'block_minutes' => $block, 'block_rate_minor' => (int) $rate['price_minor']]];
        } else {
            $price = (int) $rate['price_minor'];
            $grace = (int) $rate['grace_minutes'];

            if ($reservation) {
                // The package price is billed once per reservation period (see ExitService/ReservationService),
                // never per visit, and a temporary exit never earns an early-exit credit.
                $graceEnds = $endsAt->addMinutes($grace);
                if ($exit > $graceEnds) {
                    $from = $policy['overtime_starts_from'] === 'grace_end' ? $graceEnds : $endsAt;
                    $overtimeMinutes = $this->minutesBetween($from, $exit);
                    $amount = $this->blockCharge($overtimeMinutes, $block, (int) $rate['overtime_hourly_minor'], $rate['overtime_daily_cap_minor']);
                    $lines[] = ['code' => 'overtime', 'label' => 'Overtime after reservation expiry', 'amount_minor' => $amount, 'detail' => ['minutes' => $overtimeMinutes, 'hourly_minor' => (int) $rate['overtime_hourly_minor'], 'daily_cap_minor' => $rate['overtime_daily_cap_minor']]];
                }
            } elseif ($exit <= $endsAt) {
                $charge = $price;
                $durationDays = (int) ceil(((int) ($snapshot['package']['duration_minutes'] ?? 0)) / 1440);
                if ($snapshot['package']['allows_early_exit_credit'] && $policy['early_exit_method'] === 'prorate_daily' && $durationDays > 1) {
                    $usedDays = min($durationDays, max((int) $policy['early_exit_minimum_days'], (int) ceil($minutes / 1440)));
                    $charge = Money::divRound($price * $usedDays, $durationDays);
                    $unused = $price - $charge;
                }
                $lines[] = ['code' => 'package', 'label' => 'Package: '.$snapshot['package']['code'], 'amount_minor' => $charge, 'detail' => array_filter(['package_price_minor' => $price, 'early_exit_unused_minor' => $unused ?: null])];
            } else {
                $lines[] = ['code' => 'package', 'label' => 'Package: '.$snapshot['package']['code'], 'amount_minor' => $price];
                $graceEnds = $endsAt->addMinutes($grace);
                if ($exit > $graceEnds) {
                    $from = $policy['overtime_starts_from'] === 'grace_end' ? $graceEnds : $endsAt;
                    $overtimeMinutes = $this->minutesBetween($from, $exit);
                    $amount = $this->blockCharge($overtimeMinutes, $block, (int) $rate['overtime_hourly_minor'], $rate['overtime_daily_cap_minor']);
                    $lines[] = ['code' => 'overtime', 'label' => 'Overtime', 'amount_minor' => $amount, 'detail' => ['minutes' => $overtimeMinutes, 'hourly_minor' => (int) $rate['overtime_hourly_minor'], 'daily_cap_minor' => $rate['overtime_daily_cap_minor']]];
                }
            }
        }

        if ($hasSafety && ! empty($snapshot['safety'])) {
            $safety = $snapshot['safety'];
            $days = max(1, (int) ceil($minutes / 1440));
            $lines[] = ['code' => 'safety', 'label' => 'Safety parking', 'amount_minor' => $days * (int) $safety['price_minor'], 'detail' => ['days' => $days, 'daily_minor' => (int) $safety['price_minor']]];
            if ($overtimeMinutes > 0 && (int) $safety['overtime_hourly_minor'] > 0) {
                $lines[] = ['code' => 'safety_overtime', 'label' => 'Safety overtime', 'amount_minor' => $this->blockCharge($overtimeMinutes, $block, (int) $safety['overtime_hourly_minor'], $safety['overtime_daily_cap_minor']), 'detail' => ['minutes' => $overtimeMinutes]];
            }
        }

        $subtotal = array_sum(array_column($lines, 'amount_minor'));
        $tax = Money::divRound($subtotal * (int) ($policy['tax_basis_points'] ?? 0), 10000);

        return [
            'currency' => $snapshot['currency'],
            'entry_at' => $entry->toIso8601String(),
            'exit_at' => $exit->toIso8601String(),
            'duration_minutes' => $minutes,
            'package_ends_at' => $endsAt?->toIso8601String(),
            'lines' => $lines,
            'subtotal_minor' => $subtotal,
            'tax_minor' => $tax,
            'total_minor' => $subtotal + $tax,
            'early_exit_unused_minor' => $unused,
            'overtime_minutes' => $overtimeMinutes,
        ];
    }

    /**
     * Charge per started block, capped per 24-hour day (BRD §6: one overtime day never exceeds the daily rate).
     */
    public function blockCharge(int $minutes, int $blockMinutes, int $blockRate, ?int $dailyCap): int
    {
        $total = 0;
        while ($minutes > 0) {
            $dayMinutes = min($minutes, 1440);
            $dayCharge = (int) ceil($dayMinutes / $blockMinutes) * $blockRate;
            $total += $dailyCap !== null ? min($dayCharge, (int) $dailyCap) : $dayCharge;
            $minutes -= $dayMinutes;
        }

        return $total;
    }

    private function minutesBetween(CarbonInterface $from, CarbonInterface $to): int
    {
        $seconds = $to->getTimestamp() - $from->getTimestamp();

        return $seconds <= 0 ? 0 : (int) ceil($seconds / 60);
    }

    private function nextNightEnd(CarbonImmutable $entry, string $endTime, string $tz): CarbonImmutable
    {
        $local = $entry->setTimezone($tz);
        [$h, $m] = array_map('intval', explode(':', $endTime));
        $candidate = $local->setTime($h, $m);
        if ($candidate <= $local) {
            $candidate = $candidate->addDay();
        }

        return $candidate->utc();
    }
}
