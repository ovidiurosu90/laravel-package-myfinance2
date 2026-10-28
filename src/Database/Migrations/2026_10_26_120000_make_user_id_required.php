<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

use ovidiuro\myfinance2\App\Models\CashBalance;
use ovidiuro\myfinance2\App\Models\Dividend;
use ovidiuro\myfinance2\App\Models\LedgerTransaction;
use ovidiuro\myfinance2\App\Models\Order;
use ovidiuro\myfinance2\App\Models\PriceAlert;
use ovidiuro\myfinance2\App\Models\StockSplit;
use ovidiuro\myfinance2\App\Models\Trade;
use ovidiuro\myfinance2\App\Models\WatchlistSymbol;

/**
 * Every user's record has a user_id, enforced by the database: these columns were added nullable (to tables that
 * already held rows, or by habit), while MyFinance2Model always fills them on create. The foreign key to the users
 * table, optional since myfinance2.users_foreign_key, accepts NULL, so it never guaranteed this. Fails if a row still
 * has no user_id (assign it first).
 */
return new class extends Migration
{
    private const MODELS = [
        CashBalance::class, Dividend::class, LedgerTransaction::class, Order::class, PriceAlert::class,
        StockSplit::class, Trade::class, WatchlistSymbol::class,
    ];

    public function up(): void
    {
        $this->_setNullable(false);
    }

    public function down(): void
    {
        $this->_setNullable(true);
    }

    private function _setNullable(bool $nullable): void
    {
        $schema = Schema::connection(config('myfinance2.db_connection'));

        foreach (self::MODELS as $model) {
            $table  = (new $model())->getTable();
            $column = collect($schema->getColumns($table))->firstWhere('name', 'user_id');
            if ($column === null || $column['nullable'] === $nullable) {
                continue;
            }

            $schema->table($table, function (Blueprint $table) use ($nullable)
            {
                $table->unsignedBigInteger('user_id')->nullable($nullable)->change();
            });
        }
    }
};
