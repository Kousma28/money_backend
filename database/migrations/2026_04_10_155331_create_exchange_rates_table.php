<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('from_country_id')->constrained('countries')->restrictOnDelete();
            $table->foreignId('to_country_id')->constrained('countries')->restrictOnDelete();
            $table->string('from_currency', 10);     // XAF
            $table->string('to_currency', 10);       // XOF
            $table->decimal('rate', 18, 8);          // 1 XAF = X XOF
            $table->decimal('fee_percent', 5, 4)->default(2.0); // 2 %
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['from_country_id', 'to_country_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exchange_rates');
    }
};