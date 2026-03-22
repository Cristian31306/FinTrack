<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('franchise', 64);
            $table->decimal('credit_limit', 14, 2);
            $table->decimal('annual_interest_ea', 8, 4)->default(0);
            $table->unsignedTinyInteger('statement_day')->comment('Día de cierre de facturación 1-31');
            $table->unsignedTinyInteger('payment_day')->comment('Día límite de pago 1-31');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_cards');
    }
};
