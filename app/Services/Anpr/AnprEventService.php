<?php

namespace App\Services\Anpr;

use App\Contracts\AnprEngine;
use App\Exceptions\ParkingException;
use App\Models\AnprCamera;
use App\Models\AnprEvent;
use App\Models\AnprEventImage;
use App\Models\AnprReviewAction;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\AuditLogger;
use App\Services\SettingsService;
use App\Support\Anpr\SriLankaPlateNormalizer;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Records ANPR events. An event is evidence + a *suggestion*; it never authorises entry, exit or a gate.
 */
class AnprEventService
{
    public function __construct(
        private SriLankaPlateNormalizer $normalizer,
        private SettingsService $settings,
        private AnprCameraService $cameras,
        private AnprEngine $engine,
        private AuditLogger $audit,
    ) {}

    /**
     * Event pushed by an authenticated on-site camera agent (which ran recognition itself).
     *
     * @param  array{idempotency_key: string, direction?: ?string, captured_at?: ?string, status: string, candidates?: list<array{text: string, confidence: float|int|string}>, processing_ms?: ?int, error_code?: ?string}  $data
     * @return array{0: AnprEvent, 1: bool} event, whether it was a replay of an earlier request
     */
    public function ingestFromDevice(AnprCamera $camera, array $data, ?UploadedFile $frame = null, ?UploadedFile $crop = null): array
    {
        if (! $camera->is_enabled) {
            throw new ParkingException('This camera is disabled.', 'camera_disabled', 403);
        }
        $direction = $this->resolveDirection($camera, $data['direction'] ?? null);
        $candidates = collect($data['candidates'] ?? [])->map(fn ($c) => [
            'text' => (string) $c['text'],
            'confidence_bp' => (int) round(max(0, min(1, (float) $c['confidence'])) * 10000),
            'detector_confidence_bp' => isset($c['detector_confidence']) ? (int) round(max(0, min(1, (float) $c['detector_confidence'])) * 10000) : null,
        ])->all();

        return $this->record([
            'camera' => $camera,
            'direction' => $direction,
            'source' => 'camera',
            'captured_at' => isset($data['captured_at']) ? CarbonImmutable::parse($data['captured_at']) : now(),
            'engine_status' => $data['status'],
            'candidates' => $candidates,
            'review_suggestions' => $data['review_suggestions'] ?? [],
            'partial_review_clues' => $data['partial_review_clues'] ?? null,
            'processing_ms' => $data['processing_ms'] ?? null,
            'error_code' => $data['error_code'] ?? null,
            'idempotency_key' => $data['idempotency_key'],
            'frame' => $frame ? [$frame->get(), $frame->getMimeType()] : null,
            'crop' => $crop ? [$crop->get(), $crop->getMimeType()] : null,
        ]);
    }

    /**
     * Staff-submitted image recognised by the local engine (e.g. USB camera at the booth, or re-capture).
     */
    public function recognizeUpload(User $by, ?AnprCamera $camera, string $direction, UploadedFile $image, ?string $idempotencyKey = null): AnprEvent
    {
        if ($camera && ! $camera->is_enabled) {
            throw new ParkingException('This camera is disabled.', 'camera_disabled', 403);
        }
        $direction = $camera ? $this->resolveDirection($camera, $direction) : $direction;
        $bytes = $image->get();
        $result = $this->engine->recognize($bytes, $image->getMimeType());

        if ($result['status'] === 'engine_error') {
            $this->cameras->logHealth($camera, 'engine', 'error', $result['error_code'] ?? 'engine_error', $result['processing_ms'] ?? null);
        }

        [$event] = $this->record([
            'camera' => $camera,
            'direction' => $direction,
            'source' => 'upload',
            'captured_at' => now(),
            'engine_status' => $result['status'],
            'candidates' => $result['candidates'] ?? [],
            'review_suggestions' => $result['review_suggestions'] ?? [],
            'partial_review_clues' => $result['partial_review_clues'] ?? null,
            'processing_ms' => $result['processing_ms'] ?? null,
            'error_code' => $result['error_code'] ?? null,
            'idempotency_key' => $idempotencyKey,
            'frame' => [$bytes, $image->getMimeType()],
            'crop' => ! empty($result['plate_crop']) ? [$result['plate_crop'], 'image/jpeg'] : null,
            'created_by' => $by->id,
        ]);

        return $event;
    }

