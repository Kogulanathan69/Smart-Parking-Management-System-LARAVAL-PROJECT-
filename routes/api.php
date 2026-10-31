<?php

use App\Http\Controllers\Api\Admin\OperationsController;
use App\Http\Controllers\Api\Admin\PackageController;
use App\Http\Controllers\Api\Admin\SettingController;
use App\Http\Controllers\Api\Admin\ShiftController;
use App\Http\Controllers\Api\Admin\UserController;
use App\Http\Controllers\Api\Admin\ZoneController;
use App\Http\Controllers\Api\Customer\ComplaintController;
use App\Http\Controllers\Api\Customer\NotificationController;
use App\Http\Controllers\Api\Customer\ParkingHistoryController;
use App\Http\Controllers\Api\Customer\VehicleController;
use App\Http\Controllers\Api\MeController;
use App\Http\Controllers\Api\Report\AuditController;
use App\Http\Controllers\Api\Report\ReportController;
use App\Http\Controllers\Api\Staff\AttendanceController;
use App\Http\Controllers\Api\Staff\EntryController;
use App\Http\Controllers\Api\Staff\ExitController;
use App\Http\Controllers\Api\Staff\SafetyController;
use App\Http\Controllers\Api\Staff\SessionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| JSON API v1  (prefix: /api/v1)
|--------------------------------------------------------------------------
| Session-cookie authenticated (same-origin Blade/JS frontend): the "web" group
| provides session + CSRF protection. Send the X-XSRF-TOKEN header from JS.
*/

