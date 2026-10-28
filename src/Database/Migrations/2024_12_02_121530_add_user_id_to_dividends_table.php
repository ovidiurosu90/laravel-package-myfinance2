<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

use ovidiuro\myfinance2\App\Models\Dividend;
use ovidiuro\myfinance2\App\Services\AppUser;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $connection = config('myfinance2.db_connection');
        $table = (new Dividend())->getTable();
        $columnCheck = Schema::connection($connection)->hasColumn($table, 'user_id');

        if ($columnCheck) {
            return;
        }

        Schema::connection($connection)->table($table, function (Blueprint $table)
        {
            $table->foreignId('user_id')
                ->after('id')
                ->nullable();
            AppUser::addUserIdKey($table);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $connection = config('myfinance2.db_connection');
        $table = (new Dividend())->getTable();

        Schema::connection($connection)->table($table, function (Blueprint $table)
        {
            AppUser::dropUserIdKey($table);
            $table->dropColumn('user_id');
        });
    }
};

