<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

use ovidiuro\myfinance2\App\Models\PortfolioPeakNotification;

return new class extends Migration
{
    public function up(): void
    {
        $connection = config('myfinance2.db_connection');

        $table = (new PortfolioPeakNotification())->getTable();
        if (!Schema::connection($connection)->hasTable($table)) {
            Schema::connection($connection)->create($table, function (Blueprint $t)
            {
                $t->bigIncrements('id');
                $t->unsignedBigInteger('user_id');
                // Snapshot of what fired, for the history page and the reminder/once-per-day guards.
                $t->string('triggered_metrics')->nullable();   // e.g. "change_eur,change_pct"
                $t->string('triggered_windows')->nullable();   // e.g. "1y,2y"
                $t->decimal('closest_proximity_pct', 8, 4)->nullable();
                $t->decimal('change_eur_current', 14, 2)->nullable();
                $t->decimal('change_pct_current', 8, 4)->nullable();
                $t->decimal('vusa_change_pct', 8, 4)->nullable();  // context only
                $t->timestamp('sent_at')->nullable();
                $t->enum('status', ['SENT', 'FAILED'])->default('SENT');
                $t->text('error_message')->nullable();
                $t->timestamps();

                $t->index(['user_id', 'sent_at'], 'portfolio_peak_notifs_user_sent');
            });
        }
    }

    public function down(): void
    {
        $connection = config('myfinance2.db_connection');
        Schema::connection($connection)->dropIfExists(
            (new PortfolioPeakNotification())->getTable()
        );
    }
};
