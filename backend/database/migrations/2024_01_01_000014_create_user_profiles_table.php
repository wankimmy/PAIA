<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->onDelete('cascade');
            $table->string('full_name')->nullable();
            $table->string('nickname')->nullable();
            $table->string('pronouns')->nullable();
            $table->text('bio')->nullable();
            $table->string('timezone')->nullable();
            $table->string('primary_language')->nullable();
            $table->string('preferred_tone')->nullable(); // friendly, professional, casual
            $table->string('preferred_answer_length')->nullable(); // short, normal, detailed
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_profiles');
    }
};

