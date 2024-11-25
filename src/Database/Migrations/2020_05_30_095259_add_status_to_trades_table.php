<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

use ovidiuro\myfinance2\App\Models\Trade;

class AddStatusToTradesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $connection = config('myfinance2.db_connection');
        $table = (new Trade())->getTable();
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
        $connection = config('myfinance2.db_connection');
        $table = (new Trade())->getTable();

        Schema::connection($connection)->table($table, function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
}

