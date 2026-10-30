<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('funds', function (Blueprint $table) {
            $table->string('ticker', 12)->primary();
            $table->string('name');
            $table->timestamps();
        });

        // Sem chave estrangeira para favorites: a cotação e o histórico sobrevivem a
        // remover um favorito, e o histórico curto é caro de recuperar (3 meses grátis).
        Schema::create('quotes', function (Blueprint $table) {
            $table->string('ticker', 12)->primary();
            $table->decimal('price', 18, 6);
            $table->decimal('change_percent', 12, 6)->nullable();
            $table->unsignedBigInteger('volume')->nullable();
            $table->timestampTz('quoted_at');
            $table->timestamps();
        });

        Schema::create('daily_prices', function (Blueprint $table) {
            $table->id();
            $table->string('ticker', 12);
            $table->date('day');
            $table->decimal('close', 18, 6);
            $table->timestamps();
            $table->unique(['ticker', 'day']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_prices');
        Schema::dropIfExists('quotes');
        Schema::dropIfExists('funds');
    }
};
