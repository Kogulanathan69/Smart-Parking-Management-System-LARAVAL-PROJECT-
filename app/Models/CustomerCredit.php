<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerCredit extends Model
{
    protected $fillable = ['customer_id', 'parking_session_id', 'amount_minor', 'reason', 'expires_at', 'created_by'];

    protected function casts(): array
    {
        return ['amount_minor' => 'integer', 'expires_at' => 'datetime'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public static function balanceFor(int $customerId): int
    {
        return (int) static::query()
            ->where('customer_id', $customerId)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->sum('amount_minor');
    }
}
