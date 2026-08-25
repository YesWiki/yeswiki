<?php

use YesWiki\Content\Entity\PageBody;
use YesWiki\Core\YesWikiMigration;
use YesWiki\Kernel\Database\SqlParameters;
use YesWiki\Kernel\Entity\JournalChannel;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\Journal;
use YesWiki\Kernel\Service\JournalSchema;

/**
 * Ticket 51 / ADR-0025: the audit trail stops being a wiki page.
 *
 * The old pages are imported rather than deleted outright. An upgrade that silently destroys an
 * audit trail is the one thing an audit system exists to prevent -- and importing puts their
 * deletion under a stated retention the operator can raise *before* migrating, instead of under a
 * decision this migration made for them.
 */
class TheJournalReplacesTheLogPages extends YesWikiMigration
{
    private const LEGACY_TAG_PREFIX = 'LogDesActionsAdministratives';

    /** `2025-10-22 12:03:41 . . . . MelanieMichel . . . . Suppression de la page ->""MichelMelanie""`. */
    private const LINE = '/^(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}) \. \. \. \. (.*?) \. \. \. \. (.*)$/';

    public function run()
    {
        $this->getService(JournalSchema::class)->create();

        $imported = $this->importLegacyPages();
        $deleted = $this->deleteLegacyPages();

        $this->say(
            "the administrative log is a table now: {$imported} entries imported from {$deleted} "
            . self::LEGACY_TAG_PREFIX . '* page(s), which were then deleted -- read them on /admin/logs. '
            . 'They are kept for journal_audit_purge_time days (365 by default); raise it before the '
            . 'next housekeeping pass if you want more of them.'
        );
    }

    /** @return int entries written */
    private function importLegacyPages(): int
    {
        $db = $this->getService(DbService::class);
        $pages = trim($db->prefixTable('pages'));
        $rows = $db->loadAll(
            "SELECT tag, body FROM {$db->quoteIdentifier($pages)} WHERE tag LIKE ?" . SqlParameters::LIKE_CLAUSE_SUFFIX,
            [SqlParameters::likeStartsWith(self::LEGACY_TAG_PREFIX)]
        );

        $seen = [];
        $entries = [];
        foreach ($rows as $row) {
            $body = PageBody::decode(is_string($row['body']) ? $row['body'] : null);
            foreach (preg_split('/\r\n|\r|\n/', PageBody::content($body)) ?: [] as $line) {
                $matches = [];
                if (preg_match(self::LINE, trim($line), $matches) !== 1) {
                    continue;
                }
                // Every revision of a day's page carries every line written that day, so the
                // same fact is in the corpus as many times as the page was appended to.
                $key = $matches[1] . '|' . $matches[2] . '|' . $matches[3];
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $entries[] = [$matches[1], $matches[2], $matches[3], (string)$row['tag']];
            }
        }

        if ($entries === []) {
            return 0;
        }

        $journal = $db->quoteIdentifier($this->getService(JournalSchema::class)->table());
        $columns = implode(', ', array_map(
            fn (string $column): string => $db->quoteIdentifier($column),
            ['at', 'last_at', 'repeat', 'channel', 'level', 'actor', 'action', 'target', 'context']
        ));

        foreach ($entries as [$at, $actor, $sentence, $tag]) {
            $db->query(
                "INSERT INTO {$journal} ({$columns}) VALUES (?, ?, 1, ?, ?, ?, ?, ?, ?)",
                [
                    $at,
                    $at,
                    JournalChannel::Audit->value,
                    'info',
                    mb_substr($actor, 0, 191),
                    Journal::LEGACY,
                    $tag,
                    // The French sentence verbatim: it is what the trail said, and rewriting
                    // somebody's history into a dotted code would be inventing it.
                    json_encode(['message' => $sentence], PageBody::JSON_FLAGS),
                ]
            );
        }

        return count($entries);
    }

    /** @return int pages removed, returning that many tags to the namespace */
    private function deleteLegacyPages(): int
    {
        $db = $this->getService(DbService::class);
        $pages = $db->quoteIdentifier(trim($db->prefixTable('pages')));
        $triples = $db->quoteIdentifier(trim($db->prefixTable('triples')));
        $pattern = SqlParameters::likeStartsWith(self::LEGACY_TAG_PREFIX);

        $tags = $db->scalar(
            "SELECT COUNT(DISTINCT tag) FROM {$pages} WHERE tag LIKE ?" . SqlParameters::LIKE_CLAUSE_SUFFIX,
            0,
            [$pattern]
        );

        $db->query("DELETE FROM {$triples} WHERE resource LIKE ?" . SqlParameters::LIKE_CLAUSE_SUFFIX, [$pattern]);
        $db->query("DELETE FROM {$pages} WHERE tag LIKE ?" . SqlParameters::LIKE_CLAUSE_SUFFIX, [$pattern]);

        return (int)$tags;
    }
}
