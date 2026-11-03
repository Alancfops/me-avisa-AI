<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Perfil do fundo no informe mensal da CVM; todos nulos porque o arquivo pode não trazê-los. */
    public function up(): void
    {
        Schema::table('fund_reports', function (Blueprint $table) {
            $table->string('segment', 120)->nullable();
            $table->string('administrator', 255)->nullable();
            $table->date('started_on')->nullable();
            $table->decimal('net_assets', 24, 2)->nullable();
            $table->unsignedInteger('holders')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('fund_reports', function (Blueprint $table) {
            $table->dropColumn(['segment', 'administrator', 'started_on', 'net_assets', 'holders']);
        });
    }
};
