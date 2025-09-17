<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateQuestionAttemptsTable extends Migration
{
    public function up()
    {
        Schema::create('question_attempts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('question_id')->nullable();
            $table->unsignedBigInteger('session_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->boolean('is_correct')->nullable();
            $table->json('answer_payload')->nullable();
            $table->integer('duration_ms')->nullable();
            $table->timestamps();

            $table->index('question_id');
            $table->index('session_id');
            $table->index('user_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('question_attempts');
    }
}
