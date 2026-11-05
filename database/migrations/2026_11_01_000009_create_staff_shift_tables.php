<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 50);
            /** Local (Asia/Colombo) wall-clock times; end < start means the shift crosses midnight. */
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedTinyInteger('min_gate_staff')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('shift_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shift_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->date('shift_date');
            $table->boolean('is_gate_duty')->default(true);
            /** scheduled | on_leave | replaced | cancelled */
            $table->string('status', 15)->default('scheduled');
            $table->foreignId('replacement_for_id')->nullable()->constrained('shift_assignments')->nullOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'shift_id', 'shift_date']);
            $table->index(['shift_date', 'shift_id']);
        });

        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shift_assignment_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->timestamp('check_in_at')->nullable();
            $table->timestamp('check_out_at')->nullable();
            /** present | late | absent | left_early */
            $table->string('status', 15)->default('present');
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'check_in_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendances');
        Schema::dropIfExists('shift_assignments');
        Schema::dropIfExists('shifts');
    }
};
