<?php

namespace App\Http\Controllers\Api\Staff;

use App\Http\Controllers\Controller;
use App\Http\Resources\AttendanceResource;
use App\Http\Resources\ShiftAssignmentResource;
use App\Models\ShiftAssignment;
use App\Services\ShiftService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AttendanceController extends Controller
{
    public function myShifts(Request $request): AnonymousResourceCollection
    {
        $from = $request->query('from', now(config('parking.business_timezone'))->subDays(1)->toDateString());

        return ShiftAssignmentResource::collection(
            $request->user()->shiftAssignments()->with(['shift', 'attendance'])->whereDate('shift_date', '>=', $from)->orderBy('shift_date')->limit(60)->get()
        );
    }

    public function checkIn(Request $request, ShiftAssignment $assignment, ShiftService $shifts): AttendanceResource
    {
        return new AttendanceResource($shifts->checkIn($request->user(), $assignment->load('shift')));
    }

    public function checkOut(Request $request, ShiftAssignment $assignment, ShiftService $shifts): AttendanceResource
    {
        return new AttendanceResource($shifts->checkOut($request->user(), $assignment->load('shift')));
    }
}
