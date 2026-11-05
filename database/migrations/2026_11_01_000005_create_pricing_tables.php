<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parking_packages', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 100);
            /** hourly | fixed | night | addon */
            $table->string('billing_mode', 20);
            /** Package length for fixed packages (daily 1440, weekly 10080, monthly 43200). */
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->boolean('allows_early_exit_credit')->default(false);
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();
        });

        /** Zone types where a package may be sold; no rows = allowed in every zone. */
        Schema::create('package_zone_type', function (Blueprint $table) {
            $table->foreignId('parking_package_id')->constrained()->cascadeOnDelete();
            $table->foreignId('zone_type_id')->constrained()->cascadeOnDelete();
            $table->primary(['parking_package_id', 'zone_type_id']);
        });

        /**
         * Versioned, append-only rates. A new version closes the previous one (effective_to).
         * All amounts are integer minor units (LKR cents).
         * For the safety add-on package, overtime_hourly_minor is the extra safety overtime charge.
         */
        Schema::create('package_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parking_package_id')->constrained()->restrictOnDelete();
            $table->foreignId('vehicle_category_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->unsignedBigInteger('price_minor');
            $table->unsignedBigInteger('overtime_hourly_minor')->default(0);
            $table->unsignedBigInteger('overtime_daily_cap_minor')->nullable();
            $table->unsignedSmallInteger('grace_minutes')->default(15);
            $table->char('currency', 3)->default('LKR');
            $table->timestamp('effective_from');
            $table->timestamp('effective_to')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['parking_package_id', 'vehicle_category_id', 'version'], 'package_rate_version_unique');
            $table->index(['parking_package_id', 'vehicle_category_id', 'effective_from'], 'package_rate_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('package_rates');
        Schema::dropIfExists('package_zone_type');
        Schema::dropIfExists('parking_packages');
    }
};
