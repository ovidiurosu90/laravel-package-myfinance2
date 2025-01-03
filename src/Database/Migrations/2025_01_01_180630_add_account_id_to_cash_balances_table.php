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
        $columnCheck = Schema::connection($connection)
            ->hasColumn($table, 'account_id');

        if ($columnCheck) {
            return;
        }

        Schema::connection($connection)->table($table, function (Blueprint $table)
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

        // Populate account_id
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
                RIGHT OUTER JOIN `$table` cb on a.name = cb.account
                    AND c.iso_code = cb.account_currency
                    AND cb.user_id = a.user_id
            ) as myjoin
            SET ucb.account_id = myjoin.account_id
            WHERE myjoin.cash_balance_id = ucb.id
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $connection = config('myfinance2.db_connection');
        $table = (new CashBalance())->getTable();

        $columnCheck = Schema::connection($connection)
            ->hasColumn($table, 'account_id');

        if (!$columnCheck) {
            return;
        }

        Schema::connection($connection)->table($table, function (Blueprint $table)
        {
            $table->dropForeign($table->getTable() . '_account_id');
            $table->dropColumn('account_id');
        });
    }
};

