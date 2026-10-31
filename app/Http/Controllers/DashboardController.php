<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Models\CustomerCredit;
use App\Models\ParkingSession;
use App\Models\SafetyInspection;
use App\Models\ShiftAssignment;
use App\Models\User;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Role dashboards. Each returns JSON summary data for the Blade/JS frontend.
 */
class DashboardController extends Controller
{
    public function __construct(private ReportService $reports) {}

    public function redirect(Request $request): RedirectResponse
    {
        return redirect($request->user()->dashboardPath());
    }

    public function superAdmin(Request $request): JsonResponse
    {
        return $this->respond($request, 'Super Admin Dashboard', User::ROLE_SUPER_ADMIN, $this->reports->overview() + [
            'financial_7_days' => $this->reports->financial(null, null),
        ]);
    }

    public function admin(Request $request): JsonResponse
    {
        return $this->respond($request, 'Admin Dashboard', User::ROLE_ADMIN, $this->reports->overview());
    }

    public function staff(Request $request): JsonResponse
    {
        $today = now(config('parking.business_timezone'))->toDateString();

        return $this->respond($request, 'Staff Dashboard', User::ROLE_STAFF, [
            'occupancy' => $this->reports->occupancy(),
            'active_sessions' => ParkingSession::where('status', ParkingSession::ACTIVE)->count(),
            'due_inspections' => SafetyInspection::where('status', SafetyInspection::SCHEDULED)->where('scheduled_for', '<=', now()->addHour())->count(),
            'my_shifts_today' => ShiftAssignment::with('shift')->where('user_id', $request->user()->id)->whereDate('shift_date', $today)->get()
                ->map(fn ($a) => ['id' => $a->id, 'shift' => $a->shift->code, 'status' => $a->status]),
        ]);
    }

    public function auditor(Request $request): JsonResponse
    {
        return $this->respond($request, 'Auditor Dashboard', User::ROLE_AUDITOR, [
            'financial_7_days' => $this->reports->financial(null, null),
        ]);
    }

    public function customer(Request $request): JsonResponse
    {
        $user = $request->user();

        return $this->respond($request, 'Customer Dashboard', User::ROLE_CUSTOMER, [
            'vehicles' => $user->vehicles()->count(),
            'active_sessions' => $user->parkingSessions()->whereIn('status', [ParkingSession::ACTIVE, ParkingSession::EXIT_AUTHORIZED])->count(),
            'unread_notifications' => $user->unreadNotifications()->count(),
            'credit_balance_minor' => CustomerCredit::balanceFor($user->id),
        ]);
    }

    private function respond(Request $request, string $message, string $role, array $data): JsonResponse
    {
        return response()->json([
            'message' => $message,
            'role' => $role,
            'user' => new UserResource($request->user()),
            'data' => $data,
        ]);
    }
}
