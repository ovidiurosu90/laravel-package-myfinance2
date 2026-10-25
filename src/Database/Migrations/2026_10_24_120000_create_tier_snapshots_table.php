<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

use ovidiuro\myfinance2\App\Models\Order;
use ovidiuro\myfinance2\App\Models\TierSnapshot;

/**
 * The tier reading saved with an order (and later a trade) at the moment of the decision, and the warnings it raised
 * (TierSnapshot). Rows never change: a changed decision inserts a new row and repoints the order. A live reading
 * cannot be rebuilt later, so the table is backed up, not regenerated.
 */
return new class extends Migration
{
    public function up(): void
    {
        $connection = config('myfinance2.db_connection');
        $tableName  = (new TierSnapshot())->getTable();

        if (!Schema::connection($connection)->hasTable($tableName)) {
            Schema::connection($connection)->create($tableName, function (Blueprint $table)
            {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('user_id');
                $table->string('symbol', 20);
                // The decision the warnings were judged for.
                $table->enum('action', ['BUY', 'SELL']);
                // Trading day the reading belongs to.
                $table->date('as_of_date');
                // When the reading was taken (kept when only the warnings are re-evaluated).
                $table->dateTime('taken_at');
                // When the warnings were evaluated.
                $table->dateTime('evaluated_at');
                // live: taken on the form; rebuilt: from the stored tier history; none: before that history.
                $table->enum('source', ['live', 'rebuilt', 'none']);
                // Lets the renderer read older shapes of 'reading' (TierReadingBuilder::SCHEMA_VERSION).
                $table->unsignedTinyInteger('schema_version');
                // The tier in use; for sorting and filtering.
                $table->enum('effective_tier', ['PLATINUM', 'GOLD', 'SILVER', 'BRONZE', 'RUST'])->nullable();
                $table->enum('verdict', ['ok', 'soft', 'against', 'none']);
                // The serious warnings were confirmed on save.
                $table->dateTime('acknowledged_at')->nullable();
                // TierReadingBuilder's shape, tooltips rendered.
                $table->json('reading');
                // [{code, level, label, text}] (OrderTierWarnings).
                $table->json('warnings');
                $table->timestamps();
                $table->softDeletes();

                $table->index(['user_id', 'symbol', 'as_of_date'], 'tier_snapshots_user_symbol_date');
                $table->index(['user_id', 'verdict'], 'tier_snapshots_user_verdict');
            });
        }

        $orders = (new Order())->getTable();
        if (!Schema::connection($connection)->hasColumn($orders, 'tier_snapshot_id')) {
            Schema::connection($connection)->table($orders, function (Blueprint $table) use ($tableName)
            {
                $table->unsignedBigInteger('tier_snapshot_id')->nullable()->after('trade_id');
                $table->foreign('tier_snapshot_id', 'orders_tier_snapshot_id_foreign')
                    ->references('id')->on($tableName);
            });
        }
    }

    public function down(): void
    {
        $connection = config('myfinance2.db_connection');
        $orders     = (new Order())->getTable();

        if (Schema::connection($connection)->hasColumn($orders, 'tier_snapshot_id')) {
            Schema::connection($connection)->table($orders, function (Blueprint $table)
            {
                $table->dropForeign('orders_tier_snapshot_id_foreign');
                $table->dropColumn('tier_snapshot_id');
            });
        }
        Schema::connection($connection)->dropIfExists((new TierSnapshot())->getTable());
    }
};
