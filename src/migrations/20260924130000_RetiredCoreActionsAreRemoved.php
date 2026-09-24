<?php

use YesWiki\Content\Entity\PageBody;
use YesWiki\Core\YesWikiMigration;
use YesWiki\Kernel\Performable\ActionRegistry;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Search\Service\SearchIndexer;

/** Calls to Doryphore core actions that Ectoplasme neither kept, renamed nor rewrote are taken out of every page revision. */
class RetiredCoreActionsAreRemoved extends YesWikiMigration
{
    /** Doryphore 4.6 core actions with no Ectoplasme successor, and rename targets that never shipped. */
    public const RETIRED = [
        'adminpages', 'backgroundimage', 'backlinks', 'bazarrecordsindex', 'changestyle', 'diaporama',
        'endbackgroundimage', 'erasespamedcomments', 'footer', 'greeting', 'header', 'interwikilist', 'lang',
        'liensjavascripts', 'liensstyle', 'linkjavascript', 'linkstyle', 'listpages', 'myfavorites',
        'newtextsearch', 'orphanedpages', 'progressbar', 'template', 'testtriples', 'textsearch', 'tocjs',
        'translation', 'wantedpages', 'entrylistcategory', 'searchform',
    ];

    public function run()
    {
        $registry = $this->getService(ActionRegistry::class);
        $gone = array_values(array_filter(
            self::RETIRED,
            fn (string $name): bool => !$registry->has('action', $registry->resolve('action', $name)[0])
        ));

        [$removed, $pages] = $this->remove($this->getService(DbService::class), $gone);

        $this->getService(SearchIndexer::class)->enqueue($pages);

        if ($removed !== []) {
            arsort($removed);
            $counts = implode(', ', array_map(fn (string $name, int $n): string => "{$name} ({$n})", array_keys($removed), $removed));
            $this->say(
                'actions that are no longer part of YesWiki were removed from ' . count($pages) . ' page(s), across all revisions: '
                . $counts . '. Pages: ' . implode(', ', array_slice($pages, 0, 40)) . (count($pages) > 40 ? '...' : '')
            );
        }
    }

    /**
     * Takes the calls to `$gone`, and the `{{end elem}}` closing them, out of every page and comment revision, or only `$tag`'s.
     *
     * @param list<string> $gone
     *
     * @return array{0: array<string, int>, 1: list<string>} calls removed per action, and the tags touched
     */
    public function remove(DbService $db, array $gone, ?string $tag = null): array
    {
        if ($gone === []) {
            return [[], []];
        }
        $pages = $db->prefixTable('pages');
        $sql = "SELECT id, tag, body FROM {$pages} WHERE " . $db->jsonAsText('body') . " LIKE '%{{%'";
        $params = [];
        if ($tag !== null) {
            $sql .= ' AND tag = ?';
            $params[] = $tag;
        }

        return $db->transactional(fn (): array => $this->removeFromRows($db, $db->loadAll($sql, $params), $gone));
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @param list<string>                     $gone
     *
     * @return array{0: array<string, int>, 1: list<string>}
     */
    private function removeFromRows(DbService $db, array $rows, array $gone): array
    {
        $pages = $db->prefixTable('pages');
        $names = implode('|', array_map(fn (string $name): string => preg_quote($name, '/'), $gone));
        $call = '/\{\{\s*(?:(' . $names . ')\b[^}]*|end\s+elem\s*=\s*"(' . $names . ')"\s*)\}\}/i';

        $removed = [];
        $touched = [];
        foreach ($rows as $row) {
            $body = PageBody::decode((string)$row['body']);
            $content = PageBody::content($body);
            $cleaned = (string)preg_replace_callback(
                $call,
                function (array $m) use (&$removed): string {
                    $name = strtolower(($m[1] ?? '') !== '' ? $m[1] : (string)($m[2] ?? ''));
                    $removed[$name] = ($removed[$name] ?? 0) + 1;

                    return "\x1B";
                },
                $content
            );
            if ($cleaned === $content) {
                continue;
            }

            $body[PageBody::CONTENT] = str_replace("\x1B", '', (string)preg_replace('/^[ \t]*(?:\x1B[ \t]*)+(?:\n|$)/m', '', $cleaned));
            $db->query("UPDATE {$pages} SET body = ? WHERE id = ?", [PageBody::encode($body), (string)$row['id']]);
            $touched[(string)$row['tag']] = true;
        }

        return [$removed, array_keys($touched)];
    }
}
