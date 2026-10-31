<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class AuditLogger
{
    /** Attributes that must never be written into audit history. */
    private const REDACT = ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes', 'token_encrypted', 'token_hash'];

    public function log(string $action, ?Model $subject = null, array $old = [], array $new = [], array $meta = []): AuditLog
    {
        $request = app()->runningInConsole() && ! app()->runningUnitTests() ? null : request();
        $actor = auth()->user();

        return AuditLog::create([
            'actor_id' => $actor?->getKey(),
            'actor_role' => $actor?->role ?? (app()->runningInConsole() ? 'system' : null),
            'action' => $action,
            'auditable_type' => $subject?->getMorphClass(),
            'auditable_id' => $subject?->getKey(),
            'old_values' => Arr::except($old, self::REDACT) ?: null,
            'new_values' => Arr::except($new, self::REDACT) ?: null,
            'meta' => $meta ?: null,
            'ip_address' => $request?->ip(),
            'user_agent' => $request ? substr((string) $request->userAgent(), 0, 255) : null,
        ]);
    }

    /**
     * Log only the attributes that changed on a model that was just saved.
     */
    public function logChanges(string $action, Model $model, array $original, array $meta = []): AuditLog
    {
        $changes = Arr::except($model->getChanges(), ['updated_at']);

        return $this->log($action, $model, Arr::only($original, array_keys($changes)), $changes, $meta);
    }
}
