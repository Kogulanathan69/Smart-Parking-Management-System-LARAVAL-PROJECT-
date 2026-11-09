<?php

namespace App\Console\Commands;

use App\Exceptions\ParkingException;
use App\Models\User;
use App\Services\UserAccountService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

/**
 * Secure Super Admin provisioning (BRD §3). The password is typed interactively and
 * never passed as a CLI argument (which would end up in shell history).
 */
class CreateSuperAdmin extends Command
{
    protected $signature = 'parking:create-super-admin {--name= : Full name} {--email= : Login email}';

    protected $description = 'Create a Super Admin account (limited by config parking.account_limits). Password is prompted, never passed as an argument.';

    public function handle(UserAccountService $accounts): int
    {
        $name = $this->option('name') ?: text('Full name', required: true);
        $email = $this->option('email') ?: text('Email', required: true);
        $secret = password('Password (min 12 chars, letters, numbers, symbols)', required: true);
        $confirm = password('Confirm password', required: true);

        $validator = Validator::make(
            ['name' => $name, 'email' => $email, 'password' => $secret, 'password_confirmation' => $confirm],
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255', 'unique:users,email'],
                'password' => ['required', 'confirmed', Password::min(12)->letters()->mixedCase()->numbers()->symbols()],
            ]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        try {
            $user = $accounts->provisionSuperAdmin($name, $email, $secret);
        } catch (ParkingException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $active = User::where('role', User::ROLE_SUPER_ADMIN)->where('status', User::STATUS_ACTIVE)->count();
        $this->info("Super Admin {$user->email} created ({$active}/".config('parking.account_limits.super_admin').' active).');

        return self::SUCCESS;
    }
}
