<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

use ovidiuro\myfinance2\App\Models\Currency;
use App\Models\User;

class CreateCurrenciesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $connection = config('myfinance2.db_connection');
        $table = (new Currency())->getTable();
        $tableCheck = Schema::connection($connection)->hasTable($table);

        if ($tableCheck) {
            return;
        }

        Schema::connection($connection)->create($table, function (Blueprint $table)
        {
            $usersDatabase = config('database.connections.' .
                config('database.default') . '.database');
            $usersTable = (new User())->getTable();

            $table->increments('id')->unsigned();
            $table->foreignId('user_id')->constrained(
                table:     $usersDatabase . '.' . $usersTable,
                indexName: $table->getTable() . '_user_id'
            );
            $table->string('iso_code', 4);
            $table->string('display_code', 16);
            $table->string('name', 64);
            $table->boolean('is_ledger_currency');
            $table->boolean('is_trade_currency');
            $table->boolean('is_dividend_currency');
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
        $table = (new Currency())->getTable();
        Schema::connection($connection)->dropIfExists($table);
    }
}

