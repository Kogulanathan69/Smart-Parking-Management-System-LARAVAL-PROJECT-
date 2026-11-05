<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Http\Responses\RoleBasedLoginResponse;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\RedirectIfTwoFactorAuthenticatable;
use Laravel\Fortify\Contracts\LoginResponse;
use Laravel\Fortify\Contracts\RegisterResponse;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(LoginResponse::class, RoleBasedLoginResponse::class);
        $this->app->singleton(RegisterResponse::class, RoleBasedLoginResponse::class);
        $this->app->singleton(TwoFactorLoginResponse::class, RoleBasedLoginResponse::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::redirectUserForTwoFactorAuthenticationUsing(RedirectIfTwoFactorAuthenticatable::class);

        /*
         * Only ACTIVE accounts may log in. Inactive/suspended users get the same generic
         * message after a correct password so account state is not leaked to guessers.
         */
        Fortify::authenticateUsing(function (Request $request) {
            $user = User::where('email', Str::lower((string) $request->input(Fortify::username())))->first();

            if (! $user || ! Hash::check((string) $request->input('password'), $user->password)) {
                return null;
            }

            if (! $user->isActive()) {
                app(AuditLogger::class)->log('auth.login_blocked', $user, [], [], ['status' => $user->status]);

                throw ValidationException::withMessages([
                    Fortify::username() => __('This account is not active. Please contact the parking office.'),
                ]);
            }

            $user->forceFill(['last_login_at' => now()])->saveQuietly();

            return $user;
        });

        $this->registerViews();

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        RateLimiter::for('passkeys', function (Request $request) {
            $credentialId = $request->input('credential.id');

            return Limit::perMinute(10)->by(
                ($credentialId ?: $request->session()->getId()).'|'.$request->ip()
            );
        });
    }

    /**
     * Blade views are built by the frontend team (resources/views/auth/*). Until a view exists,
     * the route returns a 501 JSON placeholder instead of a server error.
     */
    private function registerViews(): void
    {
        $view = fn (string $name) => function (Request $request) use ($name) {
            return view()->exists($name)
                ? view($name, ['request' => $request])
                : response()->json(['message' => "View [$name] has not been implemented by the frontend yet."], 501);
        };

        Fortify::loginView($view('auth.login'));
        Fortify::registerView($view('auth.register'));
        Fortify::requestPasswordResetLinkView($view('auth.forgot-password'));
        Fortify::resetPasswordView($view('auth.reset-password'));
        Fortify::confirmPasswordView($view('auth.confirm-password'));
        Fortify::twoFactorChallengeView($view('auth.two-factor-challenge'));
        Fortify::verifyEmailView($view('auth.verify-email'));
    }
}
