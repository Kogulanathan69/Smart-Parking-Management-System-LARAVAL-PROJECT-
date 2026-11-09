<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('anpr_events', function (Blueprint $table) {
            $table->json('review_suggestions')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('anpr_events', function (Blueprint $table) {
            $table->dropColumn('review_suggestions');
        });
    }
};
