<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parking_reservations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('vehicle_id')
                ->constrained()->restrictOnDelete();

            $table->foreignId('customer_id')
                ->nullable()->constrained('users')->nullOnDelete();

            $table->foreignId('parking_zone_id')
                ->constrained()->restrictOnDelete();

            $table->foreignId('parking_slot_id')
                ->constrained()->restrictOnDelete();

            $table->foreignId('parking_package_id')
                ->constrained()->restrictOnDelete();

            $table->foreignId('package_rate_id')
                ->constrained()->restrictOnDelete();

            $table->string('status', 20)->default('active');

            $table->timestamp('starts_at');
            $table->timestamp('expires_at');
            $table->timestamp('cancelled_at')->nullable();

            $table->json('pricing_snapshot');

            // NULL when inactive. MySQL permits multiple NULLs
            // in a unique index.
            $table->unsignedBigInteger('active_vehicle_key')
                ->nullable()->unique();

            $table->unsignedBigInteger('active_slot_key')
                ->nullable()->unique();

            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['status', 'expires_at']);
            $table->index(['vehicle_id', 'starts_at']);
            $table->index(['parking_slot_id', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parking_reservations');
    }
};
