<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

use ovidiuro\myfinance2\App\Models\LedgerTransaction;

class CreateLedgerTransactionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $connection = config('myfinance2.db_connection');
        $table = (new LedgerTransaction())->getTable();
        $tableCheck = Schema::connection($connection)->hasTable($table);

        if (!$tableCheck) {
            Schema::connection($connection)->create($table, function (Blueprint $table) {
                $table->increments('id')->unsigned();
                $table->timestamps();
                $table->timestamp('timestamp')->default(\DB::raw('CURRENT_TIMESTAMP'));
                $table->enum('type', array_keys(config('ledger.transaction_types')));
                $table->enum('debit_account', array_keys(config('general.ledger_accounts')));
                $table->enum('credit_account', array_keys(config('general.ledger_accounts')));
                $table->enum('debit_currency', array_keys(config('general.ledger_currencies')));
                $table->enum('credit_currency', array_keys(config('general.ledger_currencies')));
                $table->decimal('exchange_rate', 8, 4);
                $table->decimal('amount', 8, 2);
                $table->decimal('fee', 8, 2);
                $table->string('description', 127);

                $table->integer('parent_id')->nullable()->unsigned()->index();
                $table->foreign('parent_id')->nullable()->references('id')->on($table)->onDelete('cascade');
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
        $table = (new LedgerTransaction())->getTable();
        Schema::connection($connection)->dropIfExists($table);
    }
}

