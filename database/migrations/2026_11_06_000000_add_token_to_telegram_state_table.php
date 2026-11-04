<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Token colado na aba Telegram, criptografado pelo model.
        Schema::table('telegram_state', function (Blueprint $table) {
            $table->text('token')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('telegram_state', function (Blueprint $table) {
            $table->dropColumn('token');
        });
    }
};
