<?php

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;

it('registers public users as active customers and ignores a submitted role', function () {
    $this->postJson('/register', [
        'name' => 'Nimal Perera',
        'email' => 'Nimal@Example.com',
        'phone' => '+94 77 123 4567',
        'password' => 'secret-pass-123',
        'password_confirmation' => 'secret-pass-123',
        'role' => 'super_admin',
        'status' => 'active',
    ])->assertCreated()->assertJsonPath('redirect', '/customer/dashboard');

    $user = User::where('email', 'nimal@example.com')->firstOrFail();
    expect($user->role)->toBe(User::ROLE_CUSTOMER)
        ->and($user->status)->toBe(User::STATUS_ACTIVE)
        ->and($user->phone)->toBe('+94 77 123 4567');
    expect(AuditLog::where('action', 'user.registered')->exists())->toBeTrue();
});

it('rejects weak passwords at registration', function () {
    $this->postJson('/register', [
        'name' => 'X', 'email' => 'x@example.com', 'password' => 'short', 'password_confirmation' => 'short',
    ])->assertUnprocessable()->assertJsonValidationErrors('password');
});

it('redirects each role to its own dashboard after login', function (string $role, string $path) {
    $user = userWithRole($role);

    $this->postJson('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertOk()
        ->assertJsonPath('redirect', $path)
        ->assertJsonPath('user.role', $role);

    $this->getJson($path)->assertOk()->assertJsonPath('role', $role);
})->with([
    [User::ROLE_SUPER_ADMIN, '/super-admin/dashboard'],
    [User::ROLE_ADMIN, '/admin/dashboard'],
    [User::ROLE_STAFF, '/staff/dashboard'],
    [User::ROLE_AUDITOR, '/auditor/dashboard'],
    [User::ROLE_CUSTOMER, '/customer/dashboard'],
]);

it('redirects /dashboard to the role dashboard', function () {
    $this->actingAs(userWithRole(User::ROLE_STAFF))->get('/dashboard')->assertRedirect('/staff/dashboard');
});

it('blocks login for inactive and suspended accounts', function (string $state) {
    $user = User::factory()->{$state}()->create();

    $this->postJson('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');
    $this->assertGuest();
    expect(AuditLog::where('action', 'auth.login_blocked')->exists())->toBeTrue();
})->with(['suspended', 'inactive']);

it('rejects wrong passwords without revealing account state', function () {
    $user = User::factory()->suspended()->create();

    $this->postJson('/login', ['email' => $user->email, 'password' => 'wrong-password'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email' => __('auth.failed')]);
});

it('logs out a user whose account is suspended mid-session', function () {
    $user = userWithRole(User::ROLE_STAFF);
    $this->actingAs($user)->getJson('/api/v1/me')->assertOk();

    $user->forceFill(['status' => User::STATUS_SUSPENDED])->save();

    $this->getJson('/api/v1/me')->assertForbidden()->assertJsonPath('error_code', 'account_inactive');
    $this->assertGuest();
});

it('rate limits repeated failed logins', function () {
    $user = User::factory()->create();
    foreach (range(1, 5) as $attempt) {
        $this->postJson('/login', ['email' => $user->email, 'password' => 'bad'])->assertUnprocessable();
    }
    $this->postJson('/login', ['email' => $user->email, 'password' => 'bad'])->assertStatus(429);
});

it('logs out', function () {
    $this->actingAs(User::factory()->create())->postJson('/logout')->assertNoContent();
    $this->assertGuest();
});

it('sends password reset links', function () {
    Notification::fake();
    $user = User::factory()->create();

    $this->postJson('/forgot-password', ['email' => $user->email])->assertOk();

    Notification::assertSentTo($user, ResetPassword::class);
});

it('lets users update their own profile but not their role', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->putJson('/user/profile-information', [
        'name' => 'New Name', 'email' => $user->email, 'phone' => '0771234567', 'role' => 'admin',
    ])->assertOk();

    $user->refresh();
    expect($user->name)->toBe('New Name')->and($user->phone)->toBe('0771234567')->and($user->role)->toBe(User::ROLE_CUSTOMER);
});

it('requires authentication for the API', function () {
    $this->getJson('/api/v1/me')->assertUnauthorized();
});
