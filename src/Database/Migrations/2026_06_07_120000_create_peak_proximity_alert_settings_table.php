<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

use ovidiuro\myfinance2\App\Models\PeakProximityAlertSetting;

return new class extends Migration
{
    public function up(): void
    {
        $connection = config('myfinance2.db_connection');

        $table = (new PeakProximityAlertSetting())->getTable();
        if (!Schema::connection($connection)->hasTable($table)) {
            Schema::connection($connection)->create($table, function (Blueprint $t)
            {
                $t->bigIncrements('id');
                $t->unsignedBigInteger('user_id');
                $t->string('symbol', 16);
                $t->enum('status', ['ENABLED', 'DISABLED'])->default('DISABLED');
                $t->date('until')->nullable();
                $t->timestamps();

                $t->unique(['user_id', 'symbol'], 'peak_prox_settings_user_symbol_unique');
            });
        }
    }

    public function down(): void
    {
        $connection = config('myfinance2.db_connection');
        Schema::connection($connection)->dropIfExists(
            (new PeakProximityAlertSetting())->getTable()
        );
    }
};
