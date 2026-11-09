<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShiftAssignmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'shift' => $this->whenLoaded('shift', fn () => $this->shift->only(['id', 'code', 'name', 'start_time', 'end_time'])),
            'user' => $this->whenLoaded('user', fn () => ['id' => $this->user->id, 'name' => $this->user->name]),
            'shift_date' => $this->shift_date?->format('Y-m-d'),
            'is_gate_duty' => $this->is_gate_duty,
            'status' => $this->status,
            'replacement_for_id' => $this->replacement_for_id,
            'attendance' => $this->whenLoaded('attendance', fn () => $this->attendance ? new AttendanceResource($this->attendance) : null),
        ];
    }
}
