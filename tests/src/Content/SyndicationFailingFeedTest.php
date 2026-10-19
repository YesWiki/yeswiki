<?php

namespace YesWiki\Test\Content;

use YesWiki\Render\Service\Performer;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** A feed that cannot be read is reported and skipped, and the other feeds of the action are still listed. */
class SyndicationFailingFeedTest extends YesWikiTestCase
{
    /** @var list<string> */
    private array $files = [];

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            @unlink($file);
        }
        parent::tearDown();
    }

    private function file(string $contents): string
    {
        $file = tempnam(sys_get_temp_dir(), 'yw-feed') . '.xml';
        file_put_contents($file, $contents);
        $this->files[] = $file;

        return $file;
    }

    private function goodFeed(): string
    {
        return $this->file(<<<'XML'
            <?xml version="1.0" encoding="utf-8"?>
            <rss version="2.0"><channel>
              <title>A feed</title>
              <link>https://news.example/</link>
              <item>
                <title>Still listed</title>
                <link>https://news.example/item</link>
                <guid isPermaLink="false">1</guid>
                <pubDate>Mon, 19 Oct 2026 10:00:00 +0000</pubDate>
                <description>Something short.</description>
              </item>
            </channel></rss>
            XML);
    }

    private function render(string $urls): string
    {
        return (string)$this->getWiki()->services->get(Performer::class)->run('syndication', 'action', [
            'url' => $urls,
            'template' => 'liste.twig',
        ]);
    }

    public function testTheOtherFeedsAreStillListed(): void
    {
        $html = $this->render($this->file('<p>not a feed <script>alert(1)</script></p>') . ',' . $this->goodFeed());

        $this->assertStringContainsString('yw-alert--danger', $html);
        $this->assertStringContainsString('Still listed', $html);
        $this->assertStringContainsString('feed_syndication', $html);
        $this->assertStringNotContainsString('<script>', $html);
    }

    public function testOnlyTheErrorsAreShownWhenNoFeedWorks(): void
    {
        $html = $this->render($this->file('<p>not a feed</p>'));

        $this->assertStringContainsString('yw-alert--danger', $html);
        $this->assertStringNotContainsString('feed_syndication', $html);
    }
}