    /**
     * Manual identification (camera offline, unreadable/damaged/no plate, wrong result...). Mandatory reason.
     */
    public function createManual(User $by, array $data): AnprEvent
    {
        $this->assertOverrideAllowed($by);
        $camera = isset($data['anpr_camera_id']) ? AnprCamera::find($data['anpr_camera_id']) : null;
        $direction = $camera ? $this->resolveDirection($camera, $data['direction']) : $data['direction'];
        $plate = $data['plate_number'] ?? null;
        if (! $plate && $data['reason'] !== 'no_plate') {
            throw new ParkingException('A plate number is required unless the reason is no_plate.', 'plate_required');
        }
        $n = $this->normalizer->normalize($plate);

        return DB::transaction(function () use ($by, $data, $camera, $direction, $plate, $n) {
            $original = isset($data['original_event_uuid']) ? AnprEvent::where('uuid', $data['original_event_uuid'])->lockForUpdate()->first() : null;
            if ($original && $original->consumed_at) {
                throw new ParkingException('The original event has already been used.', 'anpr_event_already_used', 409);
            }

            $event = AnprEvent::create([
                'uuid' => (string) Str::uuid(),
                'anpr_camera_id' => $camera?->id,
                'direction' => $direction,
                'source' => 'manual',
                'captured_at' => now(),
                'raw_text' => $original?->raw_text,
                'normalized_plate' => $n['normalized'],
                'plate_format' => $plate ? $n['format'] : 'no_plate',
                'candidates' => $original?->candidates,
                'confidence_bp' => null,
                'recognition_status' => AnprEvent::MANUAL,
                'verification_status' => AnprEvent::VERIFIED,
                'verified_plate' => $n['normalized'],
                'verified_by' => $by->id,
                'verified_at' => now(),
                'override_reason' => $data['reason'],
                'override_notes' => $data['notes'] ?? null,
                'idempotency_key' => $data['idempotency_key'] ?? null,
                'created_by' => $by->id,
            ]);
            $event->forceFill(['vehicle_id' => $this->exactVehicle($n['normalized'])?->id])->save();

            if ($original && ! $original->isVerified()) {
                // The superseded camera reading is rejected so it cannot be used afterwards.
                $original->forceFill(['verification_status' => AnprEvent::REJECTED, 'verified_by' => $by->id, 'verified_at' => now()])->save();
                $this->review($original, $by, 'reject', $original->normalized_plate, $n['normalized'], $data['reason'], 'Superseded by manual event '.$event->uuid);
            }
            $this->review($event, $by, 'manual_create', $original?->normalized_plate, $n['normalized'], $data['reason'], $data['notes'] ?? null);
            $this->audit->log('anpr.manual_identification', $event, [], ['plate' => $n['normalized'], 'reason' => $data['reason'], 'direction' => $direction]);

            return $event;
        });
    }

