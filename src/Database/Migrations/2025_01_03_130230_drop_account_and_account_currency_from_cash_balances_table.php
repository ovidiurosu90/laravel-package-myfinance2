<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

use ovidiuro\myfinance2\App\Models\CashBalance;
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
        $table = (new CashBalance())->getTable();

        $result = \DB::connection($connection)->select("
            SELECT count(*) as total
            FROM `$table`
            WHERE account_id is null;
        ");

        if (!isset($result[0]->total) || $result[0]->total > 0) {
            throw new \RuntimeException('Migration stopped due to invalid data!'
                . ' There are ' . $result[0]->total
                . ' rows where account_id is null!');
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
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $connection = config('myfinance2.db_connection');
        $table = (new CashBalance())->getTable();

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

        // Populate account and account_currency
        $accountsTable = (new Account())->getTable();
        $currenciesTable = (new Currency())->getTable();
        \DB::connection($connection)->statement("
            UPDATE `$table` ucb
            JOIN (
                SELECT a.id as account_id, a.name as account_name,
                    c.id as currency_id, c.iso_code as currency_iso_code,
                    a.user_id as user_id, cb.id as cash_balance_id
                FROM `$accountsTable` a
                LEFT OUTER JOIN `$currenciesTable` c ON a.currency_id = c.id
                    AND a.user_id = c.user_id
                RIGHT OUTER JOIN `$table` cb on a.id = cb.account_id
                    AND cb.user_id = a.user_id
            ) as myjoin
            SET ucb.account = myjoin.account_name,
                ucb.account_currency = currency_iso_code
            WHERE myjoin.cash_balance_id = ucb.id
        ");
    }
};

