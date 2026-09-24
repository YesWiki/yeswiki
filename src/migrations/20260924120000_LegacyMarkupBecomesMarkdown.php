<?php

use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Entity\PageType;
use YesWiki\Content\Service\LegacyMarkupConverter;
use YesWiki\Core\YesWikiMigration;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\JournalSchema;
use YesWiki\Search\Service\SearchIndexer;

/** Doryphore's wakka markup becomes the CommonMark Ectoplasme renders, in every page and comment revision written before the upgrade. */
class LegacyMarkupBecomesMarkdown extends YesWikiMigration
{
    public function run()
    {
        $upgradedAt = $this->upgradedAt();
        [$pages, $revisions] = $this->rewrite($this->getService(DbService::class), $upgradedAt);

        $this->getService(SearchIndexer::class)->enqueue($pages);

        if ($pages !== []) {
            $this->say(
                'the legacy wiki markup (======titles======, //italics//, ""raw html"", [[links]], one break per line) '
                . "was rewritten as Markdown in {$revisions} revision(s) of " . count($pages) . ' page(s)'
                . ($upgradedAt === null ? '' : ", all written before the upgrade to Ectoplasme ({$upgradedAt})")
                . '. CamelCase words are no longer links by themselves.'
            );
        }
    }

    /**
     * Converts the page and comment revisions older than `$before`, or only those of `$tag` when one is given.
     *
     * @return array{0: list<string>, 1: int} the tags touched and the number of revisions rewritten
     */
    public function rewrite(DbService $db, ?string $before, ?string $tag = null): array
    {
        $pages = $db->prefixTable('pages');
        $sql = "SELECT id, tag, body FROM {$pages} WHERE (type IS NULL OR type IN ('', ?, ?))";
        $params = [PageType::PAGE, PageType::COMMENT];
        if ($before !== null) {
            $sql .= ' AND ' . $db->quoteIdentifier('time') . ' < ?';
            $params[] = $before;
        }
        if ($tag !== null) {
            $sql .= ' AND tag = ?';
            $params[] = $tag;
        }

        return $db->transactional(fn (): array => $this->convertRows($db, $db->loadAll($sql, $params)));
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     *
     * @return array{0: list<string>, 1: int}
     */
    private function convertRows(DbService $db, array $rows): array
    {
        $pages = $db->prefixTable('pages');
        $converter = new LegacyMarkupConverter();
        $touched = [];
        $revisions = 0;
        foreach ($rows as $row) {
            $body = PageBody::decode((string)$row['body']);
            $content = PageBody::content($body);
            if ($content === '') {
                continue;
            }

            $markdown = $converter->convert($content);
            if ($markdown === $content) {
                continue;
            }

            $body[PageBody::CONTENT] = $markdown;
            $db->query("UPDATE {$pages} SET body = ? WHERE id = ?", [PageBody::encode($body), (string)$row['id']]);
            $touched[(string)$row['tag']] = true;
            $revisions++;
        }

        return [array_keys($touched), $revisions];
    }

    /** When this wiki first ran an Ectoplasme migration: revisions after it were written in Markdown already. */
    private function upgradedAt(): ?string
    {
        $schema = $this->getService(JournalSchema::class);
        if (!$schema->exists()) {
            return null;
        }
        $db = $this->getService(DbService::class);
        $row = $db->loadSingle(
            'SELECT MIN(' . $db->quoteIdentifier('at') . ') AS first FROM ' . $db->quoteIdentifier($schema->table())
            . ' WHERE action = ?',
            ['migration.applied']
        );

        return empty($row['first']) ? null : (string)$row['first'];
    }
}
