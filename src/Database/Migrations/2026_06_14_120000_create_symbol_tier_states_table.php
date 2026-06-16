<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

use ovidiuro\myfinance2\App\Models\SymbolTierState;

return new class extends Migration
{
    public function up(): void
    {
        $connection = config('myfinance2.db_connection');
        $tableName  = (new SymbolTierState())->getTable();

        if (!Schema::connection($connection)->hasTable($tableName)) {
            Schema::connection($connection)->create($tableName, function (Blueprint $table)
            {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('user_id');
                $table->string('symbol', 20);
                $table->enum('tier', ['PLATINUM', 'GOLD', 'SILVER', 'BRONZE', 'RUST']);
                $table->timestamps();
                $table->softDeletes();

                // One settled tier per user per symbol; the unique key also backs the upsert.
                $table->unique(['user_id', 'symbol'], 'symbol_tier_states_user_symbol');
            });
        }
    }

    public function down(): void
    {
        $connection = config('myfinance2.db_connection');
        $tableName  = (new SymbolTierState())->getTable();

        Schema::connection($connection)->dropIfExists($tableName);
    }
};
