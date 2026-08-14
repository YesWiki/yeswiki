<?php

namespace YesWiki\Test\Content;

use YesWiki\Content\Service\FeedLoader;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * Reading a feed must not write anything.
 *
 * The vendored SimplePie reads `$this->normalization[$this->scheme]` with a **null** scheme
 * in `IRI::scheme_normalization()`, which happens for every relative link a feed carries --
 * and PHP 8.5 deprecates a null array offset, `isset()` included. With `debug` on, which is
 * every working copy, that is a notice per link printed into the middle of the page
 * rendering the feed: reported from korben.info's, a wall of them above the cards.
 *
 * There is no SimplePie release that fixes it (1.9.0 is the latest), so the level is
 * masked while it parses. The test asserts the symptom -- that nothing is PRINTED -- rather
 * than the mechanism, because two mechanisms that looked right did not work: a handler
 * installed around `init()` (SimplePie installs its own inside, and a notice raised there
 * never reaches ours) and a level masked after the constructor (`set_feed_url()` parses an
 * IRI, so sixteen notices are out before it).
 */
class FeedLoaderTest extends YesWikiTestCase
{
    private string $feedFile = '';

    protected function setUp(): void
    {
        parent::setUp();
        // relative links are the point: an absolute one carries a scheme and never reaches
        // the branch that deprecates
        $this->feedFile = tempnam(sys_get_temp_dir(), 'yw-feed') . '.xml';
        file_put_contents($this->feedFile, <<<'XML'
            <?xml version="1.0" encoding="utf-8"?>
            <rss version="2.0"><channel>
              <title>A feed</title>
              <link>/relative/channel</link>
              <item>
                <title>An item</title>
                <link>/relative/item</link>
                <guid isPermaLink="false">1</guid>
                <description>Something short.</description>
              </item>
            </channel></rss>
            XML);
    }

    protected function tearDown(): void
    {
        if ($this->feedFile !== '' && file_exists($this->feedFile)) {
            unlink($this->feedFile);
        }
        parent::tearDown();
    }

    private function loader(): FeedLoader
    {
        return $this->getWiki()->services->get(FeedLoader::class);
    }

    /** Everything the caller does with the feed, since that is where the notices are. */
    private function readLinks(): mixed
    {
        return $this->loader()->read(
            $this->feedFile,
            static function (\SimplePie\SimplePie $feed): array {
                $links = [];
                foreach ($feed->get_items() as $item) {
                    $links[] = (string)$item->get_permalink();
                }

                return $links;
            },
            false
        );
    }

    // PHPUnit's own error handler records every deprecation the parse raises, mask or no
    // mask, and would report them as issues of this test. What is under test is what PHP
    // PRINTS, so the test needs the plain engine behaviour.
    #[\PHPUnit\Framework\Attributes\WithoutErrorHandler]
    public function testReadingAFeedPrintsNothing(): void
    {
        $reporting = error_reporting(E_ALL);
        $display = ini_set('display_errors', '1');

        ob_start();
        try {
            $links = $this->readLinks();
        } finally {
            $printed = (string)ob_get_clean();
            error_reporting($reporting);
            ini_set('display_errors', (string)$display);
        }

        $this->assertSame([], preg_split('/\R+/', trim($printed), -1, PREG_SPLIT_NO_EMPTY) ?: []);
        $this->assertCount(1, $links, 'and the feed was actually read');
    }

    /** ...and the level the caller had is exactly the level it gets back. */
    #[\PHPUnit\Framework\Attributes\WithoutErrorHandler]
    public function testTheReportingLevelIsPutBackAfterwards(): void
    {
        $reporting = error_reporting(E_ALL);

        try {
            $this->readLinks();
            $this->assertSame(E_ALL, error_reporting());
        } finally {
            error_reporting($reporting);
        }
    }

    /** A url that says nothing is not a feed, and asking for it is not an error either. */
    public function testAnEmptyUrlReadsNothing(): void
    {
        $this->assertNull($this->loader()->read('   ', static fn () => 'read'));
    }
}
