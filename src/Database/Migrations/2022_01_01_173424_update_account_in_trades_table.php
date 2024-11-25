<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

use ovidiuro\myfinance2\App\Models\Trade;

class UpdateAccountInTradesTable extends Migration
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

        if (!$tableCheck) {
            return;
        }

        //NOTE Jan 2022: Laravel doesn't yet support changing ENUM columns
        \DB::connection($connection)->statement("ALTER TABLE `$table` CHANGE `account` `account` enum('TD Ameritrade','DEGIRO','E-Trade','Binance','Bitvavo') COLLATE utf8mb4_unicode_ci NOT NULL;");
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

        if (!$tableCheck) {
            return;
        }

        //NOTE Before the ENUM values can be removed, we need to update
        //     to another ENUM values that will be retained
        $count = Trade::where('account', 'Binance')->count();
        if ($count > 0) {
            exit("Can't remove ENUM value Binance as we have $count rows with that value");
        }
        $count = Trade::where('account', 'Bitvavo')->count();
        if ($count > 0) {
            exit("Can't remove ENUM value Bitvavo as we have $count rows with that value");
        }

        \DB::connection($connection)->statement("ALTER TABLE `$table` CHANGE `account` `account` enum('TD Ameritrade','DEGIRO','E-Trade') COLLATE utf8mb4_unicode_ci NOT NULL;");
    }
}

