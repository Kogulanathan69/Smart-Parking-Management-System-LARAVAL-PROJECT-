<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            /** Normalised plate (upper-case, no spaces/dashes) OR a generated reference (e.g. BIKE-XXXX) for plate-less vehicles. */
            $table->string('identifier', 30)->unique();
            $table->string('plate_number', 30)->nullable();
            $table->boolean('is_reference')->default(false);
            $table->foreignId('vehicle_category_id')->constrained()->restrictOnDelete();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('make', 50)->nullable();
            $table->string('model', 50)->nullable();
            $table->string('color', 30)->nullable();
            $table->foreignId('registered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('owner_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
