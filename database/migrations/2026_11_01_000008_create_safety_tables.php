<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('safety_inspections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parking_session_id')->constrained()->restrictOnDelete();
            /** entry | routine | exit */
            $table->string('type', 10);
            /** scheduled | completed | missed */
            $table->string('status', 15)->default('scheduled')->index();
            $table->timestamp('scheduled_for')->nullable()->index();
            $table->timestamp('performed_at')->nullable();
            $table->foreignId('inspected_by')->nullable()->constrained('users')->nullOnDelete();
            /** good | minor_damage | major_damage */
            $table->string('condition', 20)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('new_damage_found')->default(false);
            $table->timestamps();

            $table->index(['parking_session_id', 'type']);
        });

        Schema::create('incidents', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 20)->unique();
            $table->foreignId('parking_session_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('vehicle_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('safety_inspection_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            /** damage | theft | accident | other */
            $table->string('type', 20);
            /** low | medium | high */
            $table->string('severity', 10)->default('medium');
            $table->text('description');
            /** open | investigating | resolved | closed */
            $table->string('status', 20)->default('open')->index();
            $table->text('resolution')->nullable();
            $table->unsignedBigInteger('compensation_minor')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });

        /** Photo evidence stored on the private disk; served only via an authorised controller. */
        Schema::create('evidence_files', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->morphs('attachable');
            $table->string('disk', 20)->default('local');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 100);
            $table->unsignedInteger('size_bytes');
            $table->char('sha256', 64);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evidence_files');
        Schema::dropIfExists('incidents');
        Schema::dropIfExists('safety_inspections');
    }
};
