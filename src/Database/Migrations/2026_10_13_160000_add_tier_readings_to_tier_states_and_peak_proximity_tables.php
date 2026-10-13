<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

use ovidiuro\myfinance2\App\Models\PeakProximityAlertEvent;
use ovidiuro\myfinance2\App\Models\PeakProximityNotification;
use ovidiuro\myfinance2\App\Models\SymbolTierState;

/**
 * The tier readings the hourly cron stores and the peak-proximity alerts carry:
 *   - symbol_tier_states: the settled tier (12-month median) and the day it was first seen, and the range-bound,
 *     weak-tier-in-profit and tier signal readings as of the last run (JSON, null when they do not hold), so the
 *     alerts page marks those symbols without running the categorization;
 *   - peak_proximity_alert_events: today's tier next to the effective one, and the signal that raised the alert;
 *   - peak_proximity_notifications: the signal of each email.
 * Every column is added only when missing, so a database that already has some of them is left as it is.
 */
return new class extends Migration
{
    private const TIERS = ['PLATINUM', 'GOLD', 'SILVER', 'BRONZE', 'RUST'];

    public function up(): void
    {
        $connection = config('myfinance2.db_connection');

        $this->_add($connection, (new SymbolTierState())->getTable(), [
            // Null while the history is too short to settle on.
            'settled_tier'  => fn (Blueprint $t) => $t->enum('settled_tier', self::TIERS)->nullable()->after('tier'),
            // First cron run that saw the current settled tier.
            'settled_since' => fn (Blueprint $t) => $t->date('settled_since')->nullable()->after('settled_tier'),
            // RangeBoundPattern::detect(), WeakTierProfit::detect(), TierSignalChip::detect().
            'range_bound'   => fn (Blueprint $t) => $t->json('range_bound')->nullable()->after('settled_since'),
            'take_profit'   => fn (Blueprint $t) => $t->json('take_profit')->nullable()->after('range_bound'),
            'tier_signal'   => fn (Blueprint $t) => $t->json('tier_signal')->nullable()->after('take_profit'),
        ]);

        $events = (new PeakProximityAlertEvent())->getTable();
        $this->_add($connection, $events, [
            'today_tier' => fn (Blueprint $t) => $t->string('today_tier', 24)->nullable()->after('effective_tier'),
        ]);
        foreach ([$events, (new PeakProximityNotification())->getTable()] as $tableName) {
            $this->_add($connection, $tableName, [
                // PeakProximityAlertEvent::SIGNAL_*; every row before this column was a near-peak alert.
                'signal' => fn (Blueprint $t) => $t->string('signal', 16)
                    ->default(PeakProximityAlertEvent::SIGNAL_NEAR_PEAK)->after('symbol'),
            ]);
        }
    }

    public function down(): void
    {
        $connection = config('myfinance2.db_connection');

        $this->_drop($connection, (new SymbolTierState())->getTable(),
            ['settled_tier', 'settled_since', 'range_bound', 'take_profit', 'tier_signal']);
        $this->_drop($connection, (new PeakProximityAlertEvent())->getTable(), ['today_tier', 'signal']);
        $this->_drop($connection, (new PeakProximityNotification())->getTable(), ['signal']);
    }

    /**
     * @param array<string, callable> $columns column => fn (Blueprint) adding it, in order; existing ones are skipped
     */
    private function _add(?string $connection, string $tableName, array $columns): void
    {
        foreach ($columns as $column => $add) {
            if (Schema::connection($connection)->hasColumn($tableName, $column)) {
                continue;
            }
            Schema::connection($connection)->table($tableName, fn (Blueprint $table) => $add($table));
        }
    }

    private function _drop(?string $connection, string $tableName, array $columns): void
    {
        $present = array_values(array_filter(
            $columns, fn ($column) => Schema::connection($connection)->hasColumn($tableName, $column)
        ));
        if (!empty($present)) {
            Schema::connection($connection)->table($tableName, fn (Blueprint $table) => $table->dropColumn($present));
        }
    }
};
