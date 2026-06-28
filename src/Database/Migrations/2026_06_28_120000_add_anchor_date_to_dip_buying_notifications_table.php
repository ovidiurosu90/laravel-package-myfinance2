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
        $table      = (new DipBuyingNotification())->getTable();

        Schema::connection($connection)->table($table, function (Blueprint $t)
        {
            $t->date('anchor_date')->nullable()->after('driver');
        });
    }

    public function down(): void
    {
        $connection = config('myfinance2.db_connection');
        $table      = (new DipBuyingNotification())->getTable();

        Schema::connection($connection)->table($table, function (Blueprint $t)
        {
            $t->dropColumn('anchor_date');
        });
    }
};
