<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

use ovidiuro\myfinance2\App\Models\CashBalance;

class UpdateAccountInCashBalancesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $connection = config('cashbalances.database_connection');
        $table = config('cashbalances.database_table');
        $tableCheck = Schema::connection($connection)->hasTable($table);

        if (!$tableCheck) {
            return;
        }

        //NOTE Jan 2022: Laravel doesn't yet support changing ENUM columns
        \DB::statement("ALTER TABLE `$table` CHANGE `account` `account` enum('TD Ameritrade','DEGIRO','E-Trade','Binance','Bitvavo') COLLATE utf8mb4_unicode_ci NOT NULL;");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $connection = config('cashbalances.database_connection');
        $table = config('cashbalances.database_table');
        $tableCheck = Schema::connection($connection)->hasTable($table);

        if (!$tableCheck) {
            return;
        }

        //NOTE Before the ENUM values can be removed, we need to update
        //     to another ENUM values that will be retained
        $count = CashBalance::where('account', 'Binance')->count();
        if ($count > 0) {
            exit("Can't remove ENUM value Binance as we have $count rows with that value");
        }
        $count = CashBalance::where('account', 'Bitvavo')->count();
        if ($count > 0) {
            exit("Can't remove ENUM value Bitvavo as we have $count rows with that value");
        }

        \DB::statement("ALTER TABLE `$table` CHANGE `account` `account` enum('TD Ameritrade','DEGIRO','E-Trade') COLLATE utf8mb4_unicode_ci NOT NULL;");
    }
}

