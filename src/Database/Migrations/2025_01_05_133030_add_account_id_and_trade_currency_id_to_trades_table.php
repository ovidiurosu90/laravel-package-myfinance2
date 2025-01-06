<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

use ovidiuro\myfinance2\App\Models\Trade;
use ovidiuro\myfinance2\App\Models\Account;
use ovidiuro\myfinance2\App\Models\Currency;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $connection = config('myfinance2.db_connection');
        $table = (new Trade())->getTable();

        $accountIdColumnCheck = Schema::connection($connection)
            ->hasColumn($table, 'account_id');

        if (!$accountIdColumnCheck) {
            Schema::connection($connection)->table($table,
                function (Blueprint $table)
            {
                $accountsTable = (new Account())->getTable();

                $table->foreignId('account_id')
                    ->after('timestamp')
                    ->nullable()
                    ->constrained(
                        table: $accountsTable,
                        indexName: $table->getTable() . '_account_id'
                    );
            });
        }

        $tradeCurrencyIdColumnCheck = Schema::connection($connection)
            ->hasColumn($table, 'trade_currency_id');

        if (!$tradeCurrencyIdColumnCheck) {
            Schema::connection($connection)->table($table,
                function (Blueprint $table)
            {
                $currenciesTable = (new Currency())->getTable();

                $table->foreignId('trade_currency_id')
                    ->after('account_id')
                    ->nullable()
                    ->constrained(
                        table: $currenciesTable,
                        indexName: $table->getTable() . '_trade_currency_id'
                    );
            });
        }

        // Populate account_id and trade_currency_id
        $accountsTable = (new Account())->getTable();
        $currenciesTable = (new Currency())->getTable();
        \DB::connection($connection)->statement("
            UPDATE `$table` ud
            JOIN (
                SELECT a.id as account_id, a.name as account_name,
                    c.id as currency_id, c.iso_code as currency_iso_code,
                    dc.id as trade_currency_id,
                    dc.iso_code as trade_currency_iso_code,
                    a.user_id as user_id, d.id as trade_id
                FROM `$accountsTable` a
                LEFT OUTER JOIN `$currenciesTable` c ON a.currency_id = c.id
                    AND a.user_id = c.user_id
                RIGHT OUTER JOIN `$table` d ON a.name = d.account
                    AND c.iso_code = d.account_currency
                    AND d.user_id = a.user_id
                LEFT OUTER JOIN `$currenciesTable` dc
                    ON dc.iso_code = d.trade_currency
                    AND dc.user_id = d.user_id
            ) as myjoin
            SET ud.account_id = myjoin.account_id,
                ud.trade_currency_id = myjoin.trade_currency_id
            WHERE myjoin.trade_id = ud.id;
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $connection = config('myfinance2.db_connection');
        $table = (new Trade())->getTable();

        $accountIdColumnCheck = Schema::connection($connection)
            ->hasColumn($table, 'account_id');

        if ($accountIdColumnCheck) {
            Schema::connection($connection)->table($table,
                function (Blueprint $table)
            {
                $table->dropForeign($table->getTable() . '_account_id');
                $table->dropColumn('account_id');
            });
        }

        $tradeCurrencyIdColumnCheck = Schema::connection($connection)
            ->hasColumn($table, 'trade_currency_id');

        if ($tradeCurrencyIdColumnCheck) {
            Schema::connection($connection)->table($table,
                function (Blueprint $table)
            {
                $table->dropForeign($table->getTable() . '_trade_currency_id');
                $table->dropColumn('trade_currency_id');
            });
        }
    }
};

