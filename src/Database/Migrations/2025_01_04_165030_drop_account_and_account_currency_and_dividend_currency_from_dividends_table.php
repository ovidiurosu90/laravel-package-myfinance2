<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

use ovidiuro\myfinance2\App\Models\Dividend;
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
        $table = (new Dividend())->getTable();

        $result = \DB::connection($connection)->select("
            SELECT count(*) as total
            FROM `$table`
            WHERE account_id is null OR dividend_currency_id is null;
        ");

        if (!isset($result[0]->total) || $result[0]->total > 0) {
            throw new \RuntimeException('Migration stopped due to invalid data!'
                . ' There are ' . $result[0]->total . ' rows where'
                . ' account_id is null or dividend_currency_id is null!');
        }

        $accountColumnCheck = Schema::connection($connection)
            ->hasColumn($table, 'account');

        if ($accountColumnCheck) {
            Schema::connection($connection)
                ->table($table, function (Blueprint $table)
            {
                $table->dropColumn('account');
            });
        }

        $accountCurrencyColumnCheck = Schema::connection($connection)
            ->hasColumn($table, 'account_currency');

        if ($accountCurrencyColumnCheck) {
            Schema::connection($connection)
                ->table($table, function (Blueprint $table)
            {
                $table->dropColumn('account_currency');
            });
        }

        $dividendCurrencyColumnCheck = Schema::connection($connection)
            ->hasColumn($table, 'dividend_currency');

        if ($dividendCurrencyColumnCheck) {
            Schema::connection($connection)
                ->table($table, function (Blueprint $table)
            {
                $table->dropColumn('dividend_currency');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $connection = config('myfinance2.db_connection');
        $table = (new Dividend())->getTable();

        $accountColumnCheck = Schema::connection($connection)
            ->hasColumn($table, 'account');

        if (!$accountColumnCheck) {
            Schema::connection($connection)
                ->table($table, function (Blueprint $table)
            {
                $table->enum('account',
                    array_keys(config('general.trade_accounts')))
                    ->after('account_id')
                    ->nullable();
            });
        }

        $accountCurrencyColumnCheck = Schema::connection($connection)
            ->hasColumn($table, 'account_currency');

        if (!$accountCurrencyColumnCheck) {
            Schema::connection($connection)
                ->table($table, function (Blueprint $table)
            {
                $table->enum('account_currency',
                    array_keys(config('general.ledger_currencies')))
                    ->after('account')
                    ->nullable();
            });
        }

        $dividendCurrencyColumnCheck = Schema::connection($connection)
            ->hasColumn($table, 'dividend_currency');

        if (!$dividendCurrencyColumnCheck) {
            Schema::connection($connection)
                ->table($table, function (Blueprint $table)
            {
                $table->enum('dividend_currency',
                    array_keys(config('general.dividend_currencies')))
                    ->after('account_currency')
                    ->nullable();
            });
        }

        // Populate account and account_currency
        $accountsTable = (new Account())->getTable();
        $currenciesTable = (new Currency())->getTable();
        \DB::connection($connection)->statement("
            UPDATE `$table` ud
            JOIN (
                SELECT a.id as account_id, a.name as account_name,
                    c.id as currency_id, c.iso_code as currency_iso_code,
                    dc.id as dividend_currency_id,
                    dc.iso_code as dividend_currency_iso_code,
                    a.user_id as user_id, d.id as dividend_id
                FROM `$accountsTable` a
                LEFT OUTER JOIN `$currenciesTable` c ON a.currency_id = c.id
                    AND a.user_id = c.user_id
                RIGHT OUTER JOIN `$table` d ON a.id = d.account_id
                    AND d.user_id = a.user_id
                LEFT OUTER JOIN `$currenciesTable` dc
                    ON dc.id = d.dividend_currency_id
                    AND dc.user_id = d.user_id
            ) as myjoin
            SET ud.account = myjoin.account_name,
                ud.account_currency = myjoin.currency_iso_code,
                ud.dividend_currency = myjoin.dividend_currency_iso_code
            WHERE myjoin.dividend_id = ud.id;
        ");
    }
};

