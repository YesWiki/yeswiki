<?php

use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Entity\PageType;
use YesWiki\Core\YesWikiMigration;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Search\Service\SearchIndexer;

/** Puts back the dropdown dividers LegacyMarkupBecomesMarkdown split into an empty item and a rule, as the item `- ***`. */
class DropdownDividersAreListItemsAgain extends YesWikiMigration
{
    private const ITEM = '[ \t]*(?:[-*+]|\d+[.)])[ \t]';

    public function run()
    {
        $tags = $this->repair($this->getService(DbService::class));

        $this->getService(SearchIndexer::class)->enqueue($tags);

        if ($tags !== []) {
            $this->say('the dividers of ' . count($tags) . ' page(s) of dropdown menus are list items again (`- ***`): the Markdown conversion had turned each into an empty item and a rule.');
        }
    }

    /**
     * Repairs the latest revision of every page, or only of `$tag` when one is given.
     *
     * @return list<string> the tags repaired
     */
    public function repair(DbService $db, ?string $tag = null): array
    {
        $pages = $db->prefixTable('pages');
        $type = $db->quoteIdentifier('type');
        $sql = "SELECT id, tag, body FROM {$pages} WHERE latest = 'Y' AND ({$type} IS NULL OR {$type} IN ('', ?)) AND tag <> ?";
        $params = [PageType::PAGE, 'PageCss'];
        if ($tag !== null) {
            $sql .= ' AND tag = ?';
            $params[] = $tag;
        }

        return $db->transactional(function () use ($db, $pages, $sql, $params): array {
            $repaired = [];
            foreach ($db->loadAll($sql, $params) as $row) {
                $body = PageBody::decode((string)$row['body']);
                $content = PageBody::content($body);
                $fixed = self::repairContent($content);
                if ($fixed === $content) {
                    continue;
                }
                $body[PageBody::CONTENT] = $fixed;
                $db->query("UPDATE {$pages} SET body = ? WHERE id = ?", [PageBody::encode($body), (string)$row['id']]);
                $repaired[(string)$row['tag']] = true;
            }

            return array_keys($repaired);
        });
    }

    /** The content with each `-`, blank, `----`, blank between two list items made one divider item again. */
    public static function repairContent(string $content): string
    {
        $pattern = '/(^|\n)(' . self::ITEM . '[^\n]*)\n([ \t]*)-[ \t]*\n(?:[ \t]*\n)+----[ \t]*\n(?:[ \t]*\n)+([ \t]*)(?=-\n|(?:[-*+]|\d+[.)])[ \t])/';
        do {
            $before = $content;
            $content = (string)preg_replace_callback(
                $pattern,
                fn (array $m): string => $m[1] . $m[2] . "\n" . $m[3] . "- ***\n" . ($m[4] === '' ? $m[3] : $m[4]),
                $content
            );
        } while ($content !== $before);

        return $content;
    }
}
