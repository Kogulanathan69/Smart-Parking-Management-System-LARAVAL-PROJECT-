<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Incident extends Model
{
    use HasFactory;

    public const TYPES = ['damage', 'theft', 'accident', 'other'];

    public const STATUSES = ['open', 'investigating', 'resolved', 'closed'];

    protected $fillable = [
        'reference', 'parking_session_id', 'vehicle_id', 'safety_inspection_id', 'reported_by', 'type',
        'severity', 'description', 'status', 'resolution', 'compensation_minor', 'resolved_by', 'resolved_at',
    ];

    protected function casts(): array
    {
        return ['compensation_minor' => 'integer', 'resolved_at' => 'datetime'];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(ParkingSession::class, 'parking_session_id');
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class)->withTrashed();
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function evidence(): MorphMany
    {
        return $this->morphMany(EvidenceFile::class, 'attachable');
    }
}
