<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

use ovidiuro\myfinance2\App\Models\StockSplit;

return new class extends Migration
{
    public function up(): void
    {
        $connection = config('myfinance2.db_connection');
        $table = (new StockSplit())->getTable();

        if (!Schema::connection($connection)->hasTable($table)) {
            Schema::connection($connection)->create($table, function (Blueprint $table)
            {
                $table->increments('id');
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('symbol', 16);
                $table->date('split_date');
                $table->unsignedTinyInteger('ratio_numerator');
                $table->unsignedTinyInteger('ratio_denominator')->default(1);
                $table->text('notes')->nullable();
                $table->unsignedInteger('trades_updated')->default(0);
                $table->unsignedInteger('alerts_adjusted')->default(0);
                $table->timestamps();
                $table->softDeletes();

                $table->index(['symbol', 'split_date'], 'stock_splits_symbol_date');
                $table->index(['user_id', 'symbol', 'split_date'], 'stock_splits_user_symbol_date');
            });
        }
    }

    public function down(): void
    {
        $connection = config('myfinance2.db_connection');
        Schema::connection($connection)->dropIfExists(
            (new StockSplit())->getTable()
        );
    }
};
