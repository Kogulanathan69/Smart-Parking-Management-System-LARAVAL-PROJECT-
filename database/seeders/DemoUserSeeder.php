<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * LOCAL DEVELOPMENT ONLY. Creates one account per non-super-admin role.
 * Password comes from SEED_DEMO_PASSWORD or is randomly generated and printed once.
 * Super Admins are never seeded: use `php artisan parking:create-super-admin`.
 */
class DemoUserSeeder extends Seeder
{
    public function run(): void
    {
        $password = env('SEED_DEMO_PASSWORD') ?: Str::password(16);

        foreach ([User::ROLE_ADMIN, User::ROLE_STAFF, User::ROLE_AUDITOR, User::ROLE_CUSTOMER] as $role) {
            $email = "{$role}@smart-parking.test";
            if (User::where('email', $email)->exists()) {
                continue;
            }
            $user = new User;
            $user->forceFill([
                'name' => 'Demo '.Str::headline($role),
                'email' => $email,
                'password' => Hash::make($password),
                'role' => $role,
                'status' => User::STATUS_ACTIVE,
                'email_verified_at' => now(),
            ])->save();
        }

        if (! env('SEED_DEMO_PASSWORD')) {
            $this->command?->warn("Demo accounts (*@smart-parking.test) password: {$password}");
        }
    }
}
