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
        Schema::create('transfer_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('conversations')->onDelete('cascade');
            $table->foreignId('from_sector_id')->nullable()->constrained('sectors')->onDelete('set null');
            $table->foreignId('to_sector_id')->constrained('sectors')->onDelete('cascade');
            $table->foreignId('from_agent_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('to_agent_id')->nullable()->constrained('users')->onDelete('set null');
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transfer_logs');
    }
};
