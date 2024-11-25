<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

use ovidiuro\myfinance2\App\Models\Trade;

class UpdateDescriptionInTradesTable extends Migration
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
        $tableCheck = Schema::connection($connection)->hasTable($table);

        if ($tableCheck) {
            Schema::connection($connection)->table($table, function (Blueprint $table) {
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
        $connection = config('myfinance2.db_connection');
        $table = (new Trade())->getTable();
        $tableCheck = Schema::connection($connection)->hasTable($table);

        if ($tableCheck) {
            Schema::connection($connection)->table($table, function (Blueprint $table) {
                $table->string('description', 127)->nullable()->change();
            });
        }
    }
}

