<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

use ovidiuro\myfinance2\App\Models\PriceAlert;
use ovidiuro\myfinance2\App\Models\PriceAlertNotification;

/**
 * Relative price-alert targets: a target defined as an offset from a rolling closing high or low
 * ("5% below the 52W high") instead of a fixed price. Existing rows default to FIXED, so their
 * behavior does not change. The engine re-resolves RELATIVE targets and writes them back into
 * target_price, which every existing consumer keeps reading.
 */
return new class extends Migration
{
    private const ALERT_COLUMNS = [
        'target_mode', 'reference_type', 'reference_window', 'offset_pct',
        'reference_price', 'reference_date', 'reference_resolved_at',
    ];

    public function up(): void
    {
        $connection = config('myfinance2.db_connection');
        $schema     = Schema::connection($connection);

        $alertsTable = (new PriceAlert())->getTable();
        if ($schema->hasTable($alertsTable) && !$schema->hasColumn($alertsTable, 'target_mode')) {
            $schema->table($alertsTable, function (Blueprint $t)
            {
                $t->enum('target_mode', ['FIXED', 'RELATIVE'])->default('FIXED')->after('target_price');
                $t->enum('reference_type', ['HIGH', 'LOW'])->nullable()->after('target_mode');
                $t->enum('reference_window', ['3m', '6m', '1y', '2y'])->nullable()->after('reference_type');
                // Magnitude only; the direction comes from reference_type (below a HIGH, above a LOW).
                $t->decimal('offset_pct', 7, 3)->unsigned()->nullable()->after('reference_window');
                // Last resolved high / low in the symbol's native currency, with its date.
                $t->decimal('reference_price', 16, 6)->nullable()->after('offset_pct');
                $t->date('reference_date')->nullable()->after('reference_price');
                $t->timestamp('reference_resolved_at')->nullable()->after('reference_date');
            });
        }

        $notifTable = (new PriceAlertNotification())->getTable();
        if ($schema->hasTable($notifTable) && !$schema->hasColumn($notifTable, 'target_label')) {
            $schema->table($notifTable, function (Blueprint $t)
            {
                // Snapshot of the relative target at fire time, so the history still explains
                // the fire after the alert is edited, switched to FIXED or deleted.
                $t->string('target_label', 64)->nullable()->after('target_price');
            });
        }
    }

    public function down(): void
    {
        $connection = config('myfinance2.db_connection');
        $schema     = Schema::connection($connection);

        $notifTable = (new PriceAlertNotification())->getTable();
        if ($schema->hasColumn($notifTable, 'target_label')) {
            $schema->table($notifTable, function (Blueprint $t)
            {
                $t->dropColumn('target_label');
            });
        }

        $alertsTable = (new PriceAlert())->getTable();
        if ($schema->hasColumn($alertsTable, 'target_mode')) {
            $schema->table($alertsTable, function (Blueprint $t)
            {
                $t->dropColumn(self::ALERT_COLUMNS);
            });
        }
    }
};
