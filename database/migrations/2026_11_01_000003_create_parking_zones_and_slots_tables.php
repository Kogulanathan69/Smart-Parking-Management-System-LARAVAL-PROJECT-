<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parking_zones', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 100);
            $table->foreignId('zone_type_id')->constrained()->restrictOnDelete();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        /** Which vehicle categories a zone accepts (empty = none). */
        Schema::create('parking_zone_vehicle_category', function (Blueprint $table) {
            $table->foreignId('parking_zone_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_category_id')->constrained()->cascadeOnDelete();
            $table->primary(['parking_zone_id', 'vehicle_category_id']);
        });

        Schema::create('parking_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parking_zone_id')->constrained()->restrictOnDelete();
            $table->foreignId('vehicle_category_id')->constrained()->restrictOnDelete();
            $table->string('code', 30);
            /** available | occupied | reserved | unavailable */
            $table->string('status', 20)->default('available');
            $table->string('notes')->nullable();
            $table->unsignedInteger('lock_version')->default(0);
            /** Set when capacity is reduced; decommissioned slots are kept for history but never allocated. */
            $table->timestamp('decommissioned_at')->nullable();
            $table->timestamps();

            $table->unique(['parking_zone_id', 'code']);
            $table->index(['parking_zone_id', 'vehicle_category_id', 'status'], 'slots_allocation_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parking_slots');
        Schema::dropIfExists('parking_zone_vehicle_category');
        Schema::dropIfExists('parking_zones');
    }
};
