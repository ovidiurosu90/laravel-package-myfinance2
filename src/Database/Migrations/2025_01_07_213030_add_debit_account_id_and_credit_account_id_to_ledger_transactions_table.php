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

        $debitAccountIdColumnCheck = Schema::connection($connection)
            ->hasColumn($table, 'debit_account_id');

        if (!$debitAccountIdColumnCheck) {
            Schema::connection($connection)->table($table,
                function (Blueprint $table)
            {
                $accountsTable = (new Account())->getTable();

                $table->foreignId('debit_account_id')
                    ->after('timestamp')
                    ->nullable()
                    ->constrained(
                        table: $accountsTable,
                        indexName: $table->getTable() . '_debit_account_id'
                    );
            });
        }

        $creditAccountIdColumnCheck = Schema::connection($connection)
            ->hasColumn($table, 'credit_account_id');

        if (!$creditAccountIdColumnCheck) {
            Schema::connection($connection)->table($table,
                function (Blueprint $table)
            {
                $accountsTable = (new Account())->getTable();

                $table->foreignId('credit_account_id')
                    ->after('debit_account_id')
                    ->nullable()
                    ->constrained(
                        table: $accountsTable,
                        indexName: $table->getTable() . '_credit_account_id'
                    );
            });
        }


        // Populate debit_account_id and credit_account_id
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
                RIGHT OUTER JOIN `$table` lt ON da.name = lt.debit_account
                    AND dc.iso_code = lt.debit_currency
                    AND lt.user_id = da.user_id

                LEFT OUTER JOIN `$currenciesTable` cc
                    ON lt.credit_currency = cc.iso_code
                    AND lt.user_id = cc.user_id
                LEFT OUTER JOIN `$accountsTable` ca
                    ON lt.credit_account = ca.name
                    AND ca.currency_id = cc.id
                    AND lt.user_id = ca.user_id
            ) as myjoin
            SET ult.debit_account_id = myjoin.debit_account_id,
                ult.credit_account_id = myjoin.credit_account_id
            WHERE myjoin.ledger_transaction_id = ult.id;
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $connection = config('myfinance2.db_connection');
        $table = (new LedgerTransaction())->getTable();

        $debitAccountIdColumnCheck = Schema::connection($connection)
            ->hasColumn($table, 'debit_account_id');

        if ($debitAccountIdColumnCheck) {
            Schema::connection($connection)->table($table,
                function (Blueprint $table)
            {
                $table->dropForeign($table->getTable() . '_debit_account_id');
                $table->dropColumn('debit_account_id');
            });
        }

        $creditAccountIdColumnCheck = Schema::connection($connection)
            ->hasColumn($table, 'credit_account_id');

        if ($creditAccountIdColumnCheck) {
            Schema::connection($connection)->table($table,
                function (Blueprint $table)
            {
                $table->dropForeign($table->getTable() . '_credit_account_id');
                $table->dropColumn('credit_account_id');
            });
        }
    }
};

