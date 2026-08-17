<?php

use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Entity\PageType;
use YesWiki\Content\Service\PageManager;
use YesWiki\Core\YesWikiMigration;
use YesWiki\Kernel\Service\TripleStore;
use YesWiki\Search\Service\TagsManager;

/** Ticket 09: page keywords move from `triples` into the page's own `body.keywords`. */
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

        $tagsManager->reindexAll();
    }
}
