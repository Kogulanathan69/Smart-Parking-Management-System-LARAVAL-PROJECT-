<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Forward-only, additive support for weekly/monthly guaranteed parking.
 *
 *  - parking_packages.guarantees_slot: which packages hold a dedicated slot for their whole validity.
 *    Backfilled ON for fixed packages of 7 days or longer (weekly, monthly); configurable afterwards.
 *  - parking_sessions.parking_reservation_id: links every visit (first entry and re-entries) to its reservation.
 *  - reservation_periods: each paid validity period (initial purchase, renewals) and the session it is billed on,
 *    so the base package price is charged exactly once per period, never per visit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parking_packages', function (Blueprint $table) {
            if (! Schema::hasColumn('parking_packages', 'guarantees_slot')) {
                $table->boolean('guarantees_slot')->default(false);
            }
        });
        DB::table('parking_packages')
            ->where('billing_mode', 'fixed')
            ->where('duration_minutes', '>=', 10080)
            ->update(['guarantees_slot' => true]);

        Schema::table('parking_sessions', function (Blueprint $table) {
            if (! Schema::hasColumn('parking_sessions', 'parking_reservation_id')) {
                $table->foreignId('parking_reservation_id')->nullable()->after('parking_slot_id')
                    ->constrained('parking_reservations')->restrictOnDelete();
            }
        });

        if (! Schema::hasTable('reservation_periods')) {
            Schema::create('reservation_periods', function (Blueprint $table) {
                $table->id();
                $table->foreignId('parking_reservation_id')->constrained()->restrictOnDelete();
                $table->unsignedSmallInteger('sequence');
                /** initial | renewal */
                $table->string('kind', 10);
                $table->timestamp('starts_at');
                $table->timestamp('ends_at');
                $table->foreignId('package_rate_id')->constrained()->restrictOnDelete();
                /** Package price for this period, copied from the rate (integer minor units). */
                $table->unsignedBigInteger('price_minor');
                $table->char('currency', 3)->default('LKR');
                /** The parking session whose bill carries this period's price; NULL until the vehicle is next on site. */
                $table->foreignId('billed_session_id')->nullable()->constrained('parking_sessions')->restrictOnDelete();
                /** pending (not yet on a bill) | billed | void (cancelled before it started) */
                $table->string('status', 10)->default('pending');
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['parking_reservation_id', 'sequence']);
                $table->index(['billed_session_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('reservation_periods');
        Schema::table('parking_sessions', function (Blueprint $table) {
            if (Schema::hasColumn('parking_sessions', 'parking_reservation_id')) {
                $table->dropConstrainedForeignId('parking_reservation_id');
            }
        });
        Schema::table('parking_packages', function (Blueprint $table) {
            if (Schema::hasColumn('parking_packages', 'guarantees_slot')) {
                $table->dropColumn('guarantees_slot');
            }
        });
    }
};
