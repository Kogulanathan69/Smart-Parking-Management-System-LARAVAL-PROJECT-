<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SystemSetting extends Model
{
    protected $fillable = ['key', 'value', 'type', 'group', 'description', 'updated_by'];

    protected function casts(): array
    {
        return ['value' => 'json'];
    }

    public function histories(): HasMany
    {
        return $this->hasMany(SettingHistory::class);
    }
}
