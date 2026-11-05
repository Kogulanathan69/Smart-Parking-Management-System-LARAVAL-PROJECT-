<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{
    use HasFactory;

    public const CONFIRM_IMMEDIATE = 'immediate';

    public const CONFIRM_GATEWAY = 'gateway';

    protected $fillable = ['code', 'name', 'confirmation', 'requires_reference', 'is_active'];

    protected function casts(): array
    {
        return ['requires_reference' => 'boolean', 'is_active' => 'boolean'];
    }

    public function isImmediate(): bool
    {
        return $this->confirmation === self::CONFIRM_IMMEDIATE;
    }
}
