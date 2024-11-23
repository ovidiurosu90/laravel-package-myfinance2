<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCashBalancesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $connection = config('cashbalances.database_connection');
        $table = config('cashbalances.database_table');
        $tableCheck = Schema::connection($connection)->hasTable($table);

        if (!$tableCheck) {
            Schema::connection($connection)->create($table, function (Blueprint $table) {
                $table->increments('id')->unsigned();
                $table->timestamps();
                $table->timestamp('timestamp')->default(\DB::raw('CURRENT_TIMESTAMP'));
                $table->enum('account', array_keys(config('general.trade_accounts')));
                $table->enum('account_currency',
                    array_keys(config('general.ledger_currencies')));
                $table->decimal('amount', 10, 4);
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
        $connection = config('cashbalances.database_connection');
        $table = config('cashbalances.database_table');
        Schema::connection($connection)->dropIfExists($table);
    }
}

