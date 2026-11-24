<?php
// database/migrations/2026_09_03_000003_add_channel_to_transfers.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transfers', function (Blueprint $table) {
            $table->string('channel', 20)->nullable()->after('status');
            $table->json('metadata')->nullable()->after('channel');
        });
    }

    public function down(): void
    {
        Schema::table('transfers', function (Blueprint $table) {
            $table->dropColumn(['channel', 'metadata']);
        });
    }
};