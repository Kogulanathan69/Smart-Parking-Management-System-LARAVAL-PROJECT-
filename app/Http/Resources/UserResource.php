<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'role' => $this->role,
            'status' => $this->status,
            'status_reason' => $this->when($request->user()?->isInternal(), $this->status_reason),
            'email_verified' => (bool) $this->email_verified_at,
            'two_factor_enabled' => (bool) $this->two_factor_confirmed_at,
            'dashboard' => $this->dashboardPath(),
            'last_login_at' => $this->last_login_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
