<?php

namespace YesWiki\Test\Core;

require_once 'tests/YesWikiTestCase.php';
require_once 'src/syndication.functions.php';

/**
 * Regression test for ticket 23 (syndication absorbed into core).
 */
class SyndicationFunctionsTest extends YesWikiTestCase
{
    public static function setUpBeforeClass(): void
    {
        // SyndicationAction is autoloaded now (ticket 06 moved it into the Content module),
        // but booting the wiki still defines YESWIKI_SOURCE_DIR, which its include_once needs
        self::getWiki();
    }

    public function testTruncateLeavesShortTextUntouched()
    {
        $this->assertSame('short text', truncate('short text', 100));
    }

    public function testTruncateCutsLongTextAndAppendsEllipsis()
    {
        $result = truncate(str_repeat('word ', 50), 20);

        $this->assertStringEndsWith('&hellip;', $result);
        $this->assertLessThan(50, strlen($result));
    }

    /**
     * SyndicationAction::run() calls multiArraySearch() (to detect whether a feed item
     * was already imported as a Bazar entry) without defining it itself -- it's defined
     * in src/bazar.functions.php (relocated from tools/bazar/libs/bazar.fonct.php by
     * ticket 24), required unconditionally from src/YesWiki.php. A real cross-tool
     * dependency, unaffected by this ticket's relocation of SyndicationAction.php, and
     * deliberately NOT redefined in src/syndication.functions.php.
     */
    public function testMultiArraySearchFromBazarIsReachableAfterWikiBoots()
    {
        $this->getWiki();

        $this->assertTrue(function_exists('multiArraySearch'));

        $entries = [
            ['bf_titre' => 'First', 'bf_url' => 'https://example.com/a'],
            ['bf_titre' => 'Second', 'bf_url' => 'https://example.com/b'],
        ];
        $result = multiArraySearch($entries, 'bf_url', 'https://example.com/b');

        $this->assertNotEmpty($result);
        $this->assertSame('Second', $result[0]['bf_titre']);
    }

    public function testSyndicationActionFormatArgumentsParsesMapping()
    {
        $wiki = $this->getWiki();
        $action = new \YesWiki\Content\Action\SyndicationAction();
        $action->setServices($wiki->services);
        $action->setParams($wiki->services->get(\Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface::class));

        $result = $action->formatArguments([
            'url' => 'https://example.com/feed.rss, https://example.com/feed2.rss',
            'mapping' => 'id=1400,title=bf_titre,url=bf_url',
        ]);

        $this->assertSame(['https://example.com/feed.rss', 'https://example.com/feed2.rss'], $result['url']);
        $this->assertSame('1400', $result['mapping']['id']);
        $this->assertSame('bf_titre', $result['mapping']['title']);
        $this->assertSame('bf_url', $result['mapping']['url']);
        // defaults for keys not explicitly given in mapping=
        $this->assertSame('bf_chapeau', $result['mapping']['summary']);
        $this->assertSame('liste_description.twig', $result['template']);
    }
}
