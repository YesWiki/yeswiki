<?php

namespace YesWiki\Admin\Service;

use YesWiki\Kernel\Service\DbService;

/** Copies one wiki's tables into an empty database on any supported engine, schema from the installer, rows verified by count. */
class DatabaseCopier
{
    public const DRIVERS = ['mysql', 'pgsql', 'sqlite'];

    private const BATCH = 1000;
    private const ZERO_DATES = ['0000-00-00 00:00:00' => '1970-01-01 00:00:00', '0000-00-00' => '1970-01-01'];

    /** SQLite's full-text shadow tables, which the engine fills itself and nothing may write to. */
    private const ENGINE_OWNED = '/_fts(_[a-z]+)?$/';

    /** @var list<string> */
    private array $notes = [];

    public function __construct(private readonly DbService $source)
    {
    }

    /** A connection to the target described by driver, host, port, database, user and password. */
    public static function connect(string $driver, string $host, string $port, string $database, string $user, string $password): \PDO
    {
        if (!in_array($driver, self::DRIVERS, true)) {
            throw new \InvalidArgumentException("unknown driver '{$driver}', expected one of " . implode(', ', self::DRIVERS));
        }
        $portPart = $port !== '' ? ";port={$port}" : '';
        $dsn = match ($driver) {
            'sqlite' => "sqlite:{$database}",
            'pgsql' => "pgsql:host={$host}{$portPart};dbname={$database}",
            default => "mysql:host={$host}{$portPart};dbname={$database};charset=utf8mb4",
        };

        return new \PDO($dsn, $driver === 'sqlite' ? null : $user, $driver === 'sqlite' ? null : $password, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }

    /**
     * Creates the schema under `$prefix` on `$target`, copies every table of it from this wiki and compares row counts.
     *
     * @return array<string, array{0: int, 1: int}> source and target row count per table
     */
    public function copy(\PDO $target, string $prefix, ?callable $progress = null): array
    {
        $this->notes = [];
        $targetDriver = (string)$target->getAttribute(\PDO::ATTR_DRIVER_NAME);
        $existing = self::tables($target, $prefix);
        if ($existing !== []) {
            throw new \RuntimeException('the target already holds tables under this prefix: ' . implode(', ', $existing));
        }

        try {
            SchemaCreator::create($target, $prefix);
            $tables = self::tables($target, $prefix);
            $sourceTables = array_values(array_filter(
                $this->source->schema()->getTables(),
                fn (string $table): bool => str_starts_with($table, $prefix) && preg_match(self::ENGINE_OWNED, $table) !== 1
            ));
            foreach (array_diff($sourceTables, $tables) as $left) {
                $this->notes[] = "{$left} is not part of the YesWiki schema and was not copied";
            }

            $counts = [];
            foreach ($tables as $table) {
                if (!in_array($table, $sourceTables, true)) {
                    $counts[$table] = [0, 0];
                    continue;
                }
                $copied = $this->copyTable($target, $targetDriver, $table);
                $targetCount = $target->query('SELECT COUNT(*) FROM ' . self::quote($targetDriver, $table));
                $counts[$table] = [
                    (int)$this->source->scalar('SELECT COUNT(*) FROM ' . $this->source->quoteIdentifier($table), 0),
                    $targetCount === false ? -1 : (int)$targetCount->fetchColumn(),
                ];
                if ($progress !== null) {
                    $progress($table, $copied);
                }
            }
        } catch (\Throwable $failure) {
            foreach (self::tables($target, $prefix) as $created) {
                $target->exec('DROP TABLE IF EXISTS ' . self::quote($targetDriver, $created) . ($targetDriver === 'pgsql' ? ' CASCADE' : ''));
            }

            throw $failure;
        }

        return $counts;
    }

    /**
     * What the copy could not carry or had to change, one line each.
     *
     * @return list<string>
     */
    public function notes(): array
    {
        return $this->notes;
    }

    /** Copies one table in batches, keyed on `id` when it has one, inside a single target transaction. */
    private function copyTable(\PDO $target, string $targetDriver, string $table): int
    {
        $columns = array_values(array_intersect(self::columns($target, $targetDriver, $table), $this->source->schema()->dumpableColumns($table)));
        $sourceList = implode(', ', array_map(fn (string $c): string => $this->source->quoteIdentifier($c), $columns));
        $insert = $target->prepare(
            'INSERT INTO ' . self::quote($targetDriver, $table)
            . ' (' . implode(', ', array_map(fn (string $c): string => self::quote($targetDriver, $c), $columns)) . ')'
            . ' VALUES (' . implode(', ', array_fill(0, count($columns), '?')) . ')'
        );
        $from = ' FROM ' . $this->source->quoteIdentifier($table);
        $keyed = in_array('id', $columns, true);

        $copied = 0;
        $cleaned = 0;
        $target->beginTransaction();
        try {
            $target->exec('DELETE FROM ' . self::quote($targetDriver, $table));
            $last = null;
            do {
                $rows = $keyed
                    ? $this->source->loadAll(
                        "SELECT {$sourceList}{$from}" . ($last === null ? '' : ' WHERE ' . $this->source->quoteIdentifier('id') . ' > ?')
                        . ' ORDER BY ' . $this->source->quoteIdentifier('id') . ' LIMIT ' . self::BATCH,
                        $last === null ? [] : [$last]
                    )
                    : $this->source->loadAll("SELECT {$sourceList}{$from}");
                foreach ($rows as $row) {
                    $values = [];
                    foreach ($columns as $column) {
                        $value = $row[$column] ?? null;
                        if ($targetDriver === 'pgsql' && is_string($value)) {
                            $fixed = str_replace("\0", '', self::ZERO_DATES[$value] ?? $value);
                            $cleaned += $fixed !== $value ? 1 : 0;
                            $value = $fixed;
                        }
                        $values[] = $value;
                    }
                    $insert->execute($values);
                    $copied++;
                    $last = $keyed ? $row['id'] : $last;
                }
            } while ($keyed && count($rows) === self::BATCH);

            if ($targetDriver === 'pgsql' && $keyed) {
                $target->exec(
                    "SELECT setval(pg_get_serial_sequence('" . str_replace("'", "''", $table) . "', 'id'),"
                    . ' COALESCE((SELECT MAX(id) FROM ' . self::quote($targetDriver, $table) . '), 1),'
                    . ' (SELECT MAX(id) FROM ' . self::quote($targetDriver, $table) . ') IS NOT NULL)'
                );
            }
            $target->commit();
        } catch (\Throwable $failure) {
            $target->rollBack();

            throw new \RuntimeException("copying {$table} failed after {$copied} row(s): " . $failure->getMessage(), 0, $failure);
        }
        if ($cleaned > 0) {
            $this->notes[] = "{$table}: {$cleaned} value(s) PostgreSQL cannot store were adapted (zero dates, NUL bytes)";
        }

        return $copied;
    }

    /**
     * The prefixed tables present on a connection.
     *
     * @return list<string>
     */
    private static function tables(\PDO $db, string $prefix): array
    {
        $sql = match ((string)$db->getAttribute(\PDO::ATTR_DRIVER_NAME)) {
            'sqlite' => "SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'",
            'pgsql' => 'SELECT tablename FROM pg_tables WHERE schemaname = current_schema()',
            default => 'SHOW TABLES',
        };
        $statement = $db->query($sql);
        $names = $statement === false ? [] : array_map('strval', $statement->fetchAll(\PDO::FETCH_COLUMN));
        $names = array_values(array_filter(
            $names,
            fn (string $name): bool => str_starts_with($name, $prefix) && preg_match(self::ENGINE_OWNED, $name) !== 1
        ));
        sort($names);

        return $names;
    }

    /**
     * A table's column names on the target, read from an empty result.
     *
     * @return list<string>
     */
    private static function columns(\PDO $db, string $driver, string $table): array
    {
        $statement = $db->query('SELECT * FROM ' . self::quote($driver, $table) . ' WHERE 1 = 0');
        $columns = [];
        for ($i = 0; $statement !== false && $i < $statement->columnCount(); $i++) {
            $meta = $statement->getColumnMeta($i);
            if ($meta !== false) {
                $columns[] = (string)$meta['name'];
            }
        }

        return $columns;
    }

    private static function quote(string $driver, string $identifier): string
    {
        return $driver === 'mysql' ? '`' . str_replace('`', '``', $identifier) . '`' : '"' . str_replace('"', '""', $identifier) . '"';
    }
}
