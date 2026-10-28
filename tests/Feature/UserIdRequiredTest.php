<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\Tests\Feature;

use Illuminate\Support\Facades\Schema;
use ovidiuro\myfinance2\Tests\TestCase;

/**
 * Every MyFinance2 table holds one user's records, so its user_id is required by the database itself: the foreign
 * key to the users table is optional (myfinance2.users_foreign_key) and accepts NULL anyway, and inserts that skip
 * MyFinance2Model's creating hook (query builder, upserts) would otherwise store rows nobody can see. A new table
 * fails here until it has a NOT NULL user_id or is listed as shared. Read-only: lists the columns, writes nothing.
 */
class UserIdRequiredTest extends TestCase
{
    /**
     * Market data shared by every user (prices per symbol), with no user_id.
     */
    private const SHARED_TABLES = ['stats_historical', 'stats_today'];

    public function test_every_table_but_the_shared_ones_requires_a_user_id(): void
    {
        $connection = config('myfinance2.db_connection');
        $database   = config("database.connections.{$connection}.database");
        $schema     = Schema::connection($connection);
        $offending  = [];

        foreach ($schema->getTables() as $table) {
            if (($table['schema'] ?? $database) !== $database || in_array($table['name'], self::SHARED_TABLES)) {
                continue;
            }

            $column = collect($schema->getColumns($table['name']))->firstWhere('name', 'user_id');
            if ($column === null || $column['nullable']) {
                $offending[] = $table['name'] . ($column === null ? ': no user_id' : ': user_id is nullable');
            }
        }

        $this->assertSame([], $offending, "Tables without a required user_id (run php artisan migrate?):\n"
            . implode("\n", $offending));
    }
}
