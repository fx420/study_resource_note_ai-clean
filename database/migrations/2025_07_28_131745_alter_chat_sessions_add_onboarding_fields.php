<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                  ->constrained()
                  ->cascadeOnDelete();

            $table->string('title');
            $table->string('education_level');
            $table->string('course');
            $table->string('topic');

            $table->string('prior_knowledge')->nullable();
            $table->string('learning_goal')->nullable();
            $table->tinyInteger('difficulty');
            $table->integer('examples_count')->default(0);
            $table->enum('content_format', ['text','bullet','table'])
                  ->default('text');
            $table->enum('mode', ['normal','detailed'])
                  ->default('normal');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_sessions');
    }
};
