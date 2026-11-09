<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditFinding extends Model
{
    public const SEVERITIES = ['info', 'low', 'medium', 'high'];

    protected $fillable = ['auditor_id', 'title', 'body', 'severity', 'subject_type', 'subject_id'];

    public function auditor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'auditor_id');
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
