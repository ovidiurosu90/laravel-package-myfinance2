<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

use ovidiuro\myfinance2\App\Models\LedgerTransaction;

class UpdateDebitCreditAccountsInLedgerTransactionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $connection = config('ledger.connection');
        $table = config('ledger.ledgerTransactionsTable');
        $tableCheck = Schema::connection($connection)->hasTable($table);

        if (!$tableCheck) {
            return;
        }

        //NOTE Jan 2022: Laravel doesn't yet support changing ENUM columns
        \DB::statement("ALTER TABLE `$table` CHANGE `debit_account` `debit_account` enum('ING','ABN AMRO','TD Ameritrade','DEGIRO','Binance','Bitvavo') COLLATE utf8mb4_unicode_ci NOT NULL;");
        \DB::statement("ALTER TABLE `$table` CHANGE `credit_account` `credit_account` enum('ING','ABN AMRO','TD Ameritrade','DEGIRO','Binance','Bitvavo') COLLATE utf8mb4_unicode_ci NOT NULL;");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $connection = config('ledger.connection');
        $table = config('ledger.ledgerTransactionsTable');
        $tableCheck = Schema::connection($connection)->hasTable($table);

        if (!$tableCheck) {
            return;
        }

        //NOTE Before the ENUM values can be removed, we need to update
        //     to another ENUM values that will be retained
        $count = LedgerTransaction::where('debit_account', 'Binance')->count();
        if ($count > 0) {
            exit("Can't remove ENUM value Binance as we have $count rows with that value");
        }
        $count = LedgerTransaction::where('debit_account', 'Bitvavo')->count();
        if ($count > 0) {
            exit("Can't remove ENUM value Bitvavo as we have $count rows with that value");
        }

        $count = LedgerTransaction::where('credit_account', 'Binance')->count();
        if ($count > 0) {
            exit("Can't remove ENUM value Binance as we have $count rows with that value");
        }
        $count = LedgerTransaction::where('credit_account', 'Bitvavo')->count();
        if ($count > 0) {
            exit("Can't remove ENUM value Bitvavo as we have $count rows with that value");
        }

        \DB::statement("ALTER TABLE `$table` CHANGE `debit_account` `debit_account` enum('ING','ABN AMRO','TD Ameritrade','DEGIRO') COLLATE utf8mb4_unicode_ci NOT NULL;");
        \DB::statement("ALTER TABLE `$table` CHANGE `credit_account` `credit_account` enum('ING','ABN AMRO','TD Ameritrade','DEGIRO') COLLATE utf8mb4_unicode_ci NOT NULL;");
    }
}

