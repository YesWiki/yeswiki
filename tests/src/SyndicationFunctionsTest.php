<?php

namespace YesWiki\Test\Core;

require_once 'tests/YesWikiTestCase.php';

use YesWiki\Kernel\Service\StringUtilService;

/** Regression test for ticket 23 (syndication absorbed into core). */
class SyndicationFunctionsTest extends YesWikiTestCase
{
    public static function setUpBeforeClass(): void
    {
        self::getWiki();
    }

    public function testTruncateLeavesShortTextUntouched(): void
    {
        $this->assertSame('short text', StringUtilService::truncate('short text', 100));
    }

    public function testTruncateCutsLongTextAndAppendsEllipsis(): void
    {
        $result = StringUtilService::truncate(str_repeat('word ', 50), 20);

        $this->assertStringEndsWith('&hellip;', $result);
        $this->assertLessThan(50, strlen($result));
    }

    /**
     * `SyndicationAction` asks whether a feed item was already imported as an entry, by searching the entries it holds for one whose url matches.
     */
    public function testSearchNestedFindsAnEntryByOneOfItsFields(): void
    {
        $entries = [
            ['bf_titre' => 'First', 'bf_url' => 'https://example.com/a'],
            ['bf_titre' => 'Second', 'bf_url' => 'https://example.com/b'],
        ];
        $result = StringUtilService::searchNested($entries, 'bf_url', 'https://example.com/b');

        $this->assertNotEmpty($result);
        $this->assertSame('Second', $result[0]['bf_titre']);
    }

    public function testSyndicationActionFormatArgumentsParsesMapping(): void
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

        $this->assertSame('bf_chapeau', $result['mapping']['summary']);
        $this->assertSame('liste_description.twig', $result['template']);
    }
}
