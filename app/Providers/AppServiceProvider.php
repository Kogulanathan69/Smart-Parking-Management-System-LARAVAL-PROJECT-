<?php

namespace App\Providers;

use App\Contracts\AnprEngine;
use App\Contracts\GateController;
use App\Contracts\PaymentGateway;
use App\Contracts\PlateRecognizer;
use App\Contracts\TicketPrinter;
use App\Models\NotificationLog;
use App\Services\Integrations\BrowserTicketPrinter;
use App\Services\Integrations\DisabledAnprEngine;
use App\Services\Integrations\FakeAnprEngine;
use App\Services\Integrations\HttpAnprEngine;
use App\Services\Integrations\LogGateController;
use App\Services\Integrations\ManualPlateRecognizer;
use App\Services\Integrations\NullPaymentGateway;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Hardware & external integrations are bound to interfaces so real drivers
     * (camera OCR, thermal printer, gate relay, payment gateway) can be added here later
     * and selected with the PARKING_*_DRIVER environment variables.
     *
     * @var array<class-string, array{0: string, 1: array<string, class-string>}>
     */
    private const DRIVERS = [
        PlateRecognizer::class => ['plate_recognizer', ['manual' => ManualPlateRecognizer::class]],
        TicketPrinter::class => ['ticket_printer', ['browser' => BrowserTicketPrinter::class]],
        GateController::class => ['gate_controller', ['log' => LogGateController::class]],
        PaymentGateway::class => ['payment_gateway', ['none' => NullPaymentGateway::class]],
        AnprEngine::class => ['anpr_engine', [
            'disabled' => DisabledAnprEngine::class,
            'http_local' => HttpAnprEngine::class,
            'fake' => FakeAnprEngine::class,
        ]],
    ];

    public function register(): void
    {
        foreach (self::DRIVERS as $contract => [$key, $drivers]) {
            $this->app->bind($contract, function ($app) use ($key, $drivers) {
                $driver = config("parking.drivers.$key");
                if ($driver === 'fake' && $app->isProduction()) {
                    throw new \InvalidArgumentException("The fake [$key] driver cannot be used in production.");
                }
                if (! isset($drivers[$driver])) {
                    throw new \InvalidArgumentException("Unknown parking driver [$driver] for [$key].");
                }

                return $app->make($drivers[$driver]);
            });
        }
    }

    public function boot(): void
    {
        Password::defaults(fn () => Password::min(8)->letters()->numbers());

        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)->by($request->user()?->id ?: $request->ip()));
        // Device (camera / ANPR agent) traffic is limited per camera code, separately from human users.
        RateLimiter::for('anpr-device', fn (Request $request) => Limit::perMinute((int) config('parking.anpr.ingest_rate_per_minute'))
            ->by('anpr:'.substr((string) $request->header('X-Anpr-Camera'), 0, 40).'|'.$request->ip()));
        RateLimiter::for('gate', fn (Request $request) => Limit::perMinute(60)->by($request->user()?->id ?: $request->ip()));
        RateLimiter::for('public-ticket', fn (Request $request) => Limit::perMinute(30)->by($request->ip()));

        Event::listen(NotificationSent::class, function (NotificationSent $event) {
            NotificationLog::create([
                'user_id' => $event->notifiable->id ?? null,
                'notification_type' => $event->notification::class,
                'channel' => $event->channel,
                'status' => 'sent',
            ]);
        });
        Event::listen(NotificationFailed::class, function (NotificationFailed $event) {
            NotificationLog::create([
                'user_id' => $event->notifiable->id ?? null,
                'notification_type' => $event->notification::class,
                'channel' => $event->channel,
                'status' => 'failed',
                'error' => substr(json_encode($event->data), 0, 255),
            ]);
        });
    }
}
