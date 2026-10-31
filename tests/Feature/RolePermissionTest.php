<?php

use App\Exceptions\ParkingException;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\UserAccountService;

it('restricts each dashboard to its role', function (string $path, string $allowedRole) {
    foreach (User::ROLES as $role) {
        $response = $this->actingAs(userWithRole($role))->getJson($path);
        $role === $allowedRole ? $response->assertOk() : $response->assertForbidden();
    }
})->with([
    ['/super-admin/dashboard', User::ROLE_SUPER_ADMIN],
    ['/admin/dashboard', User::ROLE_ADMIN],
    ['/staff/dashboard', User::ROLE_STAFF],
    ['/auditor/dashboard', User::ROLE_AUDITOR],
    ['/customer/dashboard', User::ROLE_CUSTOMER],
]);

it('enforces the API role matrix', function (string $path, array $allowed) {
    seedParking();
    foreach (User::ROLES as $role) {
        $status = $this->actingAs(userWithRole($role))->getJson($path)->status();
        if (in_array($role, $allowed, true)) {
            expect($status)->toBe(200, "$role should access $path");
        } else {
            expect($status)->toBe(403, "$role must not access $path");
        }
    }
})->with([
    ['/api/v1/customer/vehicles', [User::ROLE_CUSTOMER]],
    ['/api/v1/staff/sessions', [User::ROLE_STAFF, User::ROLE_ADMIN, User::ROLE_SUPER_ADMIN]],
    ['/api/v1/admin/zones', [User::ROLE_ADMIN, User::ROLE_SUPER_ADMIN]],
    ['/api/v1/admin/settings', [User::ROLE_ADMIN, User::ROLE_SUPER_ADMIN]],
    ['/api/v1/reports/financial', [User::ROLE_ADMIN, User::ROLE_SUPER_ADMIN, User::ROLE_AUDITOR]],
    ['/api/v1/audit/logs', [User::ROLE_SUPER_ADMIN, User::ROLE_AUDITOR]],
]);

it('lets admins create staff but never admins or super admins', function () {
    $admin = userWithRole(User::ROLE_ADMIN);
    $payload = fn ($role, $email) => ['name' => 'X', 'email' => $email, 'password' => 'secret-pass-123', 'password_confirmation' => 'secret-pass-123', 'role' => $role];

    $this->actingAs($admin)->postJson('/api/v1/admin/users', $payload('staff', 's@x.test'))->assertCreated()->assertJsonPath('data.role', 'staff');
    $this->actingAs($admin)->postJson('/api/v1/admin/users', $payload('admin', 'a@x.test'))->assertForbidden();
    $this->actingAs($admin)->postJson('/api/v1/admin/users', $payload('auditor', 'au@x.test'))->assertForbidden();
    $this->actingAs($admin)->postJson('/api/v1/admin/users', $payload('super_admin', 'sa@x.test'))->assertUnprocessable();
});

it('lets super admins create admins up to the BRD limit of two', function () {
    $super = userWithRole(User::ROLE_SUPER_ADMIN);
    $make = fn ($email) => $this->actingAs($super)->postJson('/api/v1/admin/users', [
        'name' => 'Admin', 'email' => $email, 'password' => 'secret-pass-123', 'password_confirmation' => 'secret-pass-123', 'role' => 'admin',
    ]);

    $make('a1@x.test')->assertCreated();
    $make('a2@x.test')->assertCreated();
    $make('a3@x.test')->assertUnprocessable()->assertJsonPath('error_code', 'account_limit_reached');
});

it('suspends accounts, kills their sessions and audits it', function () {
    $admin = userWithRole(User::ROLE_ADMIN);
    $staff = userWithRole(User::ROLE_STAFF);

    $this->actingAs($admin)->patchJson("/api/v1/admin/users/{$staff->id}/status", ['status' => 'suspended', 'reason' => 'Investigation'])
        ->assertOk()->assertJsonPath('data.status', 'suspended');

    expect(AuditLog::where('action', 'user.status_changed')->where('auditable_id', $staff->id)->exists())->toBeTrue();
});

