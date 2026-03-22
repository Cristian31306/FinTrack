<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_id')->constrained('purchases')->cascadeOnDelete();
            $table->foreignId('cut_id')->nullable()->constrained('cuts')->nullOnDelete();
            $table->unsignedSmallInteger('installment_number');
            $table->decimal('principal_amount', 14, 2);
            $table->decimal('interest_amount', 14, 2)->default(0);
            $table->decimal('total_amount', 14, 2);
            $table->date('statement_close_date');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_installments');
    }
};
