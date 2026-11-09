<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Receipt extends Model
{
    use HasFactory;

    protected $fillable = [
        'number', 'parking_session_id', 'customer_id', 'total_minor', 'paid_minor',
        'credit_issued_minor', 'refund_minor', 'currency', 'breakdown', 'issued_by', 'issued_at',
    ];

    protected function casts(): array
    {
        return [
            'breakdown' => 'array',
            'issued_at' => 'datetime',
            'total_minor' => 'integer',
            'paid_minor' => 'integer',
            'credit_issued_minor' => 'integer',
            'refund_minor' => 'integer',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(ParkingSession::class, 'parking_session_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }
}
