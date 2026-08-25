<?php

use YesWiki\Content\Entity\PageBody;
use YesWiki\Core\YesWikiMigration;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Search\Service\SearchIndexer;

/** Ticket 26: rewrite `{{newtextsearch}}` and `{{searchform}}` out of stored content. */
class RewriteRetiredSearchActions extends YesWikiMigration
{
    /** What each retired call becomes. */
    private const REWRITES = [
        '/\{\{\s*searchform\b[^}]*\}\}/i' => '{{button icon="loupe" link="search"}}',
        '/\{\{\s*newtextsearch\b[^}]*\}\}/i' => '{{search}}',
    ];

    public function run()
    {
        $db = $this->getService(DbService::class);
        $pages = $db->prefixTable('pages');

        $bodyAsText = $db->jsonAsText('body');
        $rows = $db->loadAll(
            "SELECT id, tag, body FROM {$pages}"
            . " WHERE {$bodyAsText} LIKE '%searchform%' OR {$bodyAsText} LIKE '%newtextsearch%'"
        );

        $rewritten = [];
        foreach ($rows as $row) {
            $body = PageBody::decode((string)$row['body']);
            $changed = $this->rewriteBody($body);
            if ($changed === null) {
                continue;
            }

            $db->query(
                "UPDATE {$pages} SET body = ? WHERE id = ?",
                [PageBody::encode($changed), (string)$row['id']]
            );
            $rewritten[(string)$row['tag']] = true;
        }

        $this->getService(SearchIndexer::class)->enqueue(array_keys($rewritten));

        foreach (array_keys($rewritten) as $tag) {
            $this->say(
                "searchform/newtextsearch are retired (ticket 26); page '{$tag}' was rewritten to "
                . 'a button linking to /search, or to the search action. Any template, class or '
                . 'url parameter on the old call could not be carried over.'
            );
        }

        // Ticket 53: what the themes still say is a claim about the present, so the migration
        // runs Render's check rather than writing a line that goes stale.
        $this->reportCheck('themes-call-retired-search-actions');
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array<string, mixed>|null null when nothing in it changed
     */
    private function rewriteBody(array $body): ?array
    {
        $changed = false;
        array_walk_recursive($body, function (&$value) use (&$changed): void {
            if (!is_string($value)) {
                return;
            }
            $before = $value;
            foreach (self::REWRITES as $pattern => $replacement) {
                $value = (string)preg_replace($pattern, $replacement, $value);
            }
            if ($value !== $before) {
                $changed = true;
            }
        });

        return $changed ? $body : null;
    }
}
