<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnprCameraHealthLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['anpr_camera_id', 'component', 'status', 'message', 'latency_ms'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function camera(): BelongsTo
    {
        return $this->belongsTo(AnprCamera::class, 'anpr_camera_id');
    }
}
