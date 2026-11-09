<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ShiftAssignment extends Model
{
    use HasFactory;

    public const SCHEDULED = 'scheduled';

    public const ON_LEAVE = 'on_leave';

    public const REPLACED = 'replaced';

    public const CANCELLED = 'cancelled';

    protected $fillable = ['shift_id', 'user_id', 'shift_date', 'is_gate_duty', 'status', 'replacement_for_id', 'assigned_by', 'notes'];

    protected function casts(): array
    {
        return ['shift_date' => 'date:Y-m-d', 'is_gate_duty' => 'boolean'];
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function attendance(): HasOne
    {
        return $this->hasOne(Attendance::class);
    }

    /**
     * Shift start/end as UTC instants, computed from local business time.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function window(): array
    {
        $tz = config('parking.business_timezone');
        $date = $this->shift_date->format('Y-m-d');
        $start = CarbonImmutable::parse($date.' '.$this->shift->start_time, $tz);
        $end = CarbonImmutable::parse($date.' '.$this->shift->end_time, $tz);
        if ($end <= $start) {
            $end = $end->addDay();
        }

        return [$start->utc(), $end->utc()];
    }
}
