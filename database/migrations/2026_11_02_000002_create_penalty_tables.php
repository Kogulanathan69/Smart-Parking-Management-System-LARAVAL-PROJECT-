<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /** Admin-defined penalty catalogue (lost ticket, rule violation, ...). Amounts in minor units. */
        Schema::create('penalty_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->unsignedBigInteger('amount_minor');
            $table->boolean('is_active')->default(true);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        /**
         * Penalties applied to a parking session. The amount is copied at application time so later
         * catalogue changes never alter an applied penalty. Waived rows are kept for audit.
         */
        Schema::create('session_penalties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parking_session_id')->constrained()->restrictOnDelete();
            $table->foreignId('penalty_type_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('amount_minor');
            $table->string('reason', 500);
            /** applied | waived */
            $table->string('status', 10)->default('applied')->index();
            $table->foreignId('applied_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('applied_at');
            $table->foreignId('waived_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('waived_at')->nullable();
            $table->string('waiver_reason', 500)->nullable();
            /**
             * "{session_id}:{penalty_type_id}" while applied, NULL once waived:
             * the unique index makes a duplicate active penalty impossible, even under concurrency.
             */
            $table->string('active_key', 50)->nullable()->unique();
            $table->timestamps();

            $table->index(['parking_session_id', 'status']);
            $table->index('applied_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_penalties');
        Schema::dropIfExists('penalty_types');
    }
};
