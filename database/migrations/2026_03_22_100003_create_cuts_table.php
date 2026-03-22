<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cuts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credit_card_id')->constrained('credit_cards')->cascadeOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('total_accrued', 14, 2)->default(0);
            $table->string('status', 32)->default('abierto');
            $table->timestamps();

            $table->unique(['credit_card_id', 'period_end']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cuts');
    }
};