    /**
     * Staff confirm, correct or reject a reading. Corrections and rejections need a reason.
     * Never possible once the event has been used for an entry/exit.
     */
    public function verify(AnprEvent $event, User $by, string $action, ?string $plate = null, ?string $reason = null, ?string $notes = null): AnprEvent
    {
        return DB::transaction(function () use ($event, $by, $action, $plate, $reason, $notes) {
            $locked = AnprEvent::whereKey($event->id)->lockForUpdate()->firstOrFail();
            if ($locked->consumed_at) {
                throw new ParkingException('This event has already been used for an entry or exit.', 'anpr_event_already_used', 409);
            }
            if ($locked->recognition_status === AnprEvent::DUPLICATE) {
                throw new ParkingException('This is a duplicate reading; use the original event.', 'anpr_event_duplicate', 409, ['original_event' => $locked->duplicate_of_id ? AnprEvent::find($locked->duplicate_of_id)?->uuid : null]);
            }
            if ($action !== 'confirm' && ! $reason) {
                throw new ParkingException('A reason is required to correct or reject a reading.', 'reason_required');
            }
            if ($action !== 'confirm') {
                $this->assertOverrideAllowed($by);
            }

            $before = $locked->verified_plate ?? $locked->normalized_plate;
            switch ($action) {
                case 'confirm':
                    if (! $locked->normalized_plate) {
                        throw new ParkingException('There is no reading to confirm; correct it or use manual identification.', 'nothing_to_confirm');
                    }
                    $locked->forceFill(['verification_status' => AnprEvent::VERIFIED, 'verified_plate' => $locked->normalized_plate]);
                    break;
                case 'correct':
                    $n = $this->normalizer->normalize($plate);
                    if (! $n['normalized']) {
                        throw new ParkingException('The corrected plate is not readable.', 'invalid_plate');
                    }
                    $locked->forceFill(['verification_status' => AnprEvent::CORRECTED, 'verified_plate' => $n['normalized'], 'override_reason' => $reason, 'override_notes' => $notes]);
                    break;
                case 'reject':
                    $locked->forceFill(['verification_status' => AnprEvent::REJECTED, 'verified_plate' => null, 'override_reason' => $reason, 'override_notes' => $notes]);
                    break;
                default:
                    throw new ParkingException('Unknown verification action.', 'invalid_action');
            }
            $locked->forceFill(['verified_by' => $by->id, 'verified_at' => now()]);
            $locked->vehicle_id = $locked->verified_plate ? $this->exactVehicle($locked->verified_plate)?->id : null;
            $locked->save();

            $this->review($locked, $by, $action, $before, $locked->verified_plate, $reason, $notes);
            if ($action !== 'confirm') {
                $this->audit->log('anpr.reading_'.$action.'ed', $locked, ['plate' => $before], ['plate' => $locked->verified_plate, 'reason' => $reason]);
            }

            return $locked;
        });
    }

    /**
     * Exact register match only. Similar plates are returned separately as suggestions, never matched.
     *
     * @return array{vehicle: ?Vehicle, suggestions: list<array{identifier: string, reason: string}>}
     */
    public function lookup(?string $normalizedPlate): array
    {
        if (! $normalizedPlate) {
            return ['vehicle' => null, 'suggestions' => []];
        }
        $vehicle = $this->exactVehicle($normalizedPlate);
        $suggestions = [];
        if (! $vehicle) {
            // Province prefix present on one side only (e.g. "WPCAB1234" vs "CAB1234"): suggestion for staff.
            $withoutProvince = in_array(substr($normalizedPlate, 0, 2), SriLankaPlateNormalizer::PROVINCES, true) ? substr($normalizedPlate, 2) : null;
            $alternatives = array_filter(array_merge(
                [$withoutProvince],
                array_map(fn ($p) => $p.$normalizedPlate, SriLankaPlateNormalizer::PROVINCES)
            ));
            foreach (Vehicle::whereIn('identifier', $alternatives)->limit(5)->pluck('identifier') as $identifier) {
                $suggestions[] = ['identifier' => $identifier, 'reason' => 'province_prefix_differs'];
            }
        }

        return ['vehicle' => $vehicle, 'suggestions' => $suggestions];
    }

    public function requiresExplicitVerification(AnprEvent $event): bool
    {
        if ($event->isVerified()) {
            return false;
        }
        $candidate = collect($event->candidates ?? [])->first();

        return $this->settings->get('anpr.verification_mode') === 'always'
            || ! empty($event->review_suggestions)
            || ! empty($event->partial_review_clues)
            || $event->recognition_status !== AnprEvent::RECOGNIZED
            || ($candidate['confusion_corrected'] ?? false)
            || in_array($event->plate_format, ['unknown', 'unreadable', null], true);
    }

    /**
     * Delete snapshot files older than the retention period; keep the metadata row as evidence of deletion.
     */
    public function purgeImages(?CarbonInterface $now = null): int
    {
        $now ??= now();
        $cutoff = $now->copy()->subDays((int) $this->settings->get('anpr.snapshot_retention_days'));
        $count = 0;
        AnprEventImage::where('retention_status', 'stored')->where('captured_at', '<', $cutoff)->chunkById(200, function ($images) use (&$count) {
            foreach ($images as $image) {
                if ($image->path) {
                    Storage::disk($image->disk)->delete($image->path);
                }
                $image->forceFill(['path' => null, 'retention_status' => 'purged', 'purged_at' => now()])->save();
                $count++;
            }
        });
        if ($count) {
            $this->audit->log('anpr.images_purged', null, [], ['count' => $count, 'cutoff' => $cutoff->toIso8601String()]);
        }

        return $count;
    }

