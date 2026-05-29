<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

use ovidiuro\myfinance2\App\Models\SymbolTierOverride;

return new class extends Migration
{
    public function up(): void
    {
        $connection = config('myfinance2.db_connection');
        $tableName  = (new SymbolTierOverride())->getTable();

        if (!Schema::connection($connection)->hasTable($tableName)) {
            Schema::connection($connection)->create($tableName, function (Blueprint $table)
            {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('user_id');
                $table->string('symbol', 20);
                $table->enum('tier_override', ['PLATINUM', 'GOLD', 'SILVER', 'BRONZE', 'RUST']);
                $table->string('note', 500)->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['user_id', 'symbol'], 'symbol_tier_overrides_user_symbol');
            });
        }
    }

    public function down(): void
    {
        $connection = config('myfinance2.db_connection');
        $tableName  = (new SymbolTierOverride())->getTable();

        Schema::connection($connection)->dropIfExists($tableName);
    }
};
