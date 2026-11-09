<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnprEventImage extends Model
{
    protected $fillable = ['uuid', 'anpr_event_id', 'category', 'disk', 'path', 'mime_type', 'size_bytes', 'sha256', 'captured_at', 'retention_status', 'purged_at'];

    protected $hidden = ['disk', 'path'];

    protected function casts(): array
    {
        return ['captured_at' => 'datetime', 'purged_at' => 'datetime', 'size_bytes' => 'integer'];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(AnprEvent::class, 'anpr_event_id');
    }
}
