<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

use ovidiuro\myfinance2\App\Models\Dividend;

class CreateDividendsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $connection = config('myfinance2.db_connection');
        $table = (new Dividend())->getTable();
        $tableCheck = Schema::connection($connection)->hasTable($table);

        if (!$tableCheck) {
            Schema::connection($connection)->create($table, function (Blueprint $table) {
                $table->increments('id')->unsigned();
                $table->timestamps();
                $table->timestamp('timestamp')->default(\DB::raw('CURRENT_TIMESTAMP'));
                $table->enum('account', array_keys(config('general.dividend_accounts')));
                $table->enum('account_currency', array_keys(config('general.ledger_currencies')));
                $table->enum('dividend_currency', array_keys(config('general.dividend_currencies')));
                $table->decimal('exchange_rate', 8, 4);
                $table->string('symbol', 16);
                $table->decimal('amount', 10, 4);
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
        $table = (new Dividend())->getTable();
        Schema::connection($connection)->dropIfExists($table);
    }
}

