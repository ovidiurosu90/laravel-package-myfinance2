<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

use ovidiuro\myfinance2\App\Models\DipBuyingSetting;

return new class extends Migration
{
    public function up(): void
    {
        $connection = config('myfinance2.db_connection');

        $table = (new DipBuyingSetting())->getTable();
        if (!Schema::connection($connection)->hasTable($table)) {
            Schema::connection($connection)->create($table, function (Blueprint $t)
            {
                $t->bigIncrements('id');
                $t->unsignedBigInteger('user_id');
                // Single global EUR dip-buying pool the user sets (one number, not per-account in v1).
                $t->decimal('pool_amount_eur', 16, 2)->default(0);
                // Master opt-in for the panel + email; default DISABLED so nothing fires until set up.
                $t->enum('status', ['ENABLED', 'DISABLED'])->default('DISABLED');
                // Opt-in for the daily email specifically; the panel can be on while email is off.
                $t->boolean('email_enabled')->default(false);
                // Optional advanced override of the default ladder (config alerts.dip_buying.bands).
                $t->json('bands')->nullable();
                $t->timestamps();

                $t->unique('user_id', 'dip_buying_settings_user_unique');
            });
        }
    }

    public function down(): void
    {
        $connection = config('myfinance2.db_connection');
        Schema::connection($connection)->dropIfExists(
            (new DipBuyingSetting())->getTable()
        );
    }
};
