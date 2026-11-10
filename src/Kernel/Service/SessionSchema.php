<?php

namespace YesWiki\Kernel\Service;

/** The table PHP sessions live in, so that every replica reads the same ones (replicas 02); neither a backup nor a restore touches it. */
class SessionSchema
{
    public const TABLE = 'sessions';

    public function __construct(private readonly DbService $dbService)
    {
    }

    public function table(): string
    {
        return trim($this->dbService->prefixTable(self::TABLE));
    }

    public function create(): void
    {
        foreach ($this->dbService->dialect()->sessionsDdl($this->table()) as $statement) {
            $this->dbService->query($statement);
        }
    }

    public function exists(): bool
    {
        return in_array($this->table(), $this->dbService->schema()->getTables(), true);
    }
}
