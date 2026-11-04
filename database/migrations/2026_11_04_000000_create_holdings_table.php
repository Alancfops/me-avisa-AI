<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('holdings', function (Blueprint $table) {
            $table->id();
            $table->string('ticker', 12)->unique();
            $table->unsignedInteger('shares');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('holdings');
    }
};
