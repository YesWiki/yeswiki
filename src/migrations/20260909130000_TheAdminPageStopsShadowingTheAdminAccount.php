<?php

use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Entity\PageType;
use YesWiki\Core\YesWikiMigration;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Search\Service\SearchIndexer;

/**
 * A tag names one Content (ADR-0001), so the seeded `WikiAdmin` redirect stops shadowing the account.
 *
 * The seed shipped a page whose tag was the literal string `WikiAdmin`, redirecting to `GererSite`.
 * An install that kept the default administrator name then had two `latest = 'Y'` rows for that
 * tag, one `user` and one `page`, and every read resolved it by whichever row the engine reached
 * first -- which is why authentication worked only by luck. The redirect is dropped: the menu links
 * `GererSite` directly, and `?WikiAdmin` now answers with the account, which is what it names.
 *
 * Only a page still holding the seeded redirect is removed. One somebody wrote their own content
 * into is left where it is, and said so, because moving it is their decision and not this one's.
 */
class TheAdminPageStopsShadowingTheAdminAccount extends YesWikiMigration
{
    /** What the seed put in that page, and the only body this migration will delete. */
    private const SEEDED_REDIRECT = '{{redirect page="GererSite"}}';

    public function run(): void
    {
        $dbService = $this->getService(DbService::class);
        $pages = trim($dbService->prefixTable('pages'));

        $removed = [];
        $kept = [];

        foreach ($this->shadowedTags() as $tag) {
            $page = $dbService->loadSingle(
                "SELECT body FROM {$pages} WHERE tag = ? AND type = ? AND latest = 'Y' LIMIT 1",
                [$tag, PageType::PAGE]
            );

            $content = trim(PageBody::content(PageBody::decode($page['body'] ?? null)));
            if ($content !== self::SEEDED_REDIRECT) {
                $kept[] = $tag;

                continue;
            }

            $dbService->query(
                "DELETE FROM {$pages} WHERE tag = ? AND type = ?",
                [$tag, PageType::PAGE]
            );
            $removed[] = $tag;
        }

        if ($removed !== []) {
            $this->getService(SearchIndexer::class)->enqueue($removed);
        }

        $this->say(
            count($removed) . ' page(s) that shadowed an account of the same name were removed'
            . ($removed === [] ? '' : ' (' . implode(', ', $removed) . ')')
            . ($kept === []
                ? '.'
                : ', and ' . count($kept) . ' left alone because someone had written into them ('
                    . implode(', ', $kept) . '): move that content elsewhere, then delete the page.')
        );
    }

    /**
     * Every tag carrying both a current account and a current page.
     *
     * @return list<string>
     */
    private function shadowedTags(): array
    {
        $dbService = $this->getService(DbService::class);
        $pages = trim($dbService->prefixTable('pages'));

        $rows = $dbService->loadAll(
            "SELECT DISTINCT u.tag AS tag
               FROM {$pages} AS u
               JOIN {$pages} AS p ON p.tag = u.tag
              WHERE u.type = ? AND u.latest = 'Y'
                AND p.type = ? AND p.latest = 'Y'",
            [PageType::USER, PageType::PAGE]
        );

        return array_map(static fn (array $row): string => (string)$row['tag'], $rows);
    }
}
