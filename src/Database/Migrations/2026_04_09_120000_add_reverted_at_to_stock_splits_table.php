<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

use ovidiuro\myfinance2\App\Models\StockSplit;

return new class extends Migration
{
    public function up(): void
    {
        $connection = config('myfinance2.db_connection');
        $table      = (new StockSplit())->getTable();

        Schema::connection($connection)->table($table, function (Blueprint $table)
        {
            $table->timestamp('reverted_at')->nullable()->after('alerts_adjusted');
        });
    }

    public function down(): void
    {
        $connection = config('myfinance2.db_connection');
        $table      = (new StockSplit())->getTable();

        Schema::connection($connection)->table($table, function (Blueprint $table)
        {
            $table->dropColumn('reverted_at');
        });
    }
};
