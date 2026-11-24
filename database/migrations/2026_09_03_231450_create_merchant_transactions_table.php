<?php
// database/migrations/2026_09_03_000002_create_merchant_transactions_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merchant_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('merchant_id')->constrained('merchants')->restrictOnDelete();
            $table->enum('type', ['deposit', 'withdrawal', 'payment']);
            $table->decimal('amount', 18, 4);
            $table->string('currency', 10)->default('XOF');
            $table->enum('status', ['pending', 'completed', 'failed'])->default('pending');
            $table->string('channel', 20)->nullable(); // mobile, web, ussd, qr
            $table->string('reference', 30)->unique();
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['merchant_id', 'created_at']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant_transactions');
    }
};