<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

use ovidiuro\myfinance2\App\Models\TierSnapshot;
use ovidiuro\myfinance2\App\Models\Trade;

/**
 * The tier reading of the decision behind a trade (TierSnapshot): the order's when the trade fills an order (both
 * point to the same row), else the trade's own (live when recorded the same day, rebuilt from the stored tier history
 * for a past date).
 */
return new class extends Migration
{
    public function up(): void
    {
        $connection = config('myfinance2.db_connection');
        $trades     = (new Trade())->getTable();

        if (!Schema::connection($connection)->hasColumn($trades, 'tier_snapshot_id')) {
            Schema::connection($connection)->table($trades, function (Blueprint $table)
            {
                $table->unsignedBigInteger('tier_snapshot_id')->nullable()->after('is_transfer');
                $table->foreign('tier_snapshot_id', 'trades_tier_snapshot_id_foreign')
                    ->references('id')->on((new TierSnapshot())->getTable());
            });
        }
    }

    public function down(): void
    {
        $connection = config('myfinance2.db_connection');
        $trades     = (new Trade())->getTable();

        if (Schema::connection($connection)->hasColumn($trades, 'tier_snapshot_id')) {
            Schema::connection($connection)->table($trades, function (Blueprint $table)
            {
                $table->dropForeign('trades_tier_snapshot_id_foreign');
                $table->dropColumn('tier_snapshot_id');
            });
        }
    }
};
