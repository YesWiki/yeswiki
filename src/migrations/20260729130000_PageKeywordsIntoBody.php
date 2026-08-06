<?php

use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Entity\PageType;
use YesWiki\Content\Service\PageManager;
use YesWiki\Content\Service\TripleStore;
use YesWiki\Core\YesWikiMigration;
use YesWiki\Search\Service\TagsManager;

/**
 * Ticket 09: page keywords move from `triples` into the page's own `body.keywords`.
 *
 * They were the last "fact about a page" living outside the page's row. After this the
 * body is the source of truth and the `TAG_PROPERTY` triples are a derived reverse index
 * (keyword -> pages), which is why this migration rebuilds rather than deletes them:
 * the tag cloud, multi-keyword AND filtering, the admin table's SQL aggregation and the
 * keyword vocabulary all genuinely want an index.
 *
 * Only the current revision of each page gets keywords. The triples were never versioned,
 * so there is no per-revision keyword history to migrate -- inventing one by stamping
 * today's keywords onto every past revision would be worse than leaving history alone.
 *
 * Idempotent: a page whose body already carries its keywords is skipped.
 */
class PageKeywordsIntoBody extends YesWikiMigration
{
    public function run(): void
    {
        $tripleStore = $this->getService(TripleStore::class);
        $pageManager = $this->getService(PageManager::class);
        $tagsManager = $this->getService(TagsManager::class);

        $byPage = [];
        foreach ($tripleStore->getMatching(null, TagsManager::TAG_PROPERTY, null, '=') as $triple) {
            $resource = (string)($triple['resource'] ?? '');
            $value = trim((string)($triple['value'] ?? ''));
            if ($resource === '' || $value === '') {
                continue;
            }
            if (!in_array($value, $byPage[$resource] ?? [], true)) {
                $byPage[$resource][] = $value;
            }
        }

        foreach ($byPage as $tag => $keywords) {
            $page = $pageManager->getOne($tag, null, false, true);
            if (empty($page)) {
                continue;
            }
            // a bazar entry's tags are an ordinary form field, already in its body under
            // the name the webmaster chose -- not `keywords`, and not this migration's business
            if ($pageManager->typeOf($tag) !== PageType::PAGE) {
                continue;
            }

            $body = $page['body'] ?? [];
            $existing = TagsManager::keywordsOf($page);
            $merged = $existing;
            foreach ($keywords as $keyword) {
                if (!in_array($keyword, $merged, true)) {
                    $merged[] = $keyword;
                }
            }
            if ($merged === $existing) {
                continue;
            }

            $body[PageBody::KEYWORDS] = $merged;
            $pageManager->save($tag, $body, '', true);
        }

        // the triples become the derived index, rebuilt from what the bodies now say
        $tagsManager->reindexAll();
    }
}
