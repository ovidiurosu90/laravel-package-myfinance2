<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

use ovidiuro\myfinance2\App\Models\StatToday;

class CreateStatsTodayTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $connection = config('myfinance2.db_connection');
        $table = (new StatToday())->getTable();
        $tableCheck = Schema::connection($connection)->hasTable($table);

        if ($tableCheck) {
            return;
        }

        Schema::connection($connection)->create($table, function (Blueprint $table)
        {
            $table->timestamp('timestamp')->default(\DB::raw('CURRENT_TIMESTAMP'));
            $table->string('symbol', 16);
            $table->decimal('unit_price', 10, 4);
            $table->string('currency_iso_code', 4);
            $table->timestamps();
            $table->softDeletes();
            $table->primary(['timestamp', 'symbol']);
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
        $table = (new StatToday())->getTable();
        Schema::connection($connection)->dropIfExists($table);
    }
}

