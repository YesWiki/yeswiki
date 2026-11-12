<?php

namespace YesWiki\Test\Kernel;

use YesWiki\Kernel\Service\AssetPublisher;
use YesWiki\Kernel\Service\Replication;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** The published folder's stamp moves when a source changes, and only then: every worker has to agree on the URLs. */
class PublishedAssetStampTest extends YesWikiTestCase
{
    private string $instance = '';
    private string $previousCwd = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->getWiki();
        $this->previousCwd = (string)getcwd();
        $this->instance = sys_get_temp_dir() . '/yeswiki-stamp-test-' . getmypid();
        if (!is_dir($this->instance . '/javascripts') && !mkdir($this->instance . '/javascripts', 0755, true)) {
            $this->markTestSkipped('could not lay out an instance to publish into');
        }
        chdir($this->instance);
    }

    protected function tearDown(): void
    {
        chdir($this->previousCwd);
        exec('rm -rf ' . escapeshellarg($this->instance));
        parent::tearDown();
    }

    private function source(string $name, int $mtime): string
    {
        $relPath = 'javascripts/' . $name;
        file_put_contents($this->instance . '/' . $relPath, "export const name = '{$name}'\n");
        touch($this->instance . '/' . $relPath, $mtime);

        return $relPath;
    }

    /** A worker that boots later must name the same folder as the one that published first. */
    public function testPublishingMoreFilesDoesNotMoveTheStamp(): void
    {
        $older = $this->source('stamp-probe-older.js', time() - 3600);
        $newer = $this->source('stamp-probe-newer.js', time() - 60);
        $before = AssetPublisher::publishedStamp();

        $this->assertNotNull(AssetPublisher::publishedUrl($older, '1'));
        $this->assertNotNull(AssetPublisher::publishedUrl($newer, '1'));

        $this->assertSame($before, AssetPublisher::publishedStamp(), 'publishing for the first time is not a change of sources');
    }

    /** A source edited after it was published is the reason the stamp exists: browsers must fetch it again. */
    public function testASourceChangedSinceItWasPublishedMovesTheStamp(): void
    {
        $probe = $this->source('stamp-probe-edited.js', time() - 3600);
        AssetPublisher::publishedUrl($probe, '1');
        $before = AssetPublisher::publishedStamp();

        touch($this->instance . '/' . $probe, time());
        clearstatcache();
        AssetPublisher::publishedUrl($probe, '1');

        $this->assertNotSame($before, AssetPublisher::publishedStamp());
    }

    /** Replicas each publish their own folder, in whatever order requests reach them, yet a page from one names assets another must serve: the program is immutable there, so its version is the whole name. */
    public function testEveryReplicaNamesTheSameFolderWhateverItPublishedBefore(): void
    {
        $was = getenv(Replication::ENV);
        putenv(Replication::ENV . '=1');

        try {
            AssetPublisher::publishedUrl($this->source('stamp-probe-replica.js', time() - 3600), 'another-replicas-folder');
            touch($this->instance . '/cache/assets/another-replicas-folder/.references-published', time());

            $this->assertSame('0', AssetPublisher::publishedStamp());
        } finally {
            putenv($was === false ? Replication::ENV : Replication::ENV . '=' . $was);
        }
    }
}
