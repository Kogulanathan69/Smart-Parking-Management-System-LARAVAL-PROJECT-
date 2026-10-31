<?php

use App\Http\Controllers\DashboardController;
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
