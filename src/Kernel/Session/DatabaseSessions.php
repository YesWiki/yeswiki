<?php

namespace YesWiki\Kernel\Session;

use Symfony\Component\HttpFoundation\Session\Storage\Handler\PdoSessionHandler;
use YesWiki\Kernel\Database\SqlDialectFactory;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\SessionSchema;

/**
 * PHP sessions in the wiki's `sessions` table through Symfony's PdoSessionHandler, on a connection of their own opened only when a session
 * is read, so that every replica sees the same ones (replicas 02). A table not created yet is created, since the update that will run
 * the migration needs a session of its own to be sent.
 */
final class DatabaseSessions implements \SessionHandlerInterface, \SessionUpdateTimestampHandlerInterface
{
    private ?PdoSessionHandler $handler = null;

    private ?\PDO $pdo = null;

    /** @param array<array-key, mixed> $config the wiki's configuration, as read for this request */
    public function __construct(private readonly array $config)
    {
    }

    /** The driver the configuration names. */
    public function driver(): string
    {
        $driver = $this->config['db_driver'] ?? 'mysql';

        return is_string($driver) && $driver !== '' ? $driver : 'mysql';
    }

    public function table(): string
    {
        $prefix = $this->config['table_prefix'] ?? 'yeswiki_';

        return (is_string($prefix) ? $prefix : 'yeswiki_') . SessionSchema::TABLE;
    }

    public function open(string $path, string $name): bool
    {
        return $this->call(static fn (PdoSessionHandler $handler): bool => $handler->open($path, $name));
    }

    public function read(string $id): string
    {
        return $this->call(static fn (PdoSessionHandler $handler): string => (string)$handler->read($id));
    }

    public function write(string $id, string $data): bool
    {
        return $this->call(static fn (PdoSessionHandler $handler): bool => $handler->write($id, $data));
    }

    public function destroy(string $id): bool
    {
        return $this->call(static fn (PdoSessionHandler $handler): bool => $handler->destroy($id));
    }

    public function gc(int $max_lifetime): int|false
    {
        return $this->call(static fn (PdoSessionHandler $handler): int|false => $handler->gc($max_lifetime));
    }

    public function validateId(string $id): bool
    {
        return $this->call(static fn (PdoSessionHandler $handler): bool => $handler->validateId($id));
    }

    public function updateTimestamp(string $id, string $data): bool
    {
        return $this->call(static fn (PdoSessionHandler $handler): bool => $handler->updateTimestamp($id, $data));
    }

    /** Lets the connection go once the session is written, as PdoSessionHandler does with a connection it opened itself. */
    public function close(): bool
    {
        if ($this->handler === null) {
            return true;
        }
        try {
            return $this->handler->close();
        } finally {
            $this->handler = null;
            $this->pdo = null;
        }
    }

    /**
     * @template T
     *
     * @param \Closure(PdoSessionHandler): T $operation
     *
     * @return T
     */
    private function call(\Closure $operation): mixed
    {
        try {
            return $operation($this->handler());
        } catch (\PDOException $failure) {
            if (!$this->isMissingTable($failure)) {
                throw $failure;
            }
            foreach (SqlDialectFactory::forDriver($this->driver())->sessionsDdl($this->table()) as $statement) {
                $this->connection()->exec($statement);
            }

            return $operation($this->handler());
        }
    }

    private function handler(): PdoSessionHandler
    {
        return $this->handler ??= new PdoSessionHandler($this->connection(), [
            'db_table' => $this->table(),
            'lock_mode' => $this->driver() === 'sqlite' ? PdoSessionHandler::LOCK_NONE : PdoSessionHandler::LOCK_ADVISORY,
        ]);
    }

    private function connection(): \PDO
    {
        if ($this->pdo === null) {
            $secret = static fn (mixed $value): ?string => is_scalar($value) && (string)$value !== '' ? (string)$value : null;
            $sqlite = $this->driver() === 'sqlite';
            $this->pdo = new \PDO(
                DbService::dsnFor($this->config),
                $sqlite ? null : $secret($this->config['db_user'] ?? null),
                $sqlite ? null : $secret($this->config['db_password'] ?? null),
                [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
            );
            if ($sqlite) {
                $this->pdo->exec('PRAGMA busy_timeout = 5000');
            }
        }

        return $this->pdo;
    }

    /** Whether the engine said the table is not there: MySQL 1146, PostgreSQL 42P01, SQLite "no such table". */
    private function isMissingTable(\PDOException $failure): bool
    {
        $info = $failure->errorInfo ?? [];

        return ($info[1] ?? null) === 1146 || ($info[0] ?? null) === '42P01' || str_contains($failure->getMessage(), 'no such table');
    }
}
