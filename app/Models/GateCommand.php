<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GateCommand extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['parking_session_id', 'direction', 'gate_code', 'status', 'driver', 'result', 'requested_by', 'success_key'];

    protected $hidden = ['success_key'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(ParkingSession::class, 'parking_session_id');
    }
}
