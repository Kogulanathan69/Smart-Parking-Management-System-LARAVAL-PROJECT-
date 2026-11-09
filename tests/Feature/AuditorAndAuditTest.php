<?php

use App\Models\AuditLog;
use App\Models\SettingHistory;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\Route;

beforeEach(fn () => seedParking());

it('gives auditors read access to reports, transactions and audit history', function () {
    $auditor = userWithRole(User::ROLE_AUDITOR);
    recordEntry(userWithRole(User::ROLE_STAFF));

    foreach (['overview', 'occupancy', 'financial', 'staff-performance', 'sessions', 'payments', 'inspections'] as $report) {
        $this->actingAs($auditor)->getJson("/api/v1/reports/{$report}")->assertOk();
    }
    $this->actingAs($auditor)->getJson('/api/v1/audit/logs')->assertOk()->assertJsonPath('data.0.action', 'session.entry_recorded');
});

it('forbids auditors from every state-changing operational endpoint', function () {
    $auditor = userWithRole(User::ROLE_AUDITOR);
    $writeRoutes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($r) => str_starts_with($r->uri(), 'api/v1/') && array_intersect($r->methods(), ['POST', 'PUT', 'PATCH', 'DELETE']))
        ->reject(fn ($r) => in_array($r->uri(), ['api/v1/audit/findings', 'api/v1/notifications/read-all', 'api/v1/notifications/{id}/read'], true))
        // Camera-agent routes use device authentication, not user roles (covered in AnprDeviceTest: 401 for any user).
        ->reject(fn ($r) => str_starts_with($r->uri(), 'api/v1/anpr/device/'));

    expect($writeRoutes->count())->toBeGreaterThan(30);

    foreach ($writeRoutes as $route) {
        $uri = preg_replace('/\{[^}]+\}/', '1', str_replace('{key}', 'pricing.grace_minutes', $route->uri()));
        $method = collect($route->methods())->first(fn ($m) => $m !== 'HEAD');
        $status = $this->actingAs($auditor)->json($method, '/'.$uri)->status();
        expect($status)->toBe(403, "Auditor reached {$method} /{$uri} ({$status})");
    }
});

it('lets auditors add findings but not alter originals', function () {
    $auditor = userWithRole(User::ROLE_AUDITOR);
    [$session] = recordEntry(userWithRole(User::ROLE_STAFF));

    $this->actingAs($auditor)->postJson('/api/v1/audit/findings', [
        'title' => 'Late exit not charged', 'body' => 'Check overtime.', 'severity' => 'medium', 'subject_type' => 'session', 'subject_id' => $session->id,
    ])->assertCreated();

    $this->actingAs(userWithRole(User::ROLE_ADMIN))->postJson('/api/v1/audit/findings', ['title' => 'x', 'body' => 'y'])->assertForbidden();
});

it('keeps audit logs immutable', function () {
    $log = app(AuditLogger::class)->log('test.action');

    expect(fn () => $log->update(['action' => 'tampered']))->toThrow(LogicException::class);
    expect(fn () => $log->delete())->toThrow(LogicException::class);
    expect(AuditLog::find($log->id)->action)->toBe('test.action');
});

it('never writes secrets into the audit log', function () {
    $log = app(AuditLogger::class)->log('test', null, [], ['password' => 'x', 'token_hash' => 'y', 'name' => 'ok']);

    expect($log->new_values)->toBe(['name' => 'ok']);
});

it('lets admins change pricing settings with history, but only super admins exit settings', function () {
    $admin = userWithRole(User::ROLE_ADMIN);

    $this->actingAs($admin)->putJson('/api/v1/admin/settings/pricing.grace_minutes', ['value' => 20])->assertOk()->assertJsonPath('value', 20);
    $this->actingAs($admin)->putJson('/api/v1/admin/settings/pricing.grace_minutes', ['value' => -5])->assertUnprocessable();
    $this->actingAs($admin)->putJson('/api/v1/admin/settings/exit.release_slot_on', ['value' => 'gate'])->assertForbidden();
    $this->actingAs(userWithRole(User::ROLE_SUPER_ADMIN))->putJson('/api/v1/admin/settings/exit.release_slot_on', ['value' => 'gate'])->assertOk();

    expect(SettingHistory::count())->toBe(2)
        ->and(AuditLog::where('action', 'setting.updated')->count())->toBe(2);
});
