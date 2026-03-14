<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

use ovidiuro\myfinance2\App\Models\Order;

return new class extends Migration
{
    public function up(): void
    {
        $connection = config('myfinance2.db_connection');
        $table = (new Order())->getTable();
        $tableCheck = Schema::connection($connection)->hasTable($table);

        if (!$tableCheck) {
            Schema::connection($connection)->create($table, function (Blueprint $table)
            {
                $table->increments('id')->unsigned();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->unsignedBigInteger('account_id')->nullable();
                $table->string('symbol', 16);
                $table->enum('action', ['BUY', 'SELL']);
                $table->enum('status', ['DRAFT', 'PLACED', 'FILLED', 'EXPIRED', 'CANCELLED'])
                    ->default('DRAFT');
                $table->decimal('quantity', 16, 8)->nullable()->unsigned();
                $table->decimal('limit_price', 10, 4)->nullable();
                $table->unsignedBigInteger('trade_currency_id')->nullable();
                $table->decimal('exchange_rate', 8, 4)->nullable();
                $table->unsignedInteger('trade_id')->nullable();
                $table->timestamp('placed_at')->nullable();
                $table->timestamp('filled_at')->nullable();
                $table->timestamp('expired_at')->nullable();
                $table->string('description', 512)->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['symbol', 'status'], 'orders_symbol_status');
            });
        }
    }

    public function down(): void
    {
        $connection = config('myfinance2.db_connection');
        $table = (new Order())->getTable();
        Schema::connection($connection)->dropIfExists($table);
    }
};
