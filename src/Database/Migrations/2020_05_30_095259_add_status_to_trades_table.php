<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddStatusToTradesTable extends Migration
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
        $columnCheck = Schema::connection($connection)->hasColumn($table, 'status');

        if (!$columnCheck) {
            Schema::connection($connection)->table($table, function (Blueprint $table) {
                $table->enum('status', array_keys(config('trades.statuses')))->default('OPEN');
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

        Schema::connection($connection)->table($table, function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
}

