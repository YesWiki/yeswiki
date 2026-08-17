<?php

namespace YesWiki\Test\Content;

use PHPUnit\Framework\Attributes\Depends;
use Symfony\Component\HttpFoundation\Request;
use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Service\PageManager;
use YesWiki\Kernel\Service\CurrentRequest;
use YesWiki\Test\Core\YesWikiTestCase;
use YesWiki\YesWikiRuntime;

require_once 'tests/YesWikiTestCase.php';

/** The entries RSS feed, after pear/xml_util was dropped for DOM. */
class EntriesFeedTest extends YesWikiTestCase
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

        $this->assertTrue($doc->loadXML($output), "the feed must parse as XML, got:\n" . substr($output, 0, 500));

        $root = $doc->documentElement;
        $this->assertNotNull($root);
        $this->assertSame('rss', $root->nodeName);
        $this->assertSame('2.0', $root->getAttribute('version'));
        $this->assertSame(1, $doc->getElementsByTagName('channel')->length);

        $selfLinks = $doc->getElementsByTagNameNS('http://www.w3.org/2005/Atom', 'link');
        $this->assertSame(1, $selfLinks->length);
        $this->assertSame('self', $selfLinks->item(0)?->attributes?->getNamedItem('rel')?->nodeValue);
    }

    /**
     * The regression the rewrite fixes: a title carrying XML metacharacters used to be written into the document unescaped, and the whole feed stopped parsing.
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
            $this->assertTrue(
                $doc->loadXML($output),
                "an & in the config must not break the feed, got:\n" . substr($output, 0, 500)
            );
            $this->assertSame(
                'Tom & Jerry <not a tag> "quoted"',
                $doc->getElementsByTagName('description')->item(0)?->textContent
            );
        } finally {
            $config['BAZ_RSS_DESCRIPTIONSITE'] = $previous;
        }
    }

    /**
     * A feed is built out of whatever anyone ever pasted into the wiki, and XML 1.0 has characters it simply cannot carry -- a control byte, a broken UTF-8 sequence.
     */
    #[Depends('testWikiExisting')]
    public function testCharactersXmlCannotCarryDoNotBreakTheFeed(YesWikiRuntime $wiki): void
    {
        $config = $wiki->services->get(\YesWiki\Kernel\Service\RuntimeConfig::class);
        $previous = $config['BAZ_RSS_DESCRIPTIONSITE'] ?? null;

        $config['BAZ_RSS_DESCRIPTIONSITE'] = "before\x0cmid\x00\xC3after";
        try {
            $output = $this->runRssOn($wiki, 'a plain page');
            $doc = new \DOMDocument();
            $this->assertTrue(
                $doc->loadXML($output),
                "illegal characters must not break the feed, got:\n" . substr($output, 0, 500)
            );
            $description = $doc->getElementsByTagName('description')->item(0)?->textContent;
            $this->assertStringContainsString('before', (string)$description);
            $this->assertStringContainsString('after', (string)$description);
        } finally {
            $config['BAZ_RSS_DESCRIPTIONSITE'] = $previous;
        }
    }

    /**
     * The feed no longer needs a page at all -- it is built from the query string -- but one is still created here so the wiki has some content to list, which is what makes the escaping assertions meaningful rather than vacuous.
     */
    private function runRssOn(YesWikiRuntime $wiki, string $content): string
    {
        $pageManager = $wiki->services->get(PageManager::class);
        $pageManager->save(self::PAGE_TAG, [PageBody::CONTENT => $content], '', true);
        $request = Request::create('https://example.org/?api/entries/rss');
        $wiki->services->get(CurrentRequest::class)->replace($request);

        try {
            return (string)$wiki->services
                ->get(\YesWiki\Content\Api\FeedApiController::class)
                ->entriesFeed($request)
                ->getContent();
        } finally {
            $pageManager->deleteOrphaned(self::PAGE_TAG);
            unset($GLOBALS['wiki']);
        }
    }
}
