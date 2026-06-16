<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

use ovidiuro\myfinance2\App\Models\DipBuyingNotification;

return new class extends Migration
{
    public function up(): void
    {
        $connection = config('myfinance2.db_connection');

        $table = (new DipBuyingNotification())->getTable();
        if (!Schema::connection($connection)->hasTable($table)) {
            Schema::connection($connection)->create($table, function (Blueprint $t)
            {
                $t->bigIncrements('id');
                $t->unsignedBigInteger('user_id');
                // Snapshot of the plan state at send time, for the history page and the throttle.
                $t->decimal('effective_dd_pct', 8, 4);
                $t->decimal('vusa_dd_pct', 8, 4)->nullable();
                $t->decimal('portfolio_dd_pct', 8, 4)->nullable();
                $t->string('driver', 16)->nullable();            // vusa | portfolio
                $t->unsignedTinyInteger('target_pct');           // band target deployed %
                $t->decimal('deployed_pct', 8, 4);
                $t->decimal('deployed_eur', 16, 2);
                $t->decimal('pool_amount_eur', 16, 2);
                $t->decimal('suggested_tranche_eur', 16, 2);
                $t->string('verdict', 24);                       // behind | on_plan | ahead | no_dip | stall
                // Which of the three triggers fired this email (band_deepened | crossed_behind | stall).
                $t->string('trigger', 24);
                $t->timestamp('sent_at');
                $t->enum('status', ['SENT', 'FAILED']);
                $t->text('error_message')->nullable();
                $t->timestamps();

                $t->index(['user_id', 'sent_at'], 'dip_buying_notifs_user_sent');
            });
        }
    }

    public function down(): void
    {
        $connection = config('myfinance2.db_connection');
        Schema::connection($connection)->dropIfExists(
            (new DipBuyingNotification())->getTable()
        );
    }
};
