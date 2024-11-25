<?php

// php artisan make:migration update_description_in_cash_balances_table
// vim database/migrations/2021_12_12_203842_update_description_in_cash_balances_table.php
// php artisan migrate
// vim app/Http/Requests/StoreCashBalance.php
// vim resources/views/cashbalances/forms/partials/description-input.blade.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

use ovidiuro\myfinance2\App\Models\CashBalance;

class UpdateDescriptionInCashBalancesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $connection = config('myfinance2.db_connection');
        $table = (new CashBalance())->getTable();
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
        $table = (new CashBalance())->getTable();
        $tableCheck = Schema::connection($connection)->hasTable($table);

        if ($tableCheck) {
            Schema::connection($connection)->table($table, function (Blueprint $table) {
                $table->string('description', 127)->nullable()->change();
            });
        }
    }
}

