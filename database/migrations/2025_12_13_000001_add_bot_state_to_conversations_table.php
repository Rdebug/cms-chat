<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->string('bot_state')->default('idle')->after('status');
            $table->timestamp('bot_last_prompt_at')->nullable()->after('bot_state');
            $table->timestamp('bot_menu_sent_at')->nullable()->after('bot_last_prompt_at');
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropColumn(['bot_state', 'bot_last_prompt_at', 'bot_menu_sent_at']);
        });
    }
};




