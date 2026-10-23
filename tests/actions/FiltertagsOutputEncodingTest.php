<?php

namespace YesWiki\Test\Actions;

use YesWiki\Core\Service\PageManager;
use YesWiki\Core\Service\TripleStore;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * The filtertags action prints its parameters and the page tags as text, never as markup.
 */
class FiltertagsOutputEncodingTest extends YesWikiTestCase
{
    private const PAGE_TAG = 'FiltertagsOutputEncodingPage';
    private const TAG_VALUE = '<img src=x onerror=alert(1)>';
    private const TAG_PROPERTY = 'http://outils-reseaux.org/_vocabulary/tag';

    private $wiki;
    private $pageManager;
    private $tripleStore;

    protected function setUp(): void
    {
        $this->wiki = $this->getWiki();
        $GLOBALS['wiki'] = $this->wiki;
        $this->pageManager = $this->wiki->services->get(PageManager::class);
        $this->tripleStore = $this->wiki->services->get(TripleStore::class);

        $this->pageManager->save(self::PAGE_TAG, 'tagged content', '', true);
        $this->tripleStore->create(self::PAGE_TAG, self::TAG_PROPERTY, self::TAG_VALUE, '', '');
    }

    protected function tearDown(): void
    {
        while (ob_get_level() > 1) {
            ob_end_clean();
        }

        $this->tripleStore->delete(self::PAGE_TAG, self::TAG_PROPERTY, self::TAG_VALUE, '', '');
        $this->pageManager->deleteOrphaned(self::PAGE_TAG);
    }

    public function testAFilterTagIsPrintedAsText()
    {
        $html = $this->wiki->Action('filtertags', 1, ['filter1' => self::TAG_VALUE]);

        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;</button>', $html);
    }

    public function testAFilterTitleIsPrintedAsText()
    {
        $html = $this->wiki->Action('filtertags', 1, ['filter1' => '<img src=x onerror=alert(1)> : other']);

        $this->assertStringNotContainsString('<img', $html);
    }

    public function testAFilterClassCannotLeaveItsAttribute()
    {
        $html = $this->wiki->Action('filtertags', 1, ['filter1' => 'other', 'class1' => '"><img src=x onerror=alert(1)>']);

        $this->assertStringNotContainsString('<img', $html);
    }

    public function testATagBadgeIsPrintedAsText()
    {
        $html = $this->wiki->Action('filtertags', 1, ['filter1' => self::TAG_VALUE, 'template' => 'pages_full.tpl.html']);
        $html .= $this->wiki->Action('includepages', 1, ['pages' => self::PAGE_TAG, 'template' => 'pages_full.tpl.html']);
        $html .= $this->wiki->Action('listpagestag', 1, ['tags' => self::TAG_VALUE, 'template' => 'pages_full.tpl.html']);

        $this->assertStringNotContainsString('<img', $html);
    }
}
