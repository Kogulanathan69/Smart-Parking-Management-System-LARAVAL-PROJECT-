<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additive ANPR (automatic number plate recognition) schema. Nothing existing is altered.
 *
 *  anpr_cameras            camera registry, encrypted stream credentials, hashed device token, health
 *  anpr_events             one row per recognition / manual identification, single-use for entry/exit
 *  anpr_event_images       private snapshot references with retention state
 *  anpr_camera_health_logs connectivity / service health transitions
 *  anpr_review_actions     append-only staff verification and correction trail
 *  gate_commands           every gate open attempt; at most one success per session and direction
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('anpr_cameras', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name', 100);
            /** entry | exit | dual */
            $table->string('direction', 10);
            $table->string('gate_code', 40)->nullable()->index();
            /** ip_rtsp | ip_http_snapshot | usb_local | other */
            $table->string('camera_type', 30);
            /** Encrypted (APP_KEY). Never returned by the API; read only by the authenticated ANPR agent. */
            $table->text('stream_url_encrypted')->nullable();
            $table->text('stream_username_encrypted')->nullable();
            $table->text('stream_password_encrypted')->nullable();
            /** Non-secret host shown to admins (e.g. 192.168.1.20). */
            $table->string('stream_host', 255)->nullable();
            /** SHA-256 of the device ingest token; the plaintext is shown once on creation/rotation. */
            $table->char('ingest_token_hash', 64)->unique();
            $table->timestamp('token_rotated_at')->nullable();
            $table->boolean('is_enabled')->default(true)->index();
            /** unknown | online | offline | error */
            $table->string('health_status', 10)->default('unknown');
            $table->timestamp('last_heartbeat_at')->nullable();
            $table->timestamp('last_capture_at')->nullable();
            $table->timestamp('last_success_at')->nullable();
            $table->string('last_error', 255)->nullable();
            $table->timestamp('last_error_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('anpr_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('anpr_camera_id')->nullable()->constrained()->restrictOnDelete();
            /** entry | exit */
            $table->string('direction', 5);
            /** camera (device push) | upload (staff capture through engine) | manual (no recognition) */
            $table->string('source', 10);
            $table->timestamp('captured_at');
            /** Exactly what the engine read, before any normalisation. */
            $table->string('raw_text', 64)->nullable();
            $table->string('normalized_plate', 30)->nullable()->index();
            /** Plate format detected by the Sri Lankan normaliser (modern, legacy_numeric, unknown, ...). */
            $table->string('plate_format', 30)->nullable();
            $table->json('candidates')->nullable();
            /** Confidence in basis points (0-10000) to avoid floats. */
            $table->unsignedSmallInteger('confidence_bp')->nullable();
            /** recognized | low_confidence | unreadable | engine_error | manual | duplicate */
            $table->string('recognition_status', 20)->index();
            /** pending | verified | corrected | rejected */
            $table->string('verification_status', 10)->default('pending')->index();
            $table->string('verified_plate', 30)->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            /** Mandatory reason when a human overrides/creates the reading. */
            $table->string('override_reason', 30)->nullable();
            $table->string('override_notes', 500)->nullable();
            $table->foreignId('vehicle_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('parking_session_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('duplicate_of_id')->nullable()->constrained('anpr_events')->nullOnDelete();
            /** When the event was used to confirm an entry or settle an exit; an event is single-use. */
            $table->timestamp('consumed_at')->nullable();
            $table->string('error_code', 40)->nullable();
            $table->unsignedInteger('processing_ms')->nullable();
            /** Client-supplied retry key, unique per camera (device retries return the same event). */
            $table->string('idempotency_key', 100)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['anpr_camera_id', 'idempotency_key'], 'anpr_events_camera_idempotency_unique');
            /** One entry event and one exit event per parking session at most. */
            $table->unique(['parking_session_id', 'direction'], 'anpr_events_session_direction_unique');
            $table->index(['anpr_camera_id', 'direction', 'normalized_plate', 'captured_at'], 'anpr_events_dedup_idx');
            $table->index(['captured_at']);
        });

        Schema::create('anpr_event_images', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('anpr_event_id')->constrained()->cascadeOnDelete();
            /** full_frame | plate_crop */
            $table->string('category', 12);
            $table->string('disk', 20);
            /** Random name under anpr/YYYY/MM; never exposed by the API. NULL once purged. */
            $table->string('path')->nullable();
            $table->string('mime_type', 50);
            $table->unsignedInteger('size_bytes');
            $table->char('sha256', 64);
            $table->timestamp('captured_at');
            /** stored | purged */
            $table->string('retention_status', 10)->default('stored')->index();
            $table->timestamp('purged_at')->nullable();
            $table->timestamps();
        });

        Schema::create('anpr_camera_health_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('anpr_camera_id')->nullable()->constrained()->cascadeOnDelete();
            /** camera | engine */
            $table->string('component', 10);
            /** online | offline | error | recovered */
            $table->string('status', 10);
            $table->string('message', 255)->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });

        Schema::create('anpr_review_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('anpr_event_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            /** confirm | correct | reject | manual_create */
            $table->string('action', 15);
            $table->string('from_plate', 30)->nullable();
            $table->string('to_plate', 30)->nullable();
            $table->string('reason', 30)->nullable();
            $table->string('notes', 500)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });

        Schema::create('gate_commands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parking_session_id')->constrained()->restrictOnDelete();
            /** entry | exit */
            $table->string('direction', 5);
            $table->string('gate_code', 40)->nullable();
            /** success | failed */
            $table->string('status', 10);
            $table->string('driver', 30);
            $table->string('result', 255)->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            /** "{session}:{direction}" on success, NULL on failure: a gate opens at most once per authorisation. */
            $table->string('success_key', 50)->nullable()->unique();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['parking_session_id', 'direction']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gate_commands');
        Schema::dropIfExists('anpr_review_actions');
        Schema::dropIfExists('anpr_camera_health_logs');
        Schema::dropIfExists('anpr_event_images');
        Schema::dropIfExists('anpr_events');
        Schema::dropIfExists('anpr_cameras');
    }
};
