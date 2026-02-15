<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

use ovidiuro\myfinance2\App\Enums\FundingRole;
use ovidiuro\myfinance2\App\Models\Account;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $connection = config('myfinance2.db_connection');
        $table = (new Account())->getTable();
        $columnCheck = Schema::connection($connection)
            ->hasColumn($table, 'funding_role');

        if ($columnCheck) {
            return;
        }

        Schema::connection($connection)->table($table, function (Blueprint $table)
        {
            $table->enum('funding_role', array_column(FundingRole::cases(), 'value'))
                ->nullable()
                ->after('is_dividend_account');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $connection = config('myfinance2.db_connection');
        $table = (new Account())->getTable();

        $columnCheck = Schema::connection($connection)
            ->hasColumn($table, 'funding_role');

        if (!$columnCheck) {
            return;
        }

        Schema::connection($connection)->table($table, function (Blueprint $table)
        {
            $table->dropColumn('funding_role');
        });
    }
};

