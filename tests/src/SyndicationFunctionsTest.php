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

    /** A feed that attaches no picture still gives its entry the first one its HTML shows. */
    public function testTheFirstPictureOfAnEntrysHtmlIsItsImage(): void
    {
        $html = '<p>Intro</p><img decoding="async" class="aligncenter" src="https://framablog.org/a.png?x=1&amp;y=2" alt=""><img src="https://framablog.org/b.png">';

        $this->assertSame('https://framablog.org/a.png?x=1&y=2', \YesWiki\Content\Action\SyndicationAction::firstImageIn($html));
        $this->assertNull(\YesWiki\Content\Action\SyndicationAction::firstImageIn('<p>No picture</p><img data-src="https://x.org/a.png">'));
        $this->assertNull(\YesWiki\Content\Action\SyndicationAction::firstImageIn('<img src="data:image/gif;base64,R0lGOD">'));
    }

    /** The picture shown as the entry's image is not shown a second time in its description. */
    public function testTheImageLeavesTheDescription(): void
    {
        $html = '<p><a href="https://x.org/a"><img class="c" src="https://x.org/a.png?x=1&amp;y=2" alt="" /></a></p><p>Text <img src="https://x.org/b.png"></p>';

        $this->assertSame(
            '<p>Text <img src="https://x.org/b.png"></p>',
            \YesWiki\Content\Action\SyndicationAction::withoutImage($html, 'https://x.org/a.png?x=1&y=2')
        );
        $this->assertSame($html, \YesWiki\Content\Action\SyndicationAction::withoutImage($html, 'https://x.org/other.png'));
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

    /** A feed item's image is fetched from a public address or not at all, and nothing lands in files/ otherwise. */
    public function testAFeedImageInsideTheNetworkIsNotDownloaded(): void
    {
        $wiki = $this->getWiki();
        $action = new class extends \YesWiki\Content\Action\SyndicationAction {
            public function download(string $url): string
            {
                return $this->downloadFile($url);
            }
        };
        $action->setServices($wiki->services);
        $action->setParams($wiki->services->get(\Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface::class));
        $before = glob('files/*');

        foreach ([
            'http://localhost:1/photo.png',
            'http://169.254.169.254/latest/meta-data/photo.png',
            'file:///etc/passwd',
        ] as $url) {
            $this->assertSame('', $action->download($url), $url);
        }
        $this->assertSame($before, glob('files/*'));
    }
}
