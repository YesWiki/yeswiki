<?php

namespace YesWiki\Federation\Service;

use YesWiki\Kernel\Service\DbService;

/** The signatures already accepted from other servers, keyed by their hash, so that a replay is refused even when both copies arrive at once. */
class SeenSignatures
{
    public const TABLE = 'activitypub_seen_signatures';

    private DbService $dbService;

    public function __construct(DbService $dbService)
    {
        $this->dbService = $dbService;
    }

    /** Trimmed, unlike DbService::prefixTable(), which pads the name for concatenation. */
    public function table(): string
    {
        return trim($this->dbService->prefixTable(self::TABLE));
    }

    public function create(): void
    {
        $table = $this->dbService->quoteIdentifier($this->table());
        $hash = $this->dbService->quoteIdentifier('hash');
        $seenAt = $this->dbService->quoteIdentifier('seen_at');
        $this->dbService->query(
            "CREATE TABLE IF NOT EXISTS {$table} ({$hash} CHAR(64) NOT NULL PRIMARY KEY, {$seenAt} BIGINT NOT NULL)"
        );
    }

    public function exists(): bool
    {
        return in_array($this->table(), $this->dbService->schema()->getTables(), true);
    }

    /** Records the signature and says whether it is new: false when another request already recorded it, however close in time. */
    public function remember(string $signature, int $now, int $forgetBefore): bool
    {
        $table = $this->dbService->quoteIdentifier($this->table());
        $hash = $this->dbService->quoteIdentifier('hash');
        $seenAt = $this->dbService->quoteIdentifier('seen_at');
        $insert = "INSERT INTO {$table} ({$hash}, {$seenAt}) VALUES (?, ?)";
        $values = [hash('sha256', $signature), $now];

        try {
            $this->dbService->query("DELETE FROM {$table} WHERE {$seenAt} < ?", [$forgetBefore]);
        } catch (\Exception $missingTable) {
            $this->create();
        }

        try {
            $this->dbService->query($insert, $values);
        } catch (\Exception $failed) {
            if ($this->isDuplicateKey($failed)) {
                return false;
            }
            throw $failed;
        }

        return true;
    }

    private function isDuplicateKey(\Exception $failed): bool
    {
        $pdoException = $failed instanceof \PDOException ? $failed : $failed->getPrevious();
        if (!$pdoException instanceof \PDOException) {
            return false;
        }
        $sqlState = (string)($pdoException->errorInfo[0] ?? $pdoException->getCode());

        return str_starts_with($sqlState, '23');
    }
}
