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
        Schema::table('embeddings', function (Blueprint $table) {
            if (!Schema::hasColumn('embeddings','is_persistent')) {
                $table->boolean('is_persistent')->default(true)->after('snippet');
            }
            if (!Schema::hasColumn('embeddings','user_id')) {
                $table->unsignedBigInteger('user_id')->nullable()->after('id');
                $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users','embeddings_opt_out')) {
                $table->boolean('embeddings_opt_out')->default(false)->after('remember_token');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('embeddings', function (Blueprint $table) {
            if (Schema::hasColumn('embeddings','is_persistent')) $table->dropColumn('is_persistent');
            if (Schema::hasColumn('embeddings','user_id')) {
                $table->dropForeign(['user_id']);
                $table->dropColumn('user_id');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users','embeddings_opt_out')) $table->dropColumn('embeddings_opt_out');
        });
    }
};
