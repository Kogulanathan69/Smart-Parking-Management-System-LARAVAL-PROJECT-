<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Additive and backward compatible:
 *  - vehicle_categories.is_motorized (bicycles = false)
 *  - vehicles.fuel_type, backfilled: non-motorised -> not_applicable, others -> other
 *  - parking_sessions.vehicle_category_id / fuel_type: the values in force at entry,
 *    backfilled from the vehicle so history and filters stay correct when a vehicle is edited later.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicle_categories', function (Blueprint $table) {
            if (! Schema::hasColumn('vehicle_categories', 'is_motorized')) {
                $table->boolean('is_motorized')->default(true);
            }
        });
        DB::table('vehicle_categories')->where('code', 'bicycle')->update(['is_motorized' => false]);

        Schema::table('vehicles', function (Blueprint $table) {
            if (! Schema::hasColumn('vehicles', 'fuel_type')) {
                $table->string('fuel_type', 20)->nullable();
                $table->index(['vehicle_category_id', 'fuel_type'], 'vehicles_category_fuel_idx');
            }
        });

        $nonMotorized = DB::table('vehicle_categories')->where('is_motorized', false)->pluck('id');
        DB::table('vehicles')->whereNull('fuel_type')->whereIn('vehicle_category_id', $nonMotorized)->update(['fuel_type' => 'not_applicable']);
        DB::table('vehicles')->whereNull('fuel_type')->update(['fuel_type' => 'other']);

        Schema::table('parking_sessions', function (Blueprint $table) {
            if (! Schema::hasColumn('parking_sessions', 'vehicle_category_id')) {
                $table->foreignId('vehicle_category_id')->nullable()->after('vehicle_id')->constrained()->restrictOnDelete();
                $table->string('fuel_type', 20)->nullable()->after('vehicle_category_id');
                $table->index(['vehicle_category_id', 'entry_at'], 'sessions_category_entry_idx');
                $table->index(['fuel_type', 'entry_at'], 'sessions_fuel_entry_idx');
                $table->index(['status', 'entry_at'], 'sessions_status_entry_idx');
                $table->index(['status', 'package_ends_at'], 'sessions_status_expiry_idx');
            }
        });

        DB::table('parking_sessions')->whereNull('vehicle_category_id')->update([
            'vehicle_category_id' => DB::raw('(SELECT vehicles.vehicle_category_id FROM vehicles WHERE vehicles.id = parking_sessions.vehicle_id)'),
            'fuel_type' => DB::raw('(SELECT vehicles.fuel_type FROM vehicles WHERE vehicles.id = parking_sessions.vehicle_id)'),
        ]);
    }

    public function down(): void
    {
        Schema::table('parking_sessions', function (Blueprint $table) {
            if (Schema::hasColumn('parking_sessions', 'vehicle_category_id')) {
                $table->dropIndex('sessions_category_entry_idx');
                $table->dropIndex('sessions_fuel_entry_idx');
                $table->dropIndex('sessions_status_entry_idx');
                $table->dropIndex('sessions_status_expiry_idx');
                $table->dropConstrainedForeignId('vehicle_category_id');
                $table->dropColumn('fuel_type');
            }
        });
        Schema::table('vehicles', function (Blueprint $table) {
            if (Schema::hasColumn('vehicles', 'fuel_type')) {
                $table->dropIndex('vehicles_category_fuel_idx');
                $table->dropColumn('fuel_type');
            }
        });
        Schema::table('vehicle_categories', function (Blueprint $table) {
            if (Schema::hasColumn('vehicle_categories', 'is_motorized')) {
                $table->dropColumn('is_motorized');
            }
        });
    }
};
