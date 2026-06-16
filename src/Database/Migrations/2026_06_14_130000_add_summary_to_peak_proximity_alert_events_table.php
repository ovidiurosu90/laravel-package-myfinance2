<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

use ovidiuro\myfinance2\App\Models\PeakProximityAlertEvent;

return new class extends Migration
{
    public function up(): void
    {
        $connection = config('myfinance2.db_connection');
        $table      = (new PeakProximityAlertEvent())->getTable();

        if (Schema::connection($connection)->hasTable($table)
            && !Schema::connection($connection)->hasColumn($table, 'summary')) {
            Schema::connection($connection)->table($table, function (Blueprint $t)
            {
                // JSON snapshot of the email's "Summary" block (currency, price, per-window peak
                // table, unrealized gain), captured at engine time so the inbox can render it
                // without making live quote calls.
                $t->text('summary')->nullable()->after('peak_dates');
            });
        }
    }

    public function down(): void
    {
        $connection = config('myfinance2.db_connection');
        $table      = (new PeakProximityAlertEvent())->getTable();

        if (Schema::connection($connection)->hasColumn($table, 'summary')) {
            Schema::connection($connection)->table($table, function (Blueprint $t)
            {
                $t->dropColumn('summary');
            });
        }
    }
};
