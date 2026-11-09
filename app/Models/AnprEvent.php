<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AnprEvent extends Model
{
    public const RECOGNIZED = 'recognized';

    public const LOW_CONFIDENCE = 'low_confidence';

    public const UNREADABLE = 'unreadable';

    public const ENGINE_ERROR = 'engine_error';

    public const MANUAL = 'manual';

    public const DUPLICATE = 'duplicate';

    public const PENDING = 'pending';

    public const VERIFIED = 'verified';

    public const CORRECTED = 'corrected';

    public const REJECTED = 'rejected';

    /** Reasons accepted for a human override / manual identification. */
    public const OVERRIDE_REASONS = ['camera_offline', 'unreadable', 'low_confidence', 'damaged_plate', 'incorrect_result', 'no_plate', 'engine_unavailable'];

    protected $fillable = [
        'uuid', 'anpr_camera_id', 'direction', 'source', 'captured_at', 'raw_text', 'normalized_plate', 'plate_format',
        'candidates', 'review_suggestions', 'partial_review_clues', 'confidence_bp', 'recognition_status', 'verification_status', 'verified_plate', 'verified_by',
        'verified_at', 'override_reason', 'override_notes', 'vehicle_id', 'parking_session_id', 'duplicate_of_id',
        'consumed_at', 'error_code', 'processing_ms', 'idempotency_key', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'captured_at' => 'datetime',
            'verified_at' => 'datetime',
            'consumed_at' => 'datetime',
            'candidates' => 'array',
            'review_suggestions' => 'array',
            'partial_review_clues' => 'array',
            'confidence_bp' => 'integer',
            'processing_ms' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function camera(): BelongsTo
    {
        return $this->belongsTo(AnprCamera::class, 'anpr_camera_id');
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class)->withTrashed();
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(ParkingSession::class, 'parking_session_id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(AnprEventImage::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(AnprReviewAction::class)->orderBy('id');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function isVerified(): bool
    {
        return in_array($this->verification_status, [self::VERIFIED, self::CORRECTED], true);
    }

    /**
     * The plate a human has confirmed (only meaningful when verified).
     */
    public function effectivePlate(): ?string
    {
        return $this->isVerified() ? $this->verified_plate : null;
    }
}
