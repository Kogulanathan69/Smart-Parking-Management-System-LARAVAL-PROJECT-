<?php

namespace App\Services;

use App\Exceptions\ParkingException;
use App\Models\SettingHistory;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Runtime business settings: DB overrides on top of config('parking.defaults').
 */
class SettingsService
{
    private const CACHE_KEY = 'parking.settings';

    public function __construct(private AuditLogger $audit) {}

    public function get(string $key, mixed $fallback = null): mixed
    {
        $all = $this->all();

        return array_key_exists($key, $all) ? $all[$key] : $fallback;
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        $overrides = Cache::rememberForever(self::CACHE_KEY, fn () => SystemSetting::query()->pluck('value', 'key')->all());

        return array_merge(config('parking.defaults'), $overrides);
    }

    public function canEdit(User $user, string $key): bool
    {
        $group = explode('.', $key)[0];
        $roles = config("parking.setting_groups.$group", []);

        return in_array($user->role, $roles, true);
    }

    public function set(string $key, mixed $value, User $by): SystemSetting
    {
        if (! array_key_exists($key, config('parking.defaults'))) {
            throw new ParkingException("Unknown setting [$key].", 'unknown_setting');
        }
        if (! $this->canEdit($by, $key)) {
            throw new ParkingException('You may not change this setting.', 'forbidden_setting', 403);
        }

        return DB::transaction(function () use ($key, $value, $by) {
            $setting = SystemSetting::firstOrNew(['key' => $key]);
            $old = $setting->exists ? $setting->value : config("parking.defaults.$key");
            $setting->fill([
                'value' => $value,
                'type' => get_debug_type($value),
                'group' => explode('.', $key)[0],
                'updated_by' => $by->id,
            ])->save();

            SettingHistory::create(['system_setting_id' => $setting->id, 'old_value' => $old, 'new_value' => $value, 'changed_by' => $by->id]);
            $this->audit->log('setting.updated', $setting, ['value' => $old], ['value' => $value], ['key' => $key]);
            DB::afterCommit(fn () => Cache::forget(self::CACHE_KEY));
            Cache::forget(self::CACHE_KEY);

            return $setting;
        });
    }
}
