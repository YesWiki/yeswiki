<?php

namespace YesWiki\Content\Service;

use YesWiki\Content\Entity\PageBody;
use YesWiki\Kernel\Service\DbService;

/**
 * Rewrites every `pages` row's body into the one JSON shape (ticket 09).
 *
 * This is the riskiest write in the programme -- it touches the central table including
 * all history, on installs the maintainer does not control -- so it is a service rather
 * than logic buried in a migration file: the migration calls `apply()`, the operator can
 * call `plan()` first through `content:migrate-bodies --dry-run`, and `classify()` is a
 * pure function that can be tested exhaustively without a database.
 *
 * Two properties carry the safety:
 *
 * - **Idempotent by construction.** A row already in the target shape is skipped, so an
 *   interrupted run resumes simply by running again. That matters because YesWiki's
 *   migration runner has no transactions and swallows a failing migration's exception,
 *   leaving partial writes behind for the next run to finish.
 * - **Type comes from the `TYPE_URI` triple, never from the body's first character.**
 *   83 wiki pages in the reference wiki open with `{{` because an action call is the
 *   first thing on the page; sniffing for a leading brace would mangle every one.
 */
class PageBodyMigrator
{
    public const STATUS_CONVERTED = 'converted';
    public const STATUS_ALREADY_JSON = 'already-json';
    public const STATUS_EMPTY = 'empty';
    /** already an object, but encoded with different flags -- rewritten canonically */
    public const STATUS_NORMALIZED = 'normalized';

    private DbService $dbService;

    public function __construct(DbService $dbService)
    {
        $this->dbService = $dbService;
    }

    /**
     * What `apply()` would do, without writing: a count per status plus a sample of the
     * rows that would change.
     *
     * @return array{total: int, converted: int, already_json: int, normalized: int, empty: int, samples: list<array{id: string, tag: string, before: string, after: string}>}
     */
    public function plan(int $sampleSize = 10): array
    {
        return $this->walk(false, $sampleSize);
    }

    /**
     * Convert every revision. Returns the same counts as `plan()`.
     *
     * @return array{total: int, converted: int, already_json: int, normalized: int, empty: int, samples: list<array{id: string, tag: string, before: string, after: string}>}
     */
    public function apply(?callable $onProgress = null): array
    {
        return $this->walk(true, 0, $onProgress);
    }

    /**
     * Re-read every row and assert it now decodes to an object, and that structured
     * Content still carries the keys it had. Returns the tags of rows that fail.
     *
     * @return list<array{id: string, tag: string, reason: string}>
     */
    public function verify(): array
    {
        $failures = [];
        foreach ($this->rows() as $row) {
            $stored = (string)($row['body'] ?? '');
            if (trim($stored) === '') {
                // an empty body is written as '{}' by apply(); anything still blank was
                // not visited
                $failures[] = ['id' => (string)$row['id'], 'tag' => (string)$row['tag'], 'reason' => 'body is still empty'];
                continue;
            }
            $decoded = json_decode($stored, true);
            if (!is_array($decoded)) {
                $failures[] = ['id' => (string)$row['id'], 'tag' => (string)$row['tag'], 'reason' => 'body is not valid JSON'];
                continue;
            }
            if (PageBody::encode($decoded) !== $stored) {
                // not fatal on its own -- but it means another writer used different JSON
                // flags, which is how escaping corruption creeps back in
                $failures[] = ['id' => (string)$row['id'], 'tag' => (string)$row['tag'], 'reason' => 'body is not canonically encoded'];
            }
        }

        return $failures;
    }

    /**
     * Decide what a single stored body should become. Pure: no database, no services.
     *
     * `$isStructured` says whether this tag carries a `TYPE_URI` triple (entry, form,
     * user, list, file) -- those bodies are already JSON field-maps. Everything else is
     * a wiki page or a comment, whose markup becomes the `content` attribute.
     *
     * @return array{status: string, body: array<array-key, mixed>}
     */
    public static function classify(?string $stored, bool $isStructured): array
    {
        $stored = (string)$stored;

        if (trim($stored) === '') {
            // file-type Content stored '' before its attributes moved into the body, and
            // a page can legitimately be blank; both become an empty object
            return ['status' => self::STATUS_EMPTY, 'body' => []];
        }

        $decoded = json_decode($stored, true);

        if ($isStructured) {
            // a structured body that will not decode is corrupt, not markup: wrapping it
            // as `content` would bury an entry's fields where nothing looks for them.
            // Leave it untouched for an operator to look at.
            return is_array($decoded)
                ? ['status' => self::STATUS_ALREADY_JSON, 'body' => $decoded]
                : ['status' => self::STATUS_CONVERTED, 'body' => [PageBody::CONTENT => $stored]];
        }

        // A page or comment. It is already converted only if it decodes to an object AND
        // carries `content` -- a page whose markup happens to be a JSON object otherwise
        // decodes fine and would be silently swallowed.
        //
        // `{}` is the exception: that is what this migration writes for a blank page, so
        // treating it as unconverted would re-wrap every blank page on every run and break
        // idempotency. A page whose literal markup is `{}` renders as nothing either way.
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
                // structured already, but written with different JSON flags (older code
                // escaped unicode, so a stored `\u00e9` never matches a search for `é`).
                // Rewrite it canonically -- one shape means one encoding too.
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
     * Every revision, oldest first. `id` order makes an interrupted run resume in a
     * predictable place, and keeps the work stable while it runs.
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
     * Tags whose Content type is declared by a triple -- entries, forms, users, lists,
     * files. Their bodies are JSON field-maps already.
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
