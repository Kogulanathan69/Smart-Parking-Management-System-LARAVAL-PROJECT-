<?php

namespace App\Services\Integrations;

use App\Contracts\AnprEngine;
use App\Services\SettingsService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Client for the local Python ANPR service (anpr-service/). Loopback/LAN only, bearer-token protected.
 * Errors are mapped to safe codes; images and tokens are never logged.
 */
class HttpAnprEngine implements AnprEngine
{
    public function __construct(private SettingsService $settings) {}

    public function name(): string
    {
        return 'http_local';
    }

    public function recognize(string $imageBytes, string $mimeType): array
    {
        $started = hrtime(true);
        $attempts = 1 + max(0, (int) $this->settings->get('anpr.camera_retry_attempts'));

        for ($i = 1; $i <= $attempts; $i++) {
            try {
                $response = $this->client()
                    ->attach('image', $imageBytes, 'frame.'.($mimeType === 'image/png' ? 'png' : 'jpg'), ['Content-Type' => $mimeType])
                    ->post('/v1/recognize');

                if ($response->successful()) {
                    $body = $response->json();
                    $status = in_array($body['status'] ?? null, ['recognized', 'unreadable'], true) ? $body['status'] : 'engine_error';

                    return [
                        'status' => $status,
                        'review_suggestions' => array_values(array_filter((array) ($body['review_suggestions'] ?? []), 'is_array')),
                        'partial_review_clues' => AnprPartialClueValidator::normalize($body['partial_review_clues'] ?? null),
                        'candidates' => collect($body['candidates'] ?? [])->map(fn ($c) => [
                            'text' => (string) ($c['text'] ?? ''),
                            // The service reports 0..1; convert to integer basis points.
                            'confidence_bp' => (int) round(max(0, min(1, (float) ($c['confidence'] ?? 0))) * 10000),
                            // Detector score is informational only (shown to staff next to the OCR confidence).
                            'detector_confidence_bp' => isset($c['detector_confidence']) ? (int) round(max(0, min(1, (float) $c['detector_confidence'])) * 10000) : null,
                        ])->filter(fn ($c) => $c['text'] !== '')->values()->all(),
                        'plate_crop' => isset($body['plate_crop_jpeg_base64']) ? base64_decode($body['plate_crop_jpeg_base64'], true) ?: null : null,
                        'processing_ms' => (int) ($body['processing_ms'] ?? $this->elapsed($started)),
                        'error_code' => $status === 'engine_error' ? 'anpr_engine_bad_response' : null,
                    ];
                }
                Log::warning('ANPR service returned an error', ['status' => $response->status(), 'attempt' => $i]);
            } catch (ConnectionException) {
                Log::warning('ANPR service unreachable or timed out', ['attempt' => $i]);
            }
        }

        return ['status' => 'engine_error', 'candidates' => [], 'plate_crop' => null, 'processing_ms' => $this->elapsed($started), 'error_code' => 'anpr_engine_unavailable'];
    }

    public function health(): array
    {
        $started = hrtime(true);
        try {
            $response = $this->client()->timeout(3)->get('/v1/health');

            return ['available' => $response->successful() && ($response->json('status') === 'ok'), 'latency_ms' => $this->elapsed($started),
                'detail' => array_intersect_key((array) $response->json(), array_flip(['status', 'detector', 'ocr', 'version']))];
        } catch (ConnectionException) {
            return ['available' => false, 'latency_ms' => null];
        }
    }

    private function client()
    {
        return Http::baseUrl(rtrim((string) config('parking.anpr.service_url'), '/'))
            ->withToken((string) config('parking.anpr.service_token'))
            ->acceptJson()
            ->connectTimeout(2)
            ->timeout(max(1, intdiv((int) $this->settings->get('anpr.recognition_timeout_ms'), 1000)));
    }

    private function elapsed(int $started): int
    {
        return (int) ((hrtime(true) - $started) / 1_000_000);
    }
}
