<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Composição da carteira no informe da CVM; nulos porque o arquivo ativo_passivo é opcional. */
    public function up(): void
    {
        Schema::table('fund_reports', function (Blueprint $table) {
            $table->decimal('total_invested', 24, 2)->nullable();
            $table->decimal('real_estate', 24, 2)->nullable();
            $table->decimal('receivables', 24, 2)->nullable();
            $table->decimal('fii_shares', 24, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('fund_reports', function (Blueprint $table) {
            $table->dropColumn(['total_invested', 'real_estate', 'receivables', 'fii_shares']);
        });
    }
};
