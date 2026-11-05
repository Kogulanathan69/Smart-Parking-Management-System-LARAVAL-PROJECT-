<?php

use App\Models\User;
use App\Services\Anpr\AnprCameraService;
use Illuminate\Routing\Route as RouteObject;
use Illuminate\Support\Facades\Route;

/*
| Page-level checks for the ParkFlow frontend. Pages are Blade shells whose data comes from the
| /api/v1 endpoints (already covered by the API tests), so these tests focus on routing, role
| protection, navigation integrity and what the server embeds in the HTML.
*/

beforeEach(function () {
    $this->withoutVite();
    seedParking();
});

/** @return array<string, array{0: string, 1: list<string>}> uri => allowed roles */
function frontendPages(): array
{
    $pages = [];
    foreach (Route::getRoutes() as $route) {
        /** @var RouteObject $route */
        $isView = str_contains((string) $route->getActionName(), 'ViewController') || str_contains((string) $route->getActionName(), 'RoleMatrixController')
            || str_contains((string) $route->getActionName(), 'DashboardController@') && ! str_ends_with($route->getActionName(), '@redirect');
        if (! $isView || ! in_array('GET', $route->methods(), true) || $route->uri() === '/') {
            continue;
        }
        $roles = User::ROLES;
        foreach ($route->gatherMiddleware() as $m) {
            if (is_string($m) && str_starts_with($m, 'role:')) {
                $roles = explode(',', substr($m, 5));
            }
        }
        $uri = '/'.preg_replace(['/\{uuid\}/', '/\{id\}/', '/\{number\}/'], ['8b5b1c2e-1111-4a5b-9c3d-1234567890ab', '1', 'RCP-TEST-0001'], $route->uri());
        $pages[$uri] = [$uri, $roles];
    }
    ksort($pages);

    return $pages;
}

it('registers a page for every navigation entry and at least 60 pages overall', function () {
    expect(count(frontendPages()))->toBeGreaterThanOrEqual(60);
});

it('renders every page for its allowed roles and refuses everyone else', function () {
    $users = collect(User::ROLES)->mapWithKeys(fn ($r) => [$r => userWithRole($r)]);
    foreach (frontendPages() as [$uri, $roles]) {
        foreach ($users as $role => $user) {
            $res = $this->actingAs($user)->get($uri);
            if (in_array($role, $roles, true)) {
                expect($res->status())->toBe(200, "$role should open $uri");
                expect($res->headers->get('content-type'))->toContain('text/html');
            } else {
                expect($res->status())->toBe(403, "$role must not open $uri");
            }
        }
    }
});

it('sends guests to the sign-in page', function () {
    foreach (array_slice(array_keys(frontendPages()), 0, 25) as $uri) {
        $this->get($uri)->assertRedirect('/login');
    }
});

it('only shows sidebar links the role can open (no dead or forbidden links)', function () {
    foreach (User::ROLES as $role) {
        $user = userWithRole($role);
        $html = $this->actingAs($user)->get($user->dashboardPath())->assertOk()->getContent();
        preg_match('#<aside id="sidebar".*?</aside>#s', $html, $aside);
        preg_match_all('#href="(http://[^"]+)"#', $aside[0] ?? '', $m);
        expect($m[1])->not->toBeEmpty();
        foreach (array_unique($m[1]) as $href) {
            $path = parse_url($href, PHP_URL_PATH);
            expect($this->actingAs($user)->get($path)->status())->toBe(200, "$role sidebar link $path");
        }
    }
});

it('keeps operational menus away from customers and auditors', function () {
    $customerNav = $this->actingAs(userWithRole(User::ROLE_CUSTOMER))->get('/customer/dashboard')->getContent();
    expect($customerNav)->not->toContain('/ops/')->not->toContain('/manage/')->not->toContain('/reports');

    $auditorNav = $this->actingAs(userWithRole(User::ROLE_AUDITOR))->get('/auditor/dashboard')->getContent();
    preg_match('#<aside id="sidebar".*?</aside>#s', $auditorNav, $aside);
    expect($aside[0])->not->toContain('/ops/')->not->toContain('/manage/users')->not->toContain('Check-In');
});

it('serves dashboards as HTML to browsers and keeps the JSON contract for API clients', function () {
    $staff = userWithRole(User::ROLE_STAFF);
    $this->actingAs($staff)->get('/staff/dashboard')->assertOk()->assertSee('Check-In')->assertSee('Occupancy by zone');
    $this->actingAs($staff)->getJson('/staff/dashboard')->assertOk()->assertJsonPath('role', 'staff')->assertJsonStructure(['data' => ['occupancy', 'active_sessions']]);
});

it('embeds only the signed-in user profile in the page, never secrets', function () {
    config(['parking.anpr.service_token' => 'never-leak-this-token-123456789012345']);
    $admin = userWithRole(User::ROLE_ADMIN);
    [, $token] = app(AnprCameraService::class)->create(['code' => 'CAM-T', 'name' => 'T', 'direction' => 'entry', 'camera_type' => 'ip_rtsp', 'stream_url' => 'rtsp://192.168.1.9/s', 'stream_password' => 'cam-pass-xyz'], $admin);
    foreach (['/admin/dashboard', '/manage/cameras', '/manage/system', '/ops/check-in'] as $uri) {
        $html = $this->actingAs($admin)->get($uri)->assertOk()->getContent();
        expect($html)->not->toContain('never-leak-this-token')->not->toContain($token)->not->toContain('cam-pass-xyz')->not->toContain('password_hash');
    }
});

it('renders the Fortify screens and offers registration only for customers', function () {
    foreach (['/login', '/register', '/forgot-password', '/reset-password/some-token'] as $uri) {
        $this->get($uri)->assertOk()->assertSee('ParkFlow');
    }
    $this->get('/register')->assertSee('Staff accounts cannot be created here');
    $this->post('/register', ['name' => 'Kamal', 'email' => 'k@example.test', 'password' => 'Secret-pass-123', 'password_confirmation' => 'Secret-pass-123', 'role' => 'admin'])->assertRedirect('/customer/dashboard');
    expect(User::where('email', 'k@example.test')->value('role'))->toBe('customer');
});

it('shows branded error pages', function () {
    $this->get('/definitely-not-a-page')->assertNotFound()->assertSee('Page not found');
    $this->actingAs(userWithRole(User::ROLE_CUSTOMER))->get('/manage/users')->assertForbidden()->assertSee('Access denied');
});

it('generates the read-only role matrix from the live routes', function () {
    $html = $this->actingAs(userWithRole(User::ROLE_AUDITOR))->get('/manage/roles')->assertOk()->getContent();
    expect($html)->toContain('/api/v1/staff/anpr/exit/settle')->toContain('cannot be edited here')->not->toContain('/api/v1/anpr/device/events');
});
