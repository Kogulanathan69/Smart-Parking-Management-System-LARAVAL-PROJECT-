<?php

namespace App\Actions\Fortify;

use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * Public registration ALWAYS creates an active customer. Any "role" or "status"
     * submitted with the request is ignored.
     *
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique(User::class),
            ],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^\+?[0-9 \-]{7,20}$/'],
            'password' => $this->passwordRules(),
        ])->validate();

        $user = new User;
        $user->forceFill([
            'name' => $input['name'],
            'email' => strtolower($input['email']),
            'phone' => $input['phone'] ?? null,
            'password' => Hash::make($input['password']),
            'role' => User::ROLE_CUSTOMER,
            'status' => User::STATUS_ACTIVE,
        ])->save();

        app(AuditLogger::class)->log('user.registered', $user, [], ['role' => User::ROLE_CUSTOMER]);

        return $user;
    }
}