    public function imageResponse(AnprEventImage $image, User $by)
    {
        if ($image->retention_status !== 'stored' || ! $image->path) {
            throw new ParkingException('This snapshot has been deleted under the retention policy.', 'snapshot_purged', 410);
        }
        $this->audit->log('anpr.image_viewed', $image->event, [], ['image' => $image->uuid, 'category' => $image->category]);

        return Storage::disk($image->disk)->response($image->path, $image->uuid, [
            'Content-Type' => $image->mime_type,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
            'Content-Disposition' => 'inline; filename="'.$image->uuid.'"',
        ]);
    }

    /* ---------------------------------------------------------------- internals */

    /**
     * @return array{0: AnprEvent, 1: bool}
     */
    private function record(array $in): array
    {
        $camera = $in['camera'];
        $normalized = $this->normalizer->normalizeCandidates($in['candidates']);
        $best = $normalized[0] ?? null;
        $min = (int) $this->settings->get('anpr.min_confidence_percent') * 100;

        $status = match (true) {
            $in['engine_status'] === 'engine_error' => AnprEvent::ENGINE_ERROR,
            ! $best => AnprEvent::UNREADABLE,
            $best['confidence_bp'] < $min || $best['confusion_corrected'] => AnprEvent::LOW_CONFIDENCE,
            default => AnprEvent::RECOGNIZED,
        };

        try {
            return DB::transaction(function () use ($in, $camera, $normalized, $best, $status) {
                if ($camera) {
                    // Serialise events per camera so duplicate detection is race-free.
                    AnprCamera::whereKey($camera->id)->lockForUpdate()->first();
                }
                if ($in['idempotency_key'] && ($existing = AnprEvent::where('anpr_camera_id', $camera?->id)->where('idempotency_key', $in['idempotency_key'])->first())) {
                    return [$existing, true];
                }

                $duplicateOf = null;
                if ($camera && $best) {
                    $window = (int) $this->settings->get('anpr.duplicate_window_seconds');
                    $duplicateOf = AnprEvent::where('anpr_camera_id', $camera->id)
                        ->where('direction', $in['direction'])
                        ->where('normalized_plate', $best['normalized'])
                        ->where('recognition_status', '!=', AnprEvent::DUPLICATE)
                        ->where('captured_at', '>=', CarbonImmutable::instance($in['captured_at'])->subSeconds($window))
                        ->orderByDesc('id')->first();
                }

                $event = AnprEvent::create([
                    'uuid' => (string) Str::uuid(),
                    'anpr_camera_id' => $camera?->id,
                    'direction' => $in['direction'],
                    'source' => $in['source'],
                    'captured_at' => $in['captured_at'],
                    'raw_text' => isset($in['candidates'][0]['text']) ? mb_substr($in['candidates'][0]['text'], 0, 64) : null,
                    'normalized_plate' => $best['normalized'] ?? null,
                    'plate_format' => $best['format'] ?? ($status === AnprEvent::ENGINE_ERROR ? null : 'unreadable'),
                    'candidates' => $normalized ?: null,
                    'review_suggestions' => $in['review_suggestions'] ?? null,
                    'partial_review_clues' => $in['partial_review_clues'] ?? null,
                    'confidence_bp' => $best['confidence_bp'] ?? null,
                    'recognition_status' => $duplicateOf ? AnprEvent::DUPLICATE : $status,
                    'verification_status' => AnprEvent::PENDING,
                    'duplicate_of_id' => $duplicateOf?->id,
                    'error_code' => $in['error_code'] ? mb_substr($in['error_code'], 0, 40) : null,
                    'processing_ms' => $in['processing_ms'],
                    'idempotency_key' => $in['idempotency_key'],
                    'created_by' => $in['created_by'] ?? null,
                ]);
                $event->forceFill(['vehicle_id' => $this->exactVehicle($event->normalized_plate)?->id])->save();

                if (! $duplicateOf) {
                    $this->storeImages($event, $in['frame'] ?? null, $in['crop'] ?? null);
                }
                if ($camera) {
                    $camera->forceFill(['last_capture_at' => now()] + ($status !== AnprEvent::ENGINE_ERROR ? ['last_success_at' => now()] : ['last_error' => $event->error_code, 'last_error_at' => now()]))->save();
                }

                return [$event, false];
            });
        } catch (UniqueConstraintViolationException) {
            // A concurrent retry with the same idempotency key won: return its event.
            return [AnprEvent::where('anpr_camera_id', $camera?->id)->where('idempotency_key', $in['idempotency_key'])->firstOrFail(), true];
        }
    }

