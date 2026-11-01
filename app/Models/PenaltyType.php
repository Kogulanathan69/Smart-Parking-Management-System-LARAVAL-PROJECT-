<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PenaltyType extends Model
{
    protected $fillable = ['code', 'name', 'description', 'amount_minor', 'is_active', 'updated_by'];

    protected function casts(): array
    {
        return ['amount_minor' => 'integer', 'is_active' => 'boolean'];
    }

    public function sessionPenalties(): HasMany
    {
        return $this->hasMany(SessionPenalty::class);
    }
}
