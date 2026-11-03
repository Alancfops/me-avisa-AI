<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Dias cujo arquivo da B3 já foi lido. A tabela de fechamentos não serve: os favoritos
        // recebem o dia da brapi antes, e o dia pareceria importado sem os outros fundos.
        Schema::create('exchange_imports', function (Blueprint $table) {
            $table->date('day')->primary();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exchange_imports');
    }
};
