<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection(config('myfinance2.db_connection'))
            ->table('trades', function (Blueprint $table)
            {
                $table->boolean('is_transfer')->default(false)->after('status');
            });
    }

    public function down(): void
    {
        Schema::connection(config('myfinance2.db_connection'))
            ->table('trades', function (Blueprint $table)
            {
                $table->dropColumn('is_transfer');
            });
    }
};

