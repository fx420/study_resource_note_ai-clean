<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('interaction_events', function (Blueprint $table) {
            $table->longText('metadata')->nullable()->change();
            $table->string('event_type', 150)->change(); 
            $table->timestamp('created_at')->nullable()->change();
        });
    }

    public function down()
    {
        Schema::table('interaction_events', function (Blueprint $table) {
            $table->string('metadata', 255)->nullable()->change();
            $table->string('event_type', 100)->change();
        });
    }
};
