<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

use ovidiuro\myfinance2\App\Models\PeakProximityNotification;

return new class extends Migration
{
    public function up(): void
    {
        $connection = config('myfinance2.db_connection');

        $table = (new PeakProximityNotification())->getTable();
        if (!Schema::connection($connection)->hasTable($table)) {
            Schema::connection($connection)->create($table, function (Blueprint $t)
            {
                $t->bigIncrements('id');
                $t->unsignedBigInteger('user_id');
                $t->string('symbol', 16);
                $t->decimal('current_price', 16, 6)->nullable();
                $t->string('triggered_windows', 40);
                $t->decimal('closest_proximity_pct', 8, 4)->nullable();
                $t->string('peak_dates', 191)->nullable();
                $t->timestamp('sent_at');
                $t->enum('status', ['SENT', 'FAILED']);
                $t->text('error_message')->nullable();
                $t->timestamps();

                $t->index(['user_id', 'symbol', 'sent_at'], 'peak_prox_notifs_user_symbol_sent');
            });
        }
    }

    public function down(): void
    {
        $connection = config('myfinance2.db_connection');
        Schema::connection($connection)->dropIfExists(
            (new PeakProximityNotification())->getTable()
        );
    }
};
