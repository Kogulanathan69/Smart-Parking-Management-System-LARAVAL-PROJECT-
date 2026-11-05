<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QrTicket extends Model
{
    use HasFactory;

    public const ACTIVE = 'active';

    public const USED = 'used';

    public const VOID = 'void';

    protected $fillable = ['parking_session_id', 'token_hash', 'token_encrypted', 'status', 'print_count', 'issued_at', 'used_at'];

    protected $hidden = ['token_hash', 'token_encrypted'];

    protected function casts(): array
    {
        return ['token_encrypted' => 'encrypted', 'issued_at' => 'datetime', 'used_at' => 'datetime'];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(ParkingSession::class, 'parking_session_id');
    }
}
