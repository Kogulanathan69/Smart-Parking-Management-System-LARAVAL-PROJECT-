<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parking_sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('reference', 20)->unique();
            $table->foreignId('vehicle_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('users')->nullOnDelete();
            /** Walk-in contact details captured by staff when the driver has no account. */
            $table->string('customer_name', 100)->nullable();
            $table->string('customer_phone', 30)->nullable()->index();
            $table->foreignId('parking_zone_id')->constrained()->restrictOnDelete();
            $table->foreignId('parking_slot_id')->constrained()->restrictOnDelete();
            $table->foreignId('parking_package_id')->constrained()->restrictOnDelete();
            $table->foreignId('package_rate_id')->constrained()->restrictOnDelete();
            $table->foreignId('safety_rate_id')->nullable()->constrained('package_rates')->restrictOnDelete();
            $table->boolean('has_safety')->default(false);
            /** active | exit_authorized | completed | cancelled */
            $table->string('status', 20)->default('active')->index();
            $table->timestamp('entry_at');
            $table->timestamp('package_ends_at')->nullable();
            $table->timestamp('exit_at')->nullable();
            $table->foreignId('entry_staff_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('exit_staff_id')->nullable()->constrained('users')->nullOnDelete();
            /** Rates captured at entry so later price changes never apply retroactively. */
            $table->json('pricing_snapshot');
            $table->json('charge_breakdown')->nullable();
            $table->unsignedBigInteger('total_charge_minor')->nullable();
            /**
             * DB-level guards: set to vehicle_id / slot_id while the session is open, NULL afterwards.
             * Unique indexes allow many NULLs, so only one open session per vehicle and per slot can exist.
             */
            $table->unsignedBigInteger('open_vehicle_key')->nullable()->unique();
            $table->unsignedBigInteger('open_slot_key')->nullable()->unique();
            $table->string('entry_source', 20)->default('manual');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['vehicle_id', 'entry_at']);
            $table->index(['customer_id', 'entry_at']);
            $table->index('exit_at');
        });

        Schema::create('qr_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parking_session_id')->constrained()->cascadeOnDelete();
            /** SHA-256 of the random token; the raw token is never stored in plain text. */
            $table->char('token_hash', 64)->unique();
            /** Encrypted raw token (APP_KEY) so staff can reprint a lost ticket. */
            $table->text('token_encrypted');
            /** active | used | void */
            $table->string('status', 10)->default('active')->index();
            $table->unsignedSmallInteger('print_count')->default(0);
            $table->timestamp('issued_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qr_tickets');
        Schema::dropIfExists('parking_sessions');
    }
};
