<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

use ovidiuro\myfinance2\App\Models\PortfolioPeakSetting;

return new class extends Migration
{
    public function up(): void
    {
        $connection = config('myfinance2.db_connection');

        $table = (new PortfolioPeakSetting())->getTable();
        if (!Schema::connection($connection)->hasTable($table)) {
            Schema::connection($connection)->create($table, function (Blueprint $t)
            {
                $t->bigIncrements('id');
                $t->unsignedBigInteger('user_id');
                // Master opt-in; default DISABLED so nothing fires until the user sets it up.
                $t->enum('status', ['ENABLED', 'DISABLED'])->default('DISABLED');
                // Opt-in for the daily email channel specifically.
                $t->boolean('email_enabled')->default(false);
                // Per-metric trigger toggles; either can be switched off independently.
                $t->boolean('change_eur_enabled')->default(true);
                $t->boolean('change_pct_enabled')->default(true);
                $t->timestamps();

                $t->unique('user_id', 'portfolio_peak_settings_user_unique');
            });
        }
    }

    public function down(): void
    {
        $connection = config('myfinance2.db_connection');
        Schema::connection($connection)->dropIfExists(
            (new PortfolioPeakSetting())->getTable()
        );
    }
};
