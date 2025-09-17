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
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'gender')) {
                $table->string('gender')->nullable();
            }
            if (! Schema::hasColumn('users', 'dob')) {
                $table->date('dob')->nullable();
            }
            if (! Schema::hasColumn('users', 'age')) {
                $table->integer('age')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'gender')) $table->dropColumn('gender');
            if (Schema::hasColumn('users', 'dob')) $table->dropColumn('dob');
            if (Schema::hasColumn('users', 'age')) $table->dropColumn('age');
        });
    }
};
