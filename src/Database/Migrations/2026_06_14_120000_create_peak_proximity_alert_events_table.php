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

        $table = (new PeakProximityAlertEvent())->getTable();
        if (!Schema::connection($connection)->hasTable($table)) {
            Schema::connection($connection)->create($table, function (Blueprint $t)
            {
                $t->bigIncrements('id');
                $t->unsignedBigInteger('user_id');
                $t->string('symbol', 16);
                $t->enum('classification', ['ACTIONABLE', 'INFO']);
                $t->enum('severity', ['HIGH', 'MEDIUM', 'LOW']);
                $t->enum('status', ['OPEN', 'DISMISSED'])->default('OPEN');
                $t->string('effective_tier', 24)->nullable();
                $t->string('head_action', 16)->nullable();
                $t->string('triggered_windows', 40);
                $t->string('meaningful_windows', 40)->nullable();
                $t->decimal('closest_proximity_pct', 8, 4)->nullable();
                $t->string('peak_dates', 191)->nullable();
                $t->decimal('current_price', 16, 6)->nullable();
                $t->timestamp('opened_at');
                $t->timestamp('last_seen_at')->nullable();
                $t->timestamp('last_emailed_at')->nullable();
                $t->unsignedTinyInteger('last_emailed_meaningful_count')->default(0);
                $t->string('last_emailed_windows', 40)->nullable();
                $t->unsignedInteger('email_count')->default(0);
                $t->timestamp('dismissed_at')->nullable();
                $t->timestamps();

                $t->index(['user_id', 'symbol', 'status'], 'peak_prox_events_user_symbol_status');
            });
        }
    }

    public function down(): void
    {
        $connection = config('myfinance2.db_connection');
        Schema::connection($connection)->dropIfExists(
            (new PeakProximityAlertEvent())->getTable()
        );
    }
};
