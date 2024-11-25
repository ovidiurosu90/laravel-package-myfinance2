<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

use ovidiuro\myfinance2\App\Models\WatchlistSymbol;

class CreateWatchlistSymbolsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $connection = config('myfinance2.db_connection');
        $table = (new WatchlistSymbol())->getTable();
        $tableCheck = Schema::connection($connection)->hasTable($table);

        if (!$tableCheck) {
            Schema::connection($connection)->create($table, function (Blueprint $table) {
                $table->increments('id')->unsigned();
                $table->timestamps();
                $table->timestamp('timestamp')->default(\DB::raw('CURRENT_TIMESTAMP'));
                $table->string('symbol', 16);
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
        $table = (new WatchlistSymbol())->getTable();
        Schema::connection($connection)->dropIfExists($table);
    }
}

