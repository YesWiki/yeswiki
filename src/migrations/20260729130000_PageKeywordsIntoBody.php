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
    /**
     * The vocabulary this migration reads, as its own literal.
     *
     * It used to borrow `TagsManager::TAG_PROPERTY`, and ticket 62 retired that constant along with
     * the triples themselves. A migration describes the world that existed when it ran; borrowing a
     * name from code that has moved on is what makes an old migration fail on a fresh install years
     * later, so the URI lives here now.
     */
    private const TAG_PROPERTY = 'http://outils-reseaux.org/_vocabulary/tag';

    public function run(): void
    {
        $tripleStore = $this->getService(TripleStore::class);
        $pageManager = $this->getService(PageManager::class);

        $byPage = [];
        foreach ($tripleStore->getMatching(null, self::TAG_PROPERTY, null, '=') as $triple) {
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
    }
}
