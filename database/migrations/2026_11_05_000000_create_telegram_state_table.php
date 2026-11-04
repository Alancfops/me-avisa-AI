<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Uma linha só: uma pessoa por instalação, um chat.
        Schema::create('telegram_state', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('chat_id')->nullable();
            $table->timestampTz('connected_at')->nullable();
            $table->string('link_code', 6)->nullable();
            $table->timestampTz('link_code_expires_at')->nullable();
            $table->bigInteger('last_update_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_state');
    }
};
