<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->string('whatsapp_number')->index();
            $table->string('client_name')->nullable();
            $table->foreignId('current_sector_id')->nullable()->constrained('sectors')->onDelete('set null');
            $table->foreignId('current_agent_id')->nullable()->constrained('users')->onDelete('set null');
            $table->enum('status', ['new', 'queued', 'in_progress', 'waiting_client', 'closed', 'archived'])->default('new');
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
