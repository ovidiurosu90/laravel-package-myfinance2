<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

use ovidiuro\myfinance2\App\Models\Trade;
use App\Models\User;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $connection = config('myfinance2.db_connection');
        $table = (new Trade())->getTable();
        $columnCheck = Schema::connection($connection)->hasColumn($table, 'user_id');

        if ($columnCheck) {
            return;
        }

        Schema::connection($connection)->table($table, function (Blueprint $table)
        {
            $usersDatabase = config('database.connections.' .
                config('database.default') . '.database');
            $usersTable = (new User())->getTable();

            $table->foreignId('user_id')
                ->after('id')
                ->nullable()
                ->constrained(
                    table: $usersDatabase . '.' . $usersTable,
                    indexName: $table->getTable() . '_user_id'
                );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $connection = config('myfinance2.db_connection');
        $table = (new Trade())->getTable();

        Schema::connection($connection)->table($table, function (Blueprint $table)
        {
            $table->dropForeign($table->getTable() . '_user_id');
            $table->dropColumn('user_id');
        });
    }
};

