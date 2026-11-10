<?php

namespace YesWiki\Kernel\Service\Lock;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Kernel\Service\DbService;

/** Locks held by the wiki's database connection: `GET_LOCK` on MySQL and MariaDB, advisory locks on PostgreSQL, files on SQLite. */
final class DatabaseLocks implements Locks
{
    /** @var array<string, true> */
    private array $held = [];

    private readonly FileLocks $sqlite;

    public function __construct(
        private readonly DbService $db,
        private readonly ParameterBagInterface $params,
        ?FileLocks $sqlite = null,
    ) {
        $this->sqlite = $sqlite ?? new FileLocks();
        $db->afterReconnect($this->retake(...));
    }

    /** Takes back on a new connection every lock the dropped one held; false when another holder got one first, which is then no longer this instance's. */
    public function retake(): bool
    {
        if ($this->onSqlite()) {
            return true;
        }
        $all = true;
        foreach (array_keys($this->held) as $name) {
            if (!self::truthy($this->take($name))) {
                unset($this->held[$name]);
                $all = false;
            }
        }

        return $all;
    }

    public function tryAcquire(string $name, bool &$heldElsewhere = false): bool
    {
        $heldElsewhere = false;
        if ($this->onSqlite()) {
            return $this->sqlite->tryAcquire($name, $heldElsewhere);
        }
        if (isset($this->held[$name])) {
            $heldElsewhere = true;

            return false;
        }
        $answer = $this->take($name);
        if ($answer === null) {
            return false;
        }
        if (!self::truthy($answer)) {
            $heldElsewhere = true;

            return false;
        }
        $this->held[$name] = true;

        return true;
    }

    public function release(string $name): void
    {
        if ($this->onSqlite()) {
            $this->sqlite->release($name);

            return;
        }
        if (!isset($this->held[$name])) {
            return;
        }
        unset($this->held[$name]);
        if ($this->onPostgres()) {
            $this->db->scalar('SELECT pg_advisory_unlock(CAST(? AS integer), CAST(? AS integer))', null, $this->pgKeys($name));
        } else {
            $this->db->scalar('SELECT RELEASE_LOCK(?)', null, [$this->mysqlName($name)]);
        }
    }

    public function probe(string $name): ?bool
    {
        if ($this->onSqlite()) {
            return $this->sqlite->probe($name);
        }
        if ($this->onPostgres()) {
            return (int)$this->db->scalar(
                "SELECT COUNT(*) FROM pg_locks WHERE locktype = 'advisory' AND granted AND objsubid = 2"
                . ' AND database = (SELECT oid FROM pg_database WHERE datname = current_database())'
                . ' AND classid::bigint = CAST(? AS bigint) AND objid::bigint = CAST(? AS bigint)',
                0,
                $this->pgKeys($name)
            ) > 0;
        }

        return $this->db->scalar('SELECT IS_USED_LOCK(?)', null, [$this->mysqlName($name)]) !== null;
    }

    private function take(string $name): mixed
    {
        return $this->onPostgres()
            ? $this->db->scalar('SELECT pg_try_advisory_lock(CAST(? AS integer), CAST(? AS integer))', null, $this->pgKeys($name))
            : $this->db->scalar('SELECT GET_LOCK(?, 0)', null, [$this->mysqlName($name)]);
    }

    public function forget(string $name): void
    {
        if ($this->onSqlite()) {
            $this->sqlite->forget($name);
        }
    }

    /** What the lock is called on the server: the wiki's database and table prefix go into it, since a farm's wikis may share one server. */
    private function key(string $name): string
    {
        return sha1($this->param('db_database') . '|' . $this->param('table_prefix') . '|' . $name);
    }

    /** A `GET_LOCK` name, under the 64 characters MySQL allows. */
    private function mysqlName(string $name): string
    {
        return 'yw_' . substr($this->key($name), 0, 40);
    }

    /** @return array{int, int} the two 28-bit keys of a PostgreSQL advisory lock */
    private function pgKeys(string $name): array
    {
        $key = $this->key($name);

        return [(int)hexdec(substr($key, 0, 7)), (int)hexdec(substr($key, 7, 7))];
    }

    private function param(string $name): string
    {
        $value = $this->params->has($name) ? $this->params->get($name) : '';

        return is_scalar($value) ? (string)$value : '';
    }

    private function onSqlite(): bool
    {
        return $this->db->getDriver() === 'sqlite';
    }

    private function onPostgres(): bool
    {
        return $this->db->getDriver() === 'pgsql';
    }

    private static function truthy(mixed $answer): bool
    {
        return $answer === true || $answer === 1 || $answer === '1' || $answer === 't';
    }
}
