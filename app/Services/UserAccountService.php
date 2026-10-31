<?php

namespace App\Services;

use App\Exceptions\ParkingException;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Internal account management with an explicit permission matrix (BRD §3).
 */
class UserAccountService
{
    /**
     * Which roles each actor may manage (status/role changes) through the API.
     * Super Admins may suspend another Super Admin (never the last active one) but can
     * never CREATE one through the API — that is only possible via the protected CLI command.
     */
    public const MANAGEABLE_ROLES = [
        User::ROLE_SUPER_ADMIN => [User::ROLE_SUPER_ADMIN, User::ROLE_ADMIN, User::ROLE_STAFF, User::ROLE_AUDITOR, User::ROLE_CUSTOMER],
        User::ROLE_ADMIN => [User::ROLE_STAFF, User::ROLE_CUSTOMER],
    ];

    public function __construct(private AuditLogger $audit) {}

    public function canManageRole(User $actor, string $role): bool
    {
        return in_array($role, self::MANAGEABLE_ROLES[$actor->role] ?? [], true);
    }

    public function canManage(User $actor, User $target): bool
    {
        return $actor->id !== $target->id && $actor->isActive() && $this->canManageRole($actor, $target->role);
    }

    /**
     * @param  array{name: string, email: string, password: string, role: string, phone?: string|null}  $data
     */
    public function createInternal(User $actor, array $data): User
    {
        if ($data['role'] === User::ROLE_SUPER_ADMIN || ! $this->canManageRole($actor, $data['role'])) {
            throw new ParkingException('You may not create accounts with this role.', 'forbidden_role', 403);
        }

        return DB::transaction(function () use ($actor, $data) {
            $this->assertWithinLimit($data['role']);

            $user = new User;
            $user->forceFill([
                'name' => $data['name'],
                'email' => strtolower($data['email']),
                'phone' => $data['phone'] ?? null,
                'password' => Hash::make($data['password']),
                'role' => $data['role'],
                'status' => User::STATUS_ACTIVE,
                'created_by' => $actor->id,
            ])->save();

            $this->audit->log('user.created', $user, [], ['role' => $user->role, 'email' => $user->email]);

            return $user;
        });
    }

    public function changeStatus(User $actor, User $target, string $status, ?string $reason = null): User
    {
        if (! in_array($status, [User::STATUS_ACTIVE, User::STATUS_INACTIVE, User::STATUS_SUSPENDED], true)) {
            throw new ParkingException('Invalid status.', 'invalid_status');
        }
        if (! $this->canManage($actor, $target)) {
            throw new ParkingException('You may not change this account.', 'forbidden_account', 403);
        }

        return DB::transaction(function () use ($target, $status, $reason) {
            $locked = User::whereKey($target->id)->lockForUpdate()->firstOrFail();
            $this->assertNotLastActiveSuperAdmin($locked, $status);
            if ($status === User::STATUS_ACTIVE && $locked->status !== User::STATUS_ACTIVE) {
                $this->assertWithinLimit($locked->role, $locked->id);
            }

            $original = $locked->getAttributes();
            $locked->forceFill(['status' => $status, 'status_reason' => $reason, 'status_changed_at' => now()])->save();

            if ($status !== User::STATUS_ACTIVE) {
                // Kill every server-side session immediately.
                DB::table('sessions')->where('user_id', $locked->id)->delete();
                $locked->forceFill(['remember_token' => null])->saveQuietly();
            }

            $this->audit->logChanges('user.status_changed', $locked, $original, ['reason' => $reason]);

            return $locked;
        });
    }

    public function changeRole(User $actor, User $target, string $role): User
    {
        if ($role === User::ROLE_SUPER_ADMIN || ! $this->canManage($actor, $target) || ! $this->canManageRole($actor, $role)) {
            throw new ParkingException('You may not assign this role.', 'forbidden_role', 403);
        }

        return DB::transaction(function () use ($target, $role) {
            $locked = User::whereKey($target->id)->lockForUpdate()->firstOrFail();
            $this->assertNotLastActiveSuperAdmin($locked, 'demoted');
            $this->assertWithinLimit($role, $locked->id);
            $original = $locked->getAttributes();
            $locked->forceFill(['role' => $role])->save();
            $this->audit->logChanges('user.role_changed', $locked, $original);

            return $locked;
        });
    }

    /**
     * Used by the CLI provisioning command only.
     */
    public function provisionSuperAdmin(string $name, string $email, string $password): User
    {
        return DB::transaction(function () use ($name, $email, $password) {
            $this->assertWithinLimit(User::ROLE_SUPER_ADMIN);
            $user = new User;
            $user->forceFill([
                'name' => $name,
                'email' => strtolower($email),
                'password' => Hash::make($password),
                'role' => User::ROLE_SUPER_ADMIN,
                'status' => User::STATUS_ACTIVE,
                'email_verified_at' => now(),
            ])->save();
            $this->audit->log('user.super_admin_provisioned', $user, [], ['email' => $user->email], ['via' => 'cli']);

            return $user;
        });
    }

    public function assertWithinLimit(string $role, ?int $ignoreUserId = null): void
    {
        $limit = config("parking.account_limits.$role");
        if ($limit === null) {
            return;
        }
        $count = User::query()
            ->where('role', $role)
            ->where('status', User::STATUS_ACTIVE)
            ->when($ignoreUserId, fn ($q) => $q->whereKeyNot($ignoreUserId))
            ->lockForUpdate()
            ->count();
        if ($count >= $limit) {
            throw new ParkingException("The maximum number of active {$role} accounts ({$limit}) has been reached.", 'account_limit_reached');
        }
    }

    private function assertNotLastActiveSuperAdmin(User $target, string $newStatus): void
    {
        if ($target->role !== User::ROLE_SUPER_ADMIN || $newStatus === User::STATUS_ACTIVE) {
            return;
        }
        $others = User::where('role', User::ROLE_SUPER_ADMIN)->where('status', User::STATUS_ACTIVE)->whereKeyNot($target->id)->count();
        if ($others === 0) {
            throw new ParkingException('At least one active Super Admin must remain.', 'last_super_admin');
        }
    }
}
