<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PublicTicketToken extends Model
{
    public const ACTIVE = 'active';
    public const REVOKED = 'revoked';

    protected $fillable = ['qr_ticket_id', 'token_hash', 'token_encrypted', 'status', 'issued_at', 'revoked_at'];
    protected $hidden = ['token_hash', 'token_encrypted'];

    protected function casts(): array
    {
        return ['token_encrypted' => 'encrypted', 'issued_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    public function qrTicket(): BelongsTo
    {
        return $this->belongsTo(QrTicket::class);
    }
}
