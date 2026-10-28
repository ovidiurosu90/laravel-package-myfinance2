<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\Tests\Support;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Connection;

/**
 * The row counts of every table of the host's two databases (HostEnvironment), cheap enough to take before and after
 * a whole test run: the highest id of a table with one (an insert that was rolled back never shows, a committed one
 * always does), the row count of a table without. Reads only, on connections of its own.
 */
final class TableSnapshot
{
    /**
     * @var array<string, Connection>|null
     */
    private ?array $_connections = null;

    /**
     * @var array<string, array<string, array{column: ?string, value: int}>> connection => table => reading
     */
    private array $_readings = [];

    /**
     * @param list<string> $ignored "connection.table" written outside the tests meanwhile (e.g. by a cron)
     */
    public function __construct(private readonly array $ignored = [])
    {
    }

    public function take(): void
    {
        $this->_readings = [];
        foreach ($this->_connections() as $name => $connection) {
            foreach ($this->_tables($connection) as $table) {
                $column = $this->_idColumn($connection, $table);

                $this->_readings[$name][$table] = [
                    'column' => $column,
                    'value'  => $this->_read($connection, $table, $column),
                ];
            }
        }
    }

    /**
     * What changed since take(): "connection.table: +N rows" for rows added (and "-N rows" for rows gone from a table
     * without an id), plus the tables created meanwhile.
     *
     * @return list<string>
     */
    public function changes(): array
    {
        $changes = [];
        foreach ($this->_connections() as $name => $connection) {
            foreach ($this->_tables($connection) as $table) {
                $before = $this->_readings[$name][$table] ?? null;
                if ($before === null) {
                    $changes[] = "{$name}.{$table}: new table";
                    continue;
                }

                $added = $before['column'] === null
                    ? $this->_read($connection, $table, null) - $before['value']
                    : $connection->table($table)->where($before['column'], '>', $before['value'])->count();
                if ($added !== 0) {
                    $changes[] = sprintf('%s.%s: %+d rows', $name, $table, $added);
                }
            }
        }

        return $changes;
    }

    private function _read(Connection $connection, string $table, ?string $idColumn): int
    {
        $query = $connection->table($table);

        return (int) ($idColumn === null ? $query->count() : $query->max($idColumn));
    }

    private function _idColumn(Connection $connection, string $table): ?string
    {
        foreach ($connection->getSchemaBuilder()->getColumns($table) as $column) {
            if ($column['name'] === 'id' && $column['auto_increment']) {
                return 'id';
            }
        }

        return null;
    }

    /**
     * The connection's own tables (getTables() can list other databases on the same server too), less the ignored.
     *
     * @return list<string>
     */
    private function _tables(Connection $connection): array
    {
        $database = $connection->getDatabaseName();
        $tables   = [];
        foreach ($connection->getSchemaBuilder()->getTables() as $table) {
            $ignored = in_array($connection->getName() . '.' . $table['name'], $this->ignored, true);
            if (($table['schema'] ?? $database) === $database && !$ignored) {
                $tables[] = $table['name'];
            }
        }
        sort($tables);

        return $tables;
    }

    /**
     * @return array<string, Connection>
     */
    private function _connections(): array
    {
        if ($this->_connections === null) {
            $capsule = new Capsule();
            foreach (HostEnvironment::connections() as $name => $settings) {
                $capsule->addConnection($settings, $name);
            }
            $this->_connections = [];
            foreach (array_keys(HostEnvironment::connections()) as $name) {
                $this->_connections[$name] = $capsule->getConnection($name);
            }
        }

        return $this->_connections;
    }
}
