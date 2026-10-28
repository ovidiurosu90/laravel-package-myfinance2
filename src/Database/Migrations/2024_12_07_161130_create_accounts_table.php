<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

use ovidiuro\myfinance2\App\Models\Account;
use ovidiuro\myfinance2\App\Models\Currency;
use ovidiuro\myfinance2\App\Services\AppUser;

class CreateAccountsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $connection = config('myfinance2.db_connection');
        $table = (new Account())->getTable();
        $tableCheck = Schema::connection($connection)->hasTable($table);

        if ($tableCheck) {
            return;
        }

        Schema::connection($connection)->create($table, function (Blueprint $table)
        {
            $table->bigIncrements('id')->unsigned();
            $table->foreignId('user_id');
            AppUser::addUserIdKey($table);
            $table->foreignId('currency_id')->constrained(
                table:     (new Currency())->getTable(),
                indexName: $table->getTable() . '_currency_id'
            );
            $table->string('name', 64);
            $table->string('description', 512);
            $table->boolean('is_ledger_account');
            $table->boolean('is_trade_account');
            $table->boolean('is_dividend_account');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $connection = config('myfinance2.db_connection');
        $table = (new Account())->getTable();
        Schema::connection($connection)->dropIfExists($table);
    }
}

