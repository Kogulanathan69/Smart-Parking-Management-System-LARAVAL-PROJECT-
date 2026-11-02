<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per reminder actually sent. The unique key makes reminder processing idempotent:
     * a re-run (or two overlapping scheduler runs) can never notify twice.
     */
    public function up(): void
    {
        Schema::create('session_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parking_session_id')->constrained()->cascadeOnDelete();
            /** expiring | expired */
            $table->string('kind', 10);
            /** Minutes-before-expiry threshold for "expiring"; 0 for "expired". */
            $table->unsignedInteger('threshold_minutes')->default(0);
            $table->timestamp('sent_at');

            $table->unique(['parking_session_id', 'kind', 'threshold_minutes'], 'session_reminders_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_reminders');
    }
};