it('prevents admins from changing other admins or themselves', function () {
    $admin = userWithRole(User::ROLE_ADMIN);
    $other = userWithRole(User::ROLE_ADMIN);

    $this->actingAs($admin)->patchJson("/api/v1/admin/users/{$other->id}/status", ['status' => 'suspended'])->assertForbidden();
    $this->actingAs($admin)->patchJson("/api/v1/admin/users/{$admin->id}/status", ['status' => 'suspended'])->assertForbidden();
});

it('never removes the last active super admin', function () {
    $a = userWithRole(User::ROLE_SUPER_ADMIN);
    $b = userWithRole(User::ROLE_SUPER_ADMIN);
    $service = app(UserAccountService::class);

    $this->actingAs($a)->patchJson("/api/v1/admin/users/{$b->id}/status", ['status' => 'suspended'])->assertOk();

    // $b is now suspended, so $a is the last active one: it can never be removed.
    $c = userWithRole(User::ROLE_SUPER_ADMIN, ['status' => User::STATUS_SUSPENDED]);
    expect(fn () => $service->changeStatus($c->forceFill(['status' => User::STATUS_ACTIVE]), $a, 'suspended'))
        ->toThrow(ParkingException::class, 'At least one active Super Admin must remain.');

    $this->actingAs($a)->patchJson("/api/v1/admin/users/{$b->id}/role", ['role' => 'super_admin'])->assertUnprocessable();
});

it('provisions super admins only via the CLI command, within the limit', function () {
    $this->artisan('parking:create-super-admin', ['--name' => 'Root', '--email' => 'root@x.test'])
        ->expectsQuestion('Password (min 12 chars, letters, numbers, symbols)', 'Str0ng!Passw0rd')
        ->expectsQuestion('Confirm password', 'Str0ng!Passw0rd')
        ->assertSuccessful();

    expect(User::where('email', 'root@x.test')->value('role'))->toBe(User::ROLE_SUPER_ADMIN);

    userWithRole(User::ROLE_SUPER_ADMIN);
    $this->artisan('parking:create-super-admin', ['--name' => 'Third', '--email' => 'third@x.test'])
        ->expectsQuestion('Password (min 12 chars, letters, numbers, symbols)', 'Str0ng!Passw0rd')
        ->expectsQuestion('Confirm password', 'Str0ng!Passw0rd')
        ->assertFailed();
});

it('rejects weak super admin passwords in the CLI', function () {
    $this->artisan('parking:create-super-admin', ['--name' => 'Root', '--email' => 'root@x.test'])
        ->expectsQuestion('Password (min 12 chars, letters, numbers, symbols)', 'password')
        ->expectsQuestion('Confirm password', 'password')
        ->assertFailed();
});

it('prevents customers from reading other customers records (IDOR)', function () {
    seedParking();
    $owner = userWithRole(User::ROLE_CUSTOMER);
    $intruder = userWithRole(User::ROLE_CUSTOMER);

    $vehicle = $this->actingAs($owner)->postJson('/api/v1/customer/vehicles', ['plate_number' => 'CAB-1111', 'vehicle_category_id' => category('car')->id])
        ->assertCreated()->json('data.id');

    $this->actingAs($intruder)->getJson("/api/v1/customer/vehicles/{$vehicle}")->assertForbidden();
    $this->actingAs($intruder)->deleteJson("/api/v1/customer/vehicles/{$vehicle}")->assertForbidden();
    $this->actingAs($intruder)->postJson('/api/v1/customer/vehicles', ['plate_number' => 'cab 1111', 'vehicle_category_id' => category('car')->id])
        ->assertUnprocessable()->assertJsonPath('error_code', 'vehicle_unavailable');

    [$session] = recordEntry(userWithRole(User::ROLE_STAFF), ['plate_number' => 'CAB-1111']);
    expect($session->customer_id)->toBe($owner->id);
    $this->actingAs($owner)->getJson("/api/v1/customer/sessions/{$session->uuid}")->assertOk();
    $this->actingAs($intruder)->getJson("/api/v1/customer/sessions/{$session->uuid}")->assertForbidden();
});
