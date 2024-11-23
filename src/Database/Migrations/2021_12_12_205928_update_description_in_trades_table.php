<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateDescriptionInTradesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $connection = config('trades.database_connection');
        $table = config('trades.database_table');
        $tableCheck = Schema::connection($connection)->hasTable($table);

        if ($tableCheck) {
            Schema::table($table, function (Blueprint $table) {
                $table->string('description', 512)->nullable()->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $connection = config('trades.database_connection');
        $table = config('trades.database_table');
        $tableCheck = Schema::connection($connection)->hasTable($table);

        if ($tableCheck) {
            Schema::table($table, function (Blueprint $table) {
                $table->string('description', 127)->nullable()->change();
            });
        }
    }
}

