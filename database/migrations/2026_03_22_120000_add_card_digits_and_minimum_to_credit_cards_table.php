<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('credit_cards', function (Blueprint $table) {
            if (!Schema::hasColumn('credit_cards', 'last_4_digits')) {
                $table->string('last_4_digits', 4)->nullable()->after('franchise');
            }
            if (!Schema::hasColumn('credit_cards', 'minimum_payment_percent')) {
                $table->decimal('minimum_payment_percent', 5, 2)->default(5)->after('annual_interest_ea');
            }
        });
    }

    public function down(): void
    {
        Schema::table('credit_cards', function (Blueprint $table) {
            $cols = array_filter(
                ['last_4_digits', 'minimum_payment_percent'],
                fn($col) => Schema::hasColumn('credit_cards', $col)
            );
            if ($cols) {
                $table->dropColumn(array_values($cols));
            }
        });
    }
};
