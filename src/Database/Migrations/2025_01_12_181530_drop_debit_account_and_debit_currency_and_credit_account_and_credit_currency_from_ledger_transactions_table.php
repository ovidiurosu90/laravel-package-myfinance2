<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

use ovidiuro\myfinance2\App\Models\LedgerTransaction;
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
        $table = (new LedgerTransaction())->getTable();

        $result = \DB::connection($connection)->select("
            SELECT count(*) as total
            FROM `$table`
            WHERE debit_account_id is null OR credit_account_id is null;
        ");

        if (!isset($result[0]->total) || $result[0]->total > 0) {
            throw new \RuntimeException('Migration stopped due to invalid data!'
                . ' There are ' . $result[0]->total . ' rows where'
                . ' debit_account_id is null or credit_account_id is null!');
        }

        $debitAccountColumnCheck = Schema::connection($connection)
            ->hasColumn($table, 'debit_account');

        if ($debitAccountColumnCheck) {
            Schema::connection($connection)
                ->table($table, function (Blueprint $table)
            {
                $table->dropColumn('debit_account');
            });
        }

        $debitCurrencyColumnCheck = Schema::connection($connection)
            ->hasColumn($table, 'debit_currency');

        if ($debitCurrencyColumnCheck) {
            Schema::connection($connection)
                ->table($table, function (Blueprint $table)
            {
                $table->dropColumn('debit_currency');
            });
        }

        $creditAccountColumnCheck = Schema::connection($connection)
            ->hasColumn($table, 'credit_account');

        if ($creditAccountColumnCheck) {
            Schema::connection($connection)
                ->table($table, function (Blueprint $table)
            {
                $table->dropColumn('credit_account');
            });
        }

        $creditCurrencyColumnCheck = Schema::connection($connection)
            ->hasColumn($table, 'credit_currency');

        if ($creditCurrencyColumnCheck) {
            Schema::connection($connection)
                ->table($table, function (Blueprint $table)
            {
                $table->dropColumn('credit_currency');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $connection = config('myfinance2.db_connection');
        $table = (new LedgerTransaction())->getTable();

        $debitAccountColumnCheck = Schema::connection($connection)
            ->hasColumn($table, 'debit_account');

        if (!$debitAccountColumnCheck) {
            Schema::connection($connection)
                ->table($table, function (Blueprint $table)
            {
                $table->enum('debit_account',
                    array_keys(config('general.ledger_accounts')))
                    ->after('debit_account_id')
                    ->nullable();
            });
        }

        $debitCurrencyColumnCheck = Schema::connection($connection)
            ->hasColumn($table, 'debit_currency');

        if (!$debitCurrencyColumnCheck) {
            Schema::connection($connection)
                ->table($table, function (Blueprint $table)
            {
                $table->enum('debit_currency',
                    array_keys(config('general.ledger_currencies')))
                    ->after('debit_account')
                    ->nullable();
            });
        }

        $creditAccountColumnCheck = Schema::connection($connection)
            ->hasColumn($table, 'credit_account');

        if (!$creditAccountColumnCheck) {
            Schema::connection($connection)
                ->table($table, function (Blueprint $table)
            {
                $table->enum('credit_account',
                    array_keys(config('general.ledger_accounts')))
                    ->after('debit_currency')
                    ->nullable();
            });
        }

        $creditCurrencyColumnCheck = Schema::connection($connection)
            ->hasColumn($table, 'credit_currency');

        if (!$creditCurrencyColumnCheck) {
            Schema::connection($connection)
                ->table($table, function (Blueprint $table)
            {
                $table->enum('credit_currency',
                    array_keys(config('general.ledger_currencies')))
                    ->after('credit_account')
                    ->nullable();
            });
        }

        // Populate debit_account, debit_currency,
        //          credit_account and credit_currency
        $accountsTable = (new Account())->getTable();
        $currenciesTable = (new Currency())->getTable();
        \DB::connection($connection)->statement("
            UPDATE `$table` ult
            JOIN (
                SELECT da.id as debit_account_id, da.name as debit_account_name,
                    dc.id as debit_currency_id,
                    dc.iso_code as debit_currency_iso_code,
                    ca.id as credit_account_id, ca.name as credit_account_name,
                    cc.id as credit_currency_id,
                    cc.iso_code as credit_currency_iso_code,
                    da.user_id as user_id, lt.id as ledger_transaction_id
                FROM `$accountsTable` da
                LEFT OUTER JOIN `$currenciesTable` dc ON da.currency_id = dc.id
                    AND da.user_id = dc.user_id
                RIGHT OUTER JOIN `$table` lt ON da.id = lt.debit_account_id
                    AND lt.user_id = da.user_id
                LEFT OUTER JOIN `$accountsTable` ca ON ca.id = lt.credit_account_id
                    AND lt.user_id = ca.user_id
                LEFT OUTER JOIN `$currenciesTable` cc ON cc.id = ca.currency_id
                    AND lt.user_id = cc.user_id
            ) as myjoin
            SET ult.debit_account = myjoin.debit_account_name,
                ult.debit_currency = myjoin.debit_currency_iso_code,
                ult.credit_account = myjoin.credit_account_name,
                ult.credit_currency = myjoin.credit_currency_iso_code
            WHERE myjoin.ledger_transaction_id = ult.id;
        ");
    }
};

