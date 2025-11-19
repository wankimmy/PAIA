<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_memories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('category'); // personal_fact, preference, habit, goal, boundary
            $table->string('key');
            $table->text('value'); // short sentence, max ~512 chars
            $table->integer('importance')->default(3); // 1 = low, 3 = medium, 5 = high
            $table->string('source')->default('ai_inferred'); // user_input, ai_inferred, system
            $table->timestamps();

            $table->index(['user_id', 'category']);
            $table->index(['user_id', 'importance']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_memories');
    }
};

