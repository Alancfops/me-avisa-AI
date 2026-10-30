<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('source_events', function (Blueprint $table) {
            $table->id();
            $table->string('source', 16);
            $table->string('kind', 16);
            $table->text('message')->nullable();
            $table->timestampTz('occurred_at');
            $table->timestamps();
            $table->index(['source', 'kind', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('source_events');
    }
};
