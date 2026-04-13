<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transfers', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 30)->unique(); // TXN-20240101-XXXX

            // Expéditeur
            $table->foreignId('sender_id')->constrained('users')->restrictOnDelete();
            $table->string('sender_phone', 20);
            $table->foreignId('from_country_id')->constrained('countries')->restrictOnDelete();

            // Destinataire
            $table->foreignId('receiver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('receiver_phone', 20);
            $table->foreignId('to_country_id')->constrained('countries')->restrictOnDelete();

            // Montants
            $table->decimal('amount_sent', 18, 4);       // dans la devise expéditeur
            $table->string('currency_sent', 10);
            $table->decimal('amount_received', 18, 4);   // dans la devise destinataire
            $table->string('currency_received', 10);

            // Taux & frais
            $table->decimal('exchange_rate', 18, 8);
            $table->decimal('fee_amount', 18, 4);
            $table->decimal('fee_percent', 5, 4);
            $table->decimal('total_deducted', 18, 4);    // amount_sent + fee_amount

            // Statut
            $table->enum('status', ['pending', 'completed', 'failed', 'cancelled'])
                  ->default('pending');
            $table->text('failure_reason')->nullable();

            $table->timestamps();

            $table->index(['sender_id', 'created_at']);
            $table->index(['receiver_phone', 'to_country_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transfers');
    }
};