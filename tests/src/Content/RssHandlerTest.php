<?php

namespace YesWiki\Test\Content;

use PHPUnit\Framework\Attributes\Depends;
use Symfony\Component\HttpFoundation\Request;
use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Service\PageManager;
use YesWiki\Kernel\Service\CurrentRequest;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Kernel\Service\Performer;
use YesWiki\Test\Core\YesWikiTestCase;
use YesWiki\YesWikiRuntime;

require_once 'tests/YesWikiTestCase.php';

/**
 * The `/rss` handler, after pear/xml_util was dropped for DOM.
 *
 * The point of these assertions is that the feed PARSES. The old builder escaped each
 * value and then ran html_entity_decode() over the finished document to turn escaped
 * `<![CDATA[` markers back into real CDATA -- which un-escaped every other value too, so
 * an `&` or a `<` anywhere in the wiki's own RSS config produced a feed no reader could
 * read. Nothing in the previous test suite would have noticed.
 */
class RssHandlerTest extends YesWikiTestCase
{
    private const PAGE_TAG = 'RssHandlerTestPage';

    public function testWikiExisting(): YesWikiRuntime
    {
        $wiki = $this->getWiki();
        $this->assertTrue($wiki->services->has(YesWikiRuntime::class));

        return $wiki->services->get(YesWikiRuntime::class);
    }

    #[Depends('testWikiExisting')]
    public function testTheFeedIsWellFormedXml(YesWikiRuntime $wiki): void
    {
        $output = $this->runRssOn($wiki, 'a plain page');

        $doc = new \DOMDocument();
        $this->assertTrue($doc->loadXML($output), 'the feed must parse as XML');

        $root = $doc->documentElement;
        $this->assertNotNull($root);
        $this->assertSame('rss', $root->nodeName);
        $this->assertSame('2.0', $root->getAttribute('version'));
        $this->assertSame(1, $doc->getElementsByTagName('channel')->length);
        // the self link a reader follows to refresh, under the namespace it needs
        $selfLinks = $doc->getElementsByTagNameNS('http://www.w3.org/2005/Atom', 'link');
        $this->assertSame(1, $selfLinks->length);
        $this->assertSame('self', $selfLinks->item(0)?->attributes?->getNamedItem('rel')?->nodeValue);
    }

    /**
     * The regression the rewrite fixes: a title carrying XML metacharacters used to be
     * written into the document unescaped, and the whole feed stopped parsing.
     */
    #[Depends('testWikiExisting')]
    public function testMetacharactersInTheConfigDoNotBreakTheFeed(YesWikiRuntime $wiki): void
    {
        $config = $wiki->services->get(\YesWiki\Kernel\Service\RuntimeConfig::class);
        $previous = $config['BAZ_RSS_DESCRIPTIONSITE'] ?? null;
        $config['BAZ_RSS_DESCRIPTIONSITE'] = 'Tom & Jerry <not a tag> "quoted"';
        try {
            $output = $this->runRssOn($wiki, 'a plain page');
            $doc = new \DOMDocument();
            $this->assertTrue($doc->loadXML($output), 'an & in the config must not break the feed');
            $this->assertSame(
                'Tom & Jerry <not a tag> "quoted"',
                $doc->getElementsByTagName('description')->item(0)?->textContent
            );
        } finally {
            $config['BAZ_RSS_DESCRIPTIONSITE'] = $previous;
        }
    }

    private function runRssOn(YesWikiRuntime $wiki, string $content): string
    {
        $pageManager = $wiki->services->get(PageManager::class);
        $pageManager->save(self::PAGE_TAG, [PageBody::CONTENT => $content], '', true);
        $page = $pageManager->getOne(self::PAGE_TAG);
        $wiki->services->get(PageContext::class)->setTag(self::PAGE_TAG);
        $wiki->services->get(PageContext::class)->setPage($page);
        $wiki->services->get(CurrentRequest::class)->replace(Request::create('https://example.org/?' . self::PAGE_TAG . '/rss'));

        try {
            return $wiki->services->get(Performer::class)->run('rss', 'handler', []);
        } finally {
            $pageManager->deleteOrphaned(self::PAGE_TAG);
            unset($GLOBALS['wiki']);
        }
    }
}
