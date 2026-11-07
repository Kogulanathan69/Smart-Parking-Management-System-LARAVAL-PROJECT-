<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\RoleMatrixController;
use App\Models\Vehicle;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

/*
| Role dashboards. Paths are unchanged from the original setup; closures were replaced by
| DashboardController so each dashboard returns real summary data.
*/
Route::middleware('auth')->get('/dashboard', [DashboardController::class, 'redirect'])->name('dashboard');

Route::middleware(['auth', 'role:super_admin'])
    ->get('/super-admin/dashboard', [DashboardController::class, 'superAdmin'])
    ->name('dashboard.super-admin');

Route::middleware(['auth', 'role:admin'])
    ->get('/admin/dashboard', [DashboardController::class, 'admin'])
    ->name('dashboard.admin');

Route::middleware(['auth', 'role:staff'])
    ->get('/staff/dashboard', [DashboardController::class, 'staff'])
    ->name('dashboard.staff');

Route::middleware(['auth', 'role:auditor'])
    ->get('/auditor/dashboard', [DashboardController::class, 'auditor'])
    ->name('dashboard.auditor');

Route::middleware(['auth', 'role:customer'])
    ->get('/customer/dashboard', [DashboardController::class, 'customer'])
    ->name('dashboard.customer');

/*
| ParkFlow frontend pages (Blade + Alpine). Pages render the shell only; all data comes from the
| /api/v1 JSON API with the same session, so every read and write is authorised there too.
| Page roles mirror the API roles they use. Route parameters are passed to the views.
*/
$page = fn (string $uri, string $view, string $name) => Route::view($uri, 'pages.'.$view)->name($name);
$uuid = '[0-9a-fA-F-]{36}';

Route::middleware('auth')->group(function () use ($page) {
    $page('/account', 'common.account', 'account');
    $page('/notifications', 'common.notifications', 'notifications');
});

Route::middleware(['auth', 'role:staff,admin,super_admin'])->prefix('ops')->group(function () use ($page, $uuid) {
    $page('/check-in', 'ops.check-in', 'ops.check-in');
    $page('/check-out', 'ops.check-out', 'ops.check-out');
    $page('/anpr', 'ops.anpr-queue', 'ops.anpr.queue');
    $page('/anpr/{uuid}', 'ops.anpr-show', 'ops.anpr.show')->where('uuid', $uuid);
    $page('/sessions', 'ops.sessions', 'ops.sessions');
    $page('/sessions/{uuid}', 'ops.session-show', 'ops.sessions.show')->where('uuid', $uuid);
    $page('/expiring', 'ops.expiring', 'ops.expiring');
    $page('/availability', 'ops.availability', 'ops.availability');
    $page('/vehicles', 'ops.vehicles', 'ops.vehicles');
    $page('/vehicles/{id}', 'ops.vehicle-show', 'ops.vehicles.show')->whereNumber('id');
    $page('/reservations', 'ops.reservations', 'ops.reservations');
    $page('/reservations/{id}', 'ops.reservation-show', 'ops.reservations.show')->whereNumber('id');
    $page('/inspections', 'ops.inspections', 'ops.inspections');
    $page('/incidents/new', 'ops.incident-create', 'ops.incidents.create');
});

Route::middleware(['auth', 'role:staff'])->group(function () use ($page) {
    $page('/ops/my-shifts', 'ops.my-shifts', 'ops.my-shifts');
});

Route::middleware(['auth', 'role:admin,super_admin'])->prefix('manage')->name('manage.')->group(function () use ($page) {
    $page('/users', 'manage.users', 'users');
    $page('/users/{id}', 'manage.user-show', 'users.show')->whereNumber('id');
    $page('/zones', 'manage.zones', 'zones');
    $page('/zones/{id}', 'manage.zone-show', 'zones.show')->whereNumber('id');
    $page('/vehicle-categories', 'manage.categories', 'categories');
    $page('/packages', 'manage.packages', 'packages');
    $page('/packages/{id}', 'manage.package-show', 'packages.show')->whereNumber('id');
    $page('/price-calculator', 'manage.pricing', 'pricing');
    $page('/penalty-types', 'manage.penalties', 'penalties');
    $page('/payment-methods', 'manage.payment-methods', 'payment-methods');
    $page('/shifts', 'manage.shifts', 'shifts');
    $page('/cameras', 'manage.cameras', 'cameras');
    $page('/cameras/{id}', 'manage.camera-show', 'cameras.show')->whereNumber('id');
    $page('/refunds', 'manage.refunds', 'refunds');
    $page('/incidents', 'manage.incidents', 'incidents');
    $page('/complaints', 'manage.complaints', 'complaints');
    $page('/settings', 'manage.settings', 'settings');
    $page('/system', 'manage.system', 'system');
});

Route::middleware(['auth', 'role:admin,super_admin,auditor'])->group(function () {
    Route::get('/manage/roles', RoleMatrixController::class)->name('manage.roles');
});

Route::middleware(['auth', 'role:admin,super_admin,auditor'])->prefix('reports')->name('reports.')->group(function () use ($page) {
    $page('/', 'reports.overview', 'overview');
    $page('/financial', 'reports.financial', 'financial');
    $page('/payments', 'reports.payments', 'payments');
    $page('/sessions', 'reports.sessions', 'sessions');
    $page('/calendar', 'reports.calendar', 'calendar');
    $page('/occupancy', 'reports.occupancy', 'occupancy');
    $page('/inspections', 'reports.inspections', 'inspections');
    $page('/staff', 'reports.staff', 'staff');
    $page('/anpr', 'reports.anpr', 'anpr');
});

Route::middleware(['auth', 'role:super_admin,auditor'])->prefix('audit')->name('audit.')->group(function () use ($page) {
    $page('/logs', 'audit.logs', 'logs');
    $page('/findings', 'audit.findings', 'findings');
    $page('/anpr-corrections', 'audit.corrections', 'corrections');
    $page('/recognition-history', 'audit.anpr-events', 'anpr-events');
});

Route::middleware(['auth', 'role:customer'])->prefix('my')->name('my.')->group(function () use ($page, $uuid) {
    $page('/parking', 'my.active', 'active');
    $page('/vehicles', 'my.vehicles', 'vehicles');
    Route::get('/vehicles/{vehicle}', function (Vehicle $vehicle) {
        abort_unless(request()->user()->can('view', $vehicle), 403);

        return view('pages.my.vehicle-show', ['id' => $vehicle->id]);
    })->name('vehicles.show')->whereNumber('vehicle');
    $page('/parking-info', 'my.parking-info', 'parking-info');
    $page('/reservations', 'my.reservations', 'reservations');
    $page('/reservations/{id}', 'my.reservation-show', 'reservations.show')->whereNumber('id');
    $page('/history', 'my.history', 'history');
    $page('/history/{uuid}', 'my.session-show', 'history.show')->where('uuid', $uuid);
    $page('/receipts', 'my.receipts', 'receipts');
    $page('/receipts/{number}', 'my.receipt-show', 'receipts.show')->where('number', '[A-Za-z0-9-]{4,40}');
    $page('/credits', 'my.credits', 'credits');
    $page('/support', 'my.support', 'support');
    $page('/support/{id}', 'my.support-show', 'support.show')->whereNumber('id');
});
