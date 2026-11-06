<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Os padrões valem também para os favoritos que já existem: favorito novo começa
        // automático, com queda forte ligada.
        Schema::table('favorites', function (Blueprint $table) {
            $table->string('mode', 16)->default('automatic');
            $table->decimal('target', 18, 6)->nullable();
            $table->decimal('range_min', 18, 6)->nullable();
            $table->decimal('range_max', 18, 6)->nullable();
            $table->boolean('sharp_drop')->default(true);
            $table->timestampTz('last_evaluated_quote_at')->nullable();
        });

        Schema::create('rule_states', function (Blueprint $table) {
            $table->id();
            $table->string('ticker', 12);
            $table->string('rule', 16);
            $table->boolean('active');
            $table->boolean('pending');
            $table->string('event_key', 10)->nullable();
            $table->decimal('last_alert_price', 18, 6)->nullable();
            $table->timestampTz('last_alert_at')->nullable();
            $table->timestamps();
            $table->unique(['ticker', 'rule']);
        });

        Schema::create('alerts', function (Blueprint $table) {
            $table->id();
            $table->string('ticker', 12)->index();
            $table->string('rule', 16);
            $table->decimal('price', 18, 6);
            $table->string('reference');
            $table->text('reason');
            $table->timestampTz('occurred_at')->index();
            $table->timestampTz('quoted_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alerts');
        Schema::dropIfExists('rule_states');

        Schema::table('favorites', function (Blueprint $table) {
            $table->dropColumn(['mode', 'target', 'range_min', 'range_max', 'sharp_drop', 'last_evaluated_quote_at']);
        });
    }
};