    private function storeImages(AnprEvent $event, ?array $frame, ?array $crop): void
    {
        $toStore = [];
        if ($crop) {
            $toStore['plate_crop'] = $crop;
        }
        if ($frame && ($this->settings->get('anpr.store_full_frame') || ! $crop)) {
            // Without a crop, the frame is the only evidence of what was read.
            $toStore['full_frame'] = $frame;
        }
        $disk = config('parking.anpr.image_disk');
        foreach ($toStore as $category => [$bytes, $mime]) {
            $mime = $this->verifiedImageMime($bytes, $mime);
            if (! $mime) {
                continue;
            }
            $uuid = (string) Str::uuid();
            $path = 'anpr/'.now()->format('Y/m').'/'.$uuid.(['image/png' => '.png', 'image/webp' => '.webp'][$mime] ?? '.jpg');
            Storage::disk($disk)->put($path, $bytes);
            AnprEventImage::create([
                'uuid' => $uuid, 'anpr_event_id' => $event->id, 'category' => $category, 'disk' => $disk, 'path' => $path,
                'mime_type' => $mime, 'size_bytes' => strlen($bytes), 'sha256' => hash('sha256', $bytes), 'captured_at' => $event->captured_at,
            ]);
        }
    }

    /**
     * Content sniffing (not the client-declared type): only real JPEG/PNG under the size limit are stored.
     */
    private function verifiedImageMime(string $bytes, ?string $declared): ?string
    {
        if (strlen($bytes) === 0 || strlen($bytes) > config('parking.anpr.image_max_kilobytes') * 1024) {
            return null;
        }
        $sniffed = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);

        return in_array($sniffed, ['image/jpeg', 'image/png'], true) ? $sniffed : null;
    }

    private function exactVehicle(?string $normalizedPlate): ?Vehicle
    {
        return $normalizedPlate ? Vehicle::where('identifier', SriLankaPlateNormalizer::identifier($normalizedPlate))->first() : null;
    }

    private function resolveDirection(AnprCamera $camera, ?string $direction): string
    {
        if ($camera->direction !== 'dual') {
            if ($direction && $direction !== $camera->direction) {
                throw new ParkingException("This camera only handles {$camera->direction} traffic.", 'camera_direction_mismatch');
            }

            return $camera->direction;
        }
        if (! in_array($direction, ['entry', 'exit'], true)) {
            throw new ParkingException('A direction (entry or exit) is required for dual-direction cameras.', 'direction_required');
        }

        return $direction;
    }

    private function assertOverrideAllowed(User $by): void
    {
        $roles = array_intersect((array) $this->settings->get('anpr.manual_override_roles'), ['staff', 'admin', 'super_admin']);
        if (! in_array($by->role, $roles, true)) {
            throw new ParkingException('You are not permitted to override ANPR readings.', 'override_not_permitted', 403);
        }
    }

    private function review(AnprEvent $event, User $by, string $action, ?string $from, ?string $to, ?string $reason, ?string $notes): void
    {
        AnprReviewAction::create([
            'anpr_event_id' => $event->id, 'user_id' => $by->id, 'action' => $action,
            'from_plate' => $from, 'to_plate' => $to, 'reason' => $reason, 'notes' => $notes ? mb_substr($notes, 0, 500) : null,
        ]);
    }
}
