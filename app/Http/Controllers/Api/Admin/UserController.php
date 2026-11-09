<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRoleRequest;
use App\Http\Requests\UpdateUserStatusRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\UserAccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UserController extends Controller
{
    public function __construct(private UserAccountService $accounts) {}

    /**
     * Lists only the roles the current user may see (Admin: staff + customers; Super Admin: everyone).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $actor = $request->user();
        $visibleRoles = $actor->hasRole(User::ROLE_SUPER_ADMIN) ? User::ROLES : UserAccountService::MANAGEABLE_ROLES[$actor->role];

        $users = User::query()
            ->whereIn('role', $visibleRoles)
            ->when($request->query('role'), fn ($q, $r) => $q->where('role', $r))
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->query('q'), fn ($q, $t) => $q->where(fn ($w) => $w->where('name', 'like', "%$t%")->orWhere('email', 'like', "%$t%")->orWhere('phone', 'like', "%$t%")))
            ->orderBy('name')
            ->paginate(25);

        return UserResource::collection($users);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = $this->accounts->createInternal($request->user(), $request->validated());

        return (new UserResource($user))->response()->setStatusCode(201);
    }

    public function show(User $user): UserResource
    {
        $this->authorize('view', $user);

        return new UserResource($user);
    }

    public function updateStatus(UpdateUserStatusRequest $request, User $user): UserResource
    {
        return new UserResource($this->accounts->changeStatus($request->user(), $user, $request->validated('status'), $request->validated('reason')));
    }

    public function updateRole(UpdateUserRoleRequest $request, User $user): UserResource
    {
        return new UserResource($this->accounts->changeRole($request->user(), $user, $request->validated('role')));
    }
}
