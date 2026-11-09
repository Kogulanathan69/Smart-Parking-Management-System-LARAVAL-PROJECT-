<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SettingHistory extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['system_setting_id', 'old_value', 'new_value', 'changed_by'];

    protected function casts(): array
    {
        return ['old_value' => 'json', 'new_value' => 'json'];
    }

    public function setting(): BelongsTo
    {
        return $this->belongsTo(SystemSetting::class, 'system_setting_id');
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
