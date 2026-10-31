<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class SafetyInspection extends Model
{
    use HasFactory;

    public const TYPE_ENTRY = 'entry';

    public const TYPE_ROUTINE = 'routine';

    public const TYPE_EXIT = 'exit';

    public const SCHEDULED = 'scheduled';

    public const COMPLETED = 'completed';

    public const MISSED = 'missed';

    public const CONDITIONS = ['good', 'minor_damage', 'major_damage'];

    protected $fillable = [
        'parking_session_id', 'type', 'status', 'scheduled_for', 'performed_at',
        'inspected_by', 'condition', 'notes', 'new_damage_found',
    ];

    protected function casts(): array
    {
        return ['scheduled_for' => 'datetime', 'performed_at' => 'datetime', 'new_damage_found' => 'boolean'];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(ParkingSession::class, 'parking_session_id');
    }

    public function inspector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspected_by');
    }

    public function evidence(): MorphMany
    {
        return $this->morphMany(EvidenceFile::class, 'attachable');
    }
}
