<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

use ovidiuro\myfinance2\App\Models\DipBuyingNotification;

/**
 * The basis each dip alert measured deployment on (DipBuyingNotification::BASIS_*). Rows sent before this column
 * stay null: they used the plan's own basis (VUSA.AS's peak, the settings pool).
 */
return new class extends Migration
{
    public function up(): void
    {
        $connection = config('myfinance2.db_connection');
        $table      = (new DipBuyingNotification())->getTable();

        Schema::connection($connection)->table($table, function (Blueprint $t)
        {
            $t->string('basis', 16)->nullable()->after('anchor_date');
        });
    }

    public function down(): void
    {
        $connection = config('myfinance2.db_connection');
        $table      = (new DipBuyingNotification())->getTable();

        Schema::connection($connection)->table($table, function (Blueprint $t)
        {
            $t->dropColumn('basis');
        });
    }
};
