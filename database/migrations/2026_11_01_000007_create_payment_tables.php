<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 30)->unique();
            /** Client-supplied key; repeating a request with the same key never charges twice. */
            $table->string('idempotency_key', 100)->unique();
            $table->foreignId('parking_session_id')->constrained()->restrictOnDelete();
            $table->foreignId('payment_method_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('amount_minor');
            $table->unsignedBigInteger('tendered_minor')->nullable();
            $table->unsignedBigInteger('change_minor')->default(0);
            $table->char('currency', 3)->default('LKR');
            /** pending | paid | failed | refunded */
            $table->string('status', 20)->default('pending')->index();
            $table->string('external_reference', 100)->nullable();
            $table->string('gateway', 30)->nullable();
            $table->string('failure_reason')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('receipts', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->unique();
            $table->foreignId('parking_session_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('total_minor');
            $table->unsignedBigInteger('paid_minor');
            $table->unsignedBigInteger('credit_issued_minor')->default(0);
            $table->unsignedBigInteger('refund_minor')->default(0);
            $table->char('currency', 3)->default('LKR');
            $table->json('breakdown');
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('issued_at');
            $table->timestamps();
        });

        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 30)->unique();
            $table->foreignId('parking_session_id')->constrained()->restrictOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('amount_minor');
            /** pending | approved | paid | rejected */
            $table->string('status', 20)->default('pending')->index();
            $table->string('reason')->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        /** Append-only credit ledger: positive = credit issued, negative = credit used. */
        Schema::create('customer_credits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('parking_session_id')->nullable()->constrained()->nullOnDelete();
            $table->bigInteger('amount_minor');
            $table->string('reason');
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['customer_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_credits');
        Schema::dropIfExists('refunds');
        Schema::dropIfExists('receipts');
        Schema::dropIfExists('payments');
    }
};
