<?php

namespace YesWiki\Content\Service;

use YesWiki\Content\Entity\PageBody;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\TripleStore;

/** Rewrites every `pages` row's body into the one JSON shape (ticket 09). */
class PageBodyMigrator
{
    public const STATUS_CONVERTED = 'converted';
    public const STATUS_ALREADY_JSON = 'already-json';
    public const STATUS_EMPTY = 'empty';
    /** already an object, but encoded with different flags -- rewritten canonically. */
    public const STATUS_NORMALIZED = 'normalized';

    private DbService $dbService;

    public function __construct(DbService $dbService)
    {
        $this->dbService = $dbService;
    }

    /**
     * What `apply()` would do, without writing: a count per status plus a sample of the rows that would change.
     *
     * @return array{total: int, converted: int, already_json: int, normalized: int, empty: int, samples: list<array{id: string, tag: string, before: string, after: string}>}
     */
    public function plan(int $sampleSize = 10): array
    {
        return $this->walk(false, $sampleSize);
    }

    /**
     * Convert every revision.
     *
     * @return array{total: int, converted: int, already_json: int, normalized: int, empty: int, samples: list<array{id: string, tag: string, before: string, after: string}>}
     */
    public function apply(?callable $onProgress = null): array
    {
        return $this->walk(true, 0, $onProgress);
    }

    /**
     * Re-read every row and assert it now decodes to an object, and that structured Content still carries the keys it had.
     *
     * @return list<array{id: string, tag: string, reason: string}>
     */
    public function verify(): array
    {
        $failures = [];
        foreach ($this->rows() as $row) {
            $stored = (string)($row['body'] ?? '');
            if (trim($stored) === '') {
                $failures[] = ['id' => (string)$row['id'], 'tag' => (string)$row['tag'], 'reason' => 'body is still empty'];
                continue;
            }
            $decoded = json_decode($stored, true);
            if (!is_array($decoded)) {
                $failures[] = ['id' => (string)$row['id'], 'tag' => (string)$row['tag'], 'reason' => 'body is not valid JSON'];
                continue;
            }
            if (PageBody::encode($decoded) !== $stored) {
                $failures[] = ['id' => (string)$row['id'], 'tag' => (string)$row['tag'], 'reason' => 'body is not canonically encoded'];
            }
        }

        return $failures;
    }

    /**
     * Decide what a single stored body should become.
     *
     * @return array{status: string, body: array<array-key, mixed>}
     */
    public static function classify(?string $stored, bool $isStructured): array
    {
        $stored = (string)$stored;

        if (trim($stored) === '') {
            return ['status' => self::STATUS_EMPTY, 'body' => []];
        }

        $decoded = json_decode($stored, true);

        if ($isStructured) {
            return is_array($decoded)
                ? ['status' => self::STATUS_ALREADY_JSON, 'body' => $decoded]
                : ['status' => self::STATUS_CONVERTED, 'body' => [PageBody::CONTENT => $stored]];
        }

        if (is_array($decoded) && ($decoded === [] || array_key_exists(PageBody::CONTENT, $decoded))) {
            return ['status' => self::STATUS_ALREADY_JSON, 'body' => $decoded];
        }

        return ['status' => self::STATUS_CONVERTED, 'body' => [PageBody::CONTENT => $stored]];
    }

    /**
     * @return array{total: int, converted: int, already_json: int, normalized: int, empty: int, samples: list<array{id: string, tag: string, before: string, after: string}>}
     */
    private function walk(bool $write, int $sampleSize, ?callable $onProgress = null): array
    {
        $structured = $this->structuredTags();
        $counts = ['total' => 0, 'converted' => 0, 'already_json' => 0, 'normalized' => 0, 'empty' => 0, 'samples' => []];

        foreach ($this->rows() as $row) {
            $counts['total']++;
            $stored = (string)($row['body'] ?? '');
            $result = self::classify($stored, isset($structured[$row['tag']]));
            $encoded = PageBody::encode($result['body']);

            if ($result['status'] === self::STATUS_ALREADY_JSON) {
                if ($encoded === $stored) {
                    $counts['already_json']++;
                    continue;
                }

                $counts['normalized']++;
            } elseif ($result['status'] === self::STATUS_EMPTY) {
                $counts['empty']++;
            } else {
                $counts['converted']++;
            }

            if (count($counts['samples']) < $sampleSize) {
                $counts['samples'][] = [
                    'id' => (string)$row['id'],
                    'tag' => (string)$row['tag'],
                    'before' => mb_substr($stored, 0, 80),
                    'after' => mb_substr($encoded, 0, 80),
                ];
            }

            if ($write) {
                $this->dbService->query(
                    'UPDATE ' . trim($this->dbService->prefixTable('pages'))
                    . ' SET body = ? WHERE id = ?',
                    [$encoded, $row['id']]
                );
                if ($onProgress !== null && $counts['total'] % 500 === 0) {
                    $onProgress($counts['total']);
                }
            }
        }

        return $counts;
    }

    /**
     * Every revision, oldest first.
     *
     * @return array<int, array<string, mixed>>
     */
    private function rows(): array
    {
        return $this->dbService->loadAll(
            'SELECT id, tag, body FROM ' . trim($this->dbService->prefixTable('pages')) . ' ORDER BY id ASC'
        );
    }

    /**
     * Tags whose Content type is declared by a triple -- entries, forms, users, lists, files.
     *
     * @return array<string, true>
     */
    private function structuredTags(): array
    {
        $rows = $this->dbService->loadAll(
            'SELECT DISTINCT resource FROM ' . trim($this->dbService->prefixTable('triples'))
            . ' WHERE property = ?'
            . " AND value <> 'migration'",
            [TripleStore::TYPE_URI]
        );

        $tags = [];
        foreach ($rows as $row) {
            $tags[(string)$row['resource']] = true;
        }

        return $tags;
    }
}
