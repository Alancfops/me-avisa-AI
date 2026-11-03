<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Anos cujo arquivo anual da B3 já foi lido por inteiro: cada ano é tentado de novo até entrar.
        Schema::create('exchange_year_imports', function (Blueprint $table) {
            $table->unsignedSmallInteger('year')->primary();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exchange_year_imports');
    }
};
