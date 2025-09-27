<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInteractionEventsTable extends Migration
{
    public function up()
    {
        Schema::create('interaction_events', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->unsignedBigInteger('session_id')->nullable()->index();
            $table->unsignedBigInteger('message_id')->nullable()->index();
            $table->string('event_type', 100)->index();
            $table->longText('metadata')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down()
    {
        Schema::dropIfExists('interaction_events');
    }
}
