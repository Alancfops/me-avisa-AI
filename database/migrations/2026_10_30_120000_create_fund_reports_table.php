<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fund_reports', function (Blueprint $table) {
            $table->id();
            $table->string('cnpj', 20);
            $table->string('ticker', 12)->index();
            // Competência em Y-m; a data de vigência é delivered_on.
            $table->string('month', 7);
            $table->unsignedInteger('version');
            $table->date('delivered_on');
            // A CVM grava até 13 casas no VP, e passar por 6 mudaria o P/VP diário.
            $table->decimal('book_value', 28, 13)->nullable();
            $table->decimal('dividend_yield', 18, 10)->nullable();
            $table->timestamps();
            $table->unique(['cnpj', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fund_reports');
    }
};
