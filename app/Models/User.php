<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable implements PasskeyUser
{
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    public const ROLE_SUPER_ADMIN = 'super_admin';

    public const ROLE_ADMIN = 'admin';

    public const ROLE_STAFF = 'staff';

    public const ROLE_AUDITOR = 'auditor';

    public const ROLE_CUSTOMER = 'customer';

    public const ROLES = [
        self::ROLE_SUPER_ADMIN, self::ROLE_ADMIN, self::ROLE_STAFF, self::ROLE_AUDITOR, self::ROLE_CUSTOMER,
    ];

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public const STATUS_SUSPENDED = 'suspended';

    /** Dashboard path per role (used for post-login redirects). */
    public const DASHBOARDS = [
        self::ROLE_SUPER_ADMIN => '/super-admin/dashboard',
        self::ROLE_ADMIN => '/admin/dashboard',
        self::ROLE_STAFF => '/staff/dashboard',
        self::ROLE_AUDITOR => '/auditor/dashboard',
        self::ROLE_CUSTOMER => '/customer/dashboard',
    ];

    /**
     * role and status are deliberately NOT mass assignable (no privilege escalation via request input).
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'status_changed_at' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    public function hasRole(string $role): bool
    {
        return $this->role === $role;
    }

    public function hasAnyRole(string ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isInternal(): bool
    {
        return $this->role !== self::ROLE_CUSTOMER;
    }

    public function dashboardPath(): string
    {
        return self::DASHBOARDS[$this->role] ?? '/';
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(self::class, 'created_by');
    }

    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class, 'owner_id');
    }

    public function parkingSessions(): HasMany
    {
        return $this->hasMany(ParkingSession::class, 'customer_id');
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(Receipt::class, 'customer_id');
    }

    public function credits(): HasMany
    {
        return $this->hasMany(CustomerCredit::class, 'customer_id');
    }

    public function complaints(): HasMany
    {
        return $this->hasMany(Complaint::class, 'customer_id');
    }

    public function shiftAssignments(): HasMany
    {
        return $this->hasMany(ShiftAssignment::class);
    }
}
