<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Com 6 casas, −3,0000000123% voltaria do banco como −3% e não dispararia. */
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->decimal('change_percent', 20, 10)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->decimal('change_percent', 12, 6)->nullable()->change();
        });
    }
};
