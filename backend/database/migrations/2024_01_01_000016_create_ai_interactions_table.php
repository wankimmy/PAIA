<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_interactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('interaction_type'); // chat, voice_command, task_complete, reminder_snooze, reminder_fired
            $table->text('metadata')->nullable(); // JSON
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['user_id', 'interaction_type']);
            $table->index('occurred_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_interactions');
    }
};

