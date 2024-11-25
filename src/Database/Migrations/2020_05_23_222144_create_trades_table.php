<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

use ovidiuro\myfinance2\App\Models\Trade;

class CreateTradesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $connection = config('myfinance2.db_connection');
        $table = (new Trade())->getTable();
        $tableCheck = Schema::connection($connection)->hasTable($table);

        if (!$tableCheck) {
            Schema::connection($connection)->create($table, function (Blueprint $table) {
                $table->increments('id')->unsigned();
                $table->timestamps();
                $table->timestamp('timestamp')->default(\DB::raw('CURRENT_TIMESTAMP'));
                $table->enum('action', array_keys(config('trades.actions')));
                $table->enum('account', array_keys(config('general.trade_accounts')));
                $table->enum('account_currency', array_keys(config('general.ledger_currencies')));
                $table->enum('trade_currency', array_keys(config('general.trade_currencies')));
                $table->decimal('exchange_rate', 8, 4);
                $table->string('symbol', 16);
                $table->integer('quantity')->unsigned();
                $table->decimal('unit_price', 10, 4);
                $table->decimal('fee', 8, 2);
                $table->string('description', 127)->nullable();
                $table->softDeletes();
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
        $table = (new Trade())->getTable();
        Schema::connection($connection)->dropIfExists($table);
    }
}

