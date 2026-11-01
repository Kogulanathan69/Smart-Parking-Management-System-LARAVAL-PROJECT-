<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SessionReminder extends Model
{
    public const EXPIRING = 'expiring';
    public const EXPIRED = 'expired';

    public $timestamps = false;

    protected $fillable = ['parking_session_id', 'kind', 'threshold_minutes', 'sent_at'];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime', 'threshold_minutes' => 'integer'];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(ParkingSession::class, 'parking_session_id');
    }
}
