<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

use ovidiuro\myfinance2\App\Models\SymbolTierHistory;

/**
 * The daily tier history (TierHistoryRecorder: backfill and the hourly cron): one row per user, symbol and trading
 * day with the day's deciding basis and value, both candidate measures (1Y market return, your own return), the
 * open holding period's return and the raw tier. Read by the settled tier, the Tier History ribbon and chart, and
 * the signal backtest.
 */
return new class extends Migration
{
    public function up(): void
    {
        $connection = config('myfinance2.db_connection');
        $tableName  = (new SymbolTierHistory())->getTable();

        if (!Schema::connection($connection)->hasTable($tableName)) {
            Schema::connection($connection)->create($tableName, function (Blueprint $table)
            {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('user_id');
                $table->string('symbol', 20);
                // The weekday this row describes.
                $table->date('date');
                // TierDecision::BASIS_* that decided the tier on that day.
                $table->string('basis', 24);
                $table->decimal('basis_value', 12, 4)->nullable();
                // The symbol's 1Y market return that day; null when missing or below the trusted floor.
                $table->decimal('market_value', 12, 4)->nullable();
                // Your own overall return that day (annualized once held a year, raw from 3 months);
                // null when never held or held under 3 months.
                $table->decimal('own_value', 12, 4)->nullable();
                // Return of the open holding period so far (realized + unrealized + dividends over invested).
                $table->decimal('holding_value', 12, 4)->nullable();
                // First day of that holding period.
                $table->date('holding_since')->nullable();
                // Raw tier: no hysteresis, no manual override.
                $table->enum('tier', ['PLATINUM', 'GOLD', 'SILVER', 'BRONZE', 'RUST'])->nullable();
                $table->boolean('is_owned')->default(false);
                $table->enum('source', ['backfill', 'cron']);
                $table->timestamps();
                $table->softDeletes();

                // One row per user, symbol and day; the unique key also backs the upsert.
                $table->unique(['user_id', 'symbol', 'date'], 'symbol_tier_history_user_symbol_date');
                $table->index(['user_id', 'date'], 'symbol_tier_history_user_date');
            });
        }
    }

    public function down(): void
    {
        $connection = config('myfinance2.db_connection');
        $tableName  = (new SymbolTierHistory())->getTable();

        Schema::connection($connection)->dropIfExists($tableName);
    }
};
