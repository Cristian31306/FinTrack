<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_responsibles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_id')->constrained('purchases')->cascadeOnDelete();
            $table->foreignId('responsible_person_id')->constrained('responsible_people')->cascadeOnDelete();
            $table->string('split_type', 16);
            $table->decimal('split_value', 14, 4);
            $table->decimal('owed_amount', 14, 2);
            $table->string('status', 16)->default('pendiente');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->unique(['purchase_id', 'responsible_person_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_responsibles');
    }
};