Route::middleware(['web', 'auth', 'active', 'throttle:api'])->name('api.')->group(function () {

    Route::get('me', [MeController::class, 'show'])->name('me');
    Route::get('reference-data', [MeController::class, 'referenceData'])->name('reference-data');

    // Notifications: every role, own notifications only.
    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::post('notifications/{id}/read', [NotificationController::class, 'markRead'])->name('notifications.read');

    // Evidence download: policy decides (internal roles or owning customer).
    Route::get('evidence/{evidence}', [SafetyController::class, 'showEvidence'])->name('evidence.show')->withoutScopedBindings();

    /* ---------------- Customer ---------------- */
    Route::middleware('role:customer')->prefix('customer')->name('customer.')->group(function () {
        Route::apiResource('vehicles', VehicleController::class);
        Route::get('sessions', [ParkingHistoryController::class, 'sessions'])->name('sessions.index');
        Route::get('sessions/{session}', [ParkingHistoryController::class, 'showSession'])->name('sessions.show');
        Route::get('receipts', [ParkingHistoryController::class, 'receipts'])->name('receipts.index');
        Route::get('receipts/{receipt:number}', [ParkingHistoryController::class, 'showReceipt'])->name('receipts.show');
        Route::get('credits', [ParkingHistoryController::class, 'credits'])->name('credits');
        Route::get('complaints', [ComplaintController::class, 'index'])->name('complaints.index');
        Route::post('complaints', [ComplaintController::class, 'store'])->name('complaints.store');
        Route::get('complaints/{complaint}', [ComplaintController::class, 'show'])->name('complaints.show');
    });

    /* ---------------- Staff operations (Admin & Super Admin may also operate) ---------------- */
    Route::middleware('role:staff,admin,super_admin')->prefix('staff')->name('staff.')->group(function () {
        Route::get('vehicles/lookup', [EntryController::class, 'lookupVehicle'])->name('vehicles.lookup');
        Route::get('availability', [EntryController::class, 'availability'])->name('availability');
        Route::post('entries', [EntryController::class, 'store'])->middleware('throttle:gate')->name('entries.store');

        Route::get('sessions', [SessionController::class, 'index'])->name('sessions.index');
        Route::get('sessions/{session}', [SessionController::class, 'show'])->name('sessions.show');
        Route::get('sessions/{session}/ticket', [SessionController::class, 'ticket'])->name('sessions.ticket');
        Route::post('sessions/{session}/ticket/reissue', [SessionController::class, 'reissueTicket'])->name('sessions.ticket.reissue');
        Route::post('sessions/{session}/payments', [SessionController::class, 'prepay'])->name('sessions.prepay');
        Route::post('sessions/{session}/confirm-left', [SessionController::class, 'confirmLeft'])->name('sessions.confirm-left');

        Route::post('exits/preview', [ExitController::class, 'preview'])->middleware('throttle:gate')->name('exits.preview');
        Route::post('exits/settle', [ExitController::class, 'settle'])->middleware('throttle:gate')->name('exits.settle');
        Route::post('payments/{payment}/confirm', [ExitController::class, 'confirmPayment'])->name('payments.confirm');
        Route::post('payments/{payment}/fail', [ExitController::class, 'failPayment'])->name('payments.fail');

        Route::get('inspections/due', [SafetyController::class, 'due'])->name('inspections.due');
        Route::get('sessions/{session}/inspections', [SafetyController::class, 'history'])->name('inspections.history');
        Route::post('sessions/{session}/inspections', [SafetyController::class, 'storeInspection'])->name('inspections.store');
        Route::post('incidents', [SafetyController::class, 'storeIncident'])->name('incidents.store');
        Route::post('evidence', [SafetyController::class, 'storeEvidence'])->name('evidence.store');
    });

    Route::middleware('role:staff')->prefix('staff')->name('staff.')->group(function () {
        Route::get('shifts/mine', [AttendanceController::class, 'myShifts'])->name('shifts.mine');
        Route::post('shifts/{assignment}/check-in', [AttendanceController::class, 'checkIn'])->name('shifts.check-in');
        Route::post('shifts/{assignment}/check-out', [AttendanceController::class, 'checkOut'])->name('shifts.check-out');
    });

    /* ---------------- Admin & Super Admin management ---------------- */
    Route::middleware('role:admin,super_admin')->prefix('admin')->name('admin.')->group(function () {
        Route::apiResource('zones', ZoneController::class)->except('destroy');
        Route::put('zones/{zone}/capacity', [ZoneController::class, 'setCapacity'])->name('zones.capacity');
        Route::get('zones/{zone}/slots', [ZoneController::class, 'slots'])->name('zones.slots');
        Route::patch('slots/{slot}/status', [ZoneController::class, 'updateSlotStatus'])->name('slots.status');

        Route::apiResource('packages', PackageController::class)->except('destroy');
        Route::get('packages/{package}/rates', [PackageController::class, 'rates'])->name('packages.rates.index');
        Route::post('packages/{package}/rates', [PackageController::class, 'storeRate'])->name('packages.rates.store');
        Route::post('pricing/preview', [PackageController::class, 'preview'])->name('pricing.preview');

        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::post('users', [UserController::class, 'store'])->name('users.store');
        Route::get('users/{user}', [UserController::class, 'show'])->name('users.show');
        Route::patch('users/{user}/status', [UserController::class, 'updateStatus'])->name('users.status');
        Route::patch('users/{user}/role', [UserController::class, 'updateRole'])->name('users.role');

        Route::get('shifts', [ShiftController::class, 'index'])->name('shifts.index');
        Route::post('shifts', [ShiftController::class, 'store'])->name('shifts.store');
        Route::put('shifts/{shift}', [ShiftController::class, 'update'])->name('shifts.update');
        Route::get('shift-assignments', [ShiftController::class, 'assignments'])->name('shift-assignments.index');
        Route::post('shift-assignments', [ShiftController::class, 'assign'])->name('shift-assignments.store');
        Route::post('shift-assignments/{assignment}/leave', [ShiftController::class, 'approveLeave'])->name('shift-assignments.leave');
        Route::get('shift-coverage', [ShiftController::class, 'coverage'])->name('shift-coverage');
        Route::get('attendance', [ShiftController::class, 'attendance'])->name('attendance.index');

        Route::get('incidents', [OperationsController::class, 'incidents'])->name('incidents.index');
        Route::patch('incidents/{incident}', [OperationsController::class, 'updateIncident'])->name('incidents.update');
        Route::get('complaints', [OperationsController::class, 'complaints'])->name('complaints.index');
        Route::patch('complaints/{complaint}', [OperationsController::class, 'updateComplaint'])->name('complaints.update');
        Route::get('refunds', [OperationsController::class, 'refunds'])->name('refunds.index');
        Route::post('refunds/{refund}/decision', [OperationsController::class, 'decideRefund'])->name('refunds.decision');

        Route::get('settings', [SettingController::class, 'index'])->name('settings.index');
        Route::get('settings/history', [SettingController::class, 'history'])->name('settings.history');
        Route::put('settings/{key}', [SettingController::class, 'update'])->where('key', '[a-z_]+\.[a-z_]+')->name('settings.update');
        Route::get('payment-methods', [SettingController::class, 'paymentMethods'])->name('payment-methods.index');
        Route::post('payment-methods', [SettingController::class, 'upsertPaymentMethod'])->name('payment-methods.upsert');
    });

    /* ---------------- Read-only reports: Admin, Super Admin, Auditor ---------------- */
    Route::middleware('role:admin,super_admin,auditor')->prefix('reports')->name('reports.')->group(function () {
        Route::get('overview', [ReportController::class, 'overview'])->name('overview');
        Route::get('occupancy', [ReportController::class, 'occupancy'])->name('occupancy');
        Route::get('financial', [ReportController::class, 'financial'])->name('financial');
        Route::get('staff-performance', [ReportController::class, 'staffPerformance'])->name('staff-performance');
        Route::get('sessions', [ReportController::class, 'sessions'])->name('sessions');
        Route::get('payments', [ReportController::class, 'payments'])->name('payments');
        Route::get('inspections', [ReportController::class, 'inspections'])->name('inspections');
    });

    /* ---------------- Audit: Super Admin & Auditor ---------------- */
    Route::middleware('role:super_admin,auditor')->prefix('audit')->name('audit.')->group(function () {
        Route::get('logs', [AuditController::class, 'logs'])->name('logs');
        Route::get('findings', [AuditController::class, 'findings'])->name('findings.index');
    });
    Route::middleware('role:auditor')->post('audit/findings', [AuditController::class, 'storeFinding'])->name('audit.findings.store');
});
