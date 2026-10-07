<?php

namespace YesWiki\Federation\Service;

use YesWiki\Kernel\Service\DbService;

/** ActivityPub signatures already seen, stored as triples. */
class SeenSignatures
{
    public const PROPERTY = 'http://yeswiki.net/_vocabulary/activitypub/seenSignature';
    public const RESOURCE_PREFIX = 'activitypub:seenSignature:';

    private DbService $dbService;

    public function __construct(DbService $dbService)
    {
        $this->dbService = $dbService;
    }

    /** Records a signature; false when it was already seen. */
    public function remember(string $signature, int $now, int $forgetBefore): bool
    {
        $this->dbService->query(
            'DELETE FROM ' . $this->triples() . ' WHERE property = ? AND value < ?',
            [self::PROPERTY, $this->stamp($forgetBefore)]
        );

        return $this->claim(hash('sha256', $signature), $now);
    }

    /** Imports a signature hash seen at a given time. */
    public function import(string $hash, int $seenAt): void
    {
        if ($this->first(self::RESOURCE_PREFIX . $hash) === null) {
            $this->insert(self::RESOURCE_PREFIX . $hash, $seenAt);
        }
    }

    /** Inserts a claim; true when it is the oldest one. */
    private function claim(string $hash, int $now): bool
    {
        $resource = self::RESOURCE_PREFIX . $hash;
        if ($this->first($resource) !== null) {
            return false;
        }
        $value = $this->insert($resource, $now);

        return $this->first($resource) === $value;
    }

    private function insert(string $resource, int $seenAt): string
    {
        $value = $this->stamp($seenAt) . '|' . bin2hex(random_bytes(8));
        $this->dbService->query(
            'INSERT INTO ' . $this->triples() . ' (resource, property, value) VALUES (?, ?, ?)',
            [$resource, self::PROPERTY, $value]
        );

        return $value;
    }

    /**
     * The value of the oldest row for the resource.
     *
     * @phpstan-impure
     */
    private function first(string $resource): ?string
    {
        $row = $this->dbService->loadSingle(
            'SELECT value FROM ' . $this->triples() . ' WHERE resource = ? AND property = ? ORDER BY id ASC LIMIT 1',
            [$resource, self::PROPERTY]
        );

        return $row === null ? null : (string)$row['value'];
    }

    /** Zero-padded timestamp. */
    private function stamp(int $time): string
    {
        return sprintf('%020d', $time);
    }

    private function triples(): string
    {
        return trim($this->dbService->prefixTable('triples'));
    }
}
