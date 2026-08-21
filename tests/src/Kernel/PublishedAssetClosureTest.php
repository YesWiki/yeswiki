<?php

namespace YesWiki\Test\Kernel;

use YesWiki\Kernel\Service\AssetPublisher;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** What a published stylesheet points at must be published with it. */
class PublishedAssetClosureTest extends YesWikiTestCase
{
    private string $instance = '';
    private string $previousCwd = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->getWiki();
        $this->previousCwd = (string)getcwd();
        $this->instance = sys_get_temp_dir() . '/yeswiki-closure-test-' . getmypid();
        if (!is_dir($this->instance) && !mkdir($this->instance, 0755, true)) {
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

    private function published(string $relPath, string $version = '1'): string
    {
        return $this->instance . '/' . AssetPublisher::PUBLISHED_PREFIX . $version . '/' . $relPath;
    }

    public function testTheFilesAStylesheetPointsAtArePublishedWithIt(): void
    {
        $url = AssetPublisher::publishedUrl('styles/yw-content.css', '1');

        $this->assertNotNull($url, 'the stylesheet itself');

        foreach (['bazar/loading.gif', 'step-circle-icon.svg'] as $referenced) {
            $this->assertFileExists(
                $this->published('src/assets/images/' . $referenced),
                $referenced . ' is named by yw-content.css and must be published with it'
            );
        }
    }

    public function testAModulesStaticImportsArePublishedWithIt(): void
    {
        $source = \YESWIKI_PROGRAM_DIR . '/javascripts/aceditor.js';
        if (!is_file($source)) {
            $this->markTestSkipped('no aceditor.js to read imports from');
        }
        AssetPublisher::publishedUrl('javascripts/aceditor.js', '1');

        foreach (['link-panel.js', 'file-picker-panel.js', 'editor-rails.js', 'mode-yeswiki.js'] as $imported) {
            if (!is_file(\YESWIKI_PROGRAM_DIR . '/javascripts/' . $imported)) {
                continue;
            }
            $this->assertFileExists(
                $this->published('javascripts/' . $imported),
                $imported . ' is imported by aceditor.js'
            );
        }
    }

    /** ...including transitively, and without looping on a cycle. */
    public function testItFollowsTheChainAndSurvivesACycle(): void
    {
        $dir = $this->instance . '/custom/closure';
        mkdir($dir, 0755, true);
        file_put_contents($dir . '/a.css', "@import url('./b.css');\n");
        file_put_contents($dir . '/b.css', "@import url('./c.css');\n@import url('./a.css');\n");
        file_put_contents($dir . '/c.css', ".deep { color: red }\n");

        AssetPublisher::publishedUrl('custom/closure/a.css', '1');

        $this->assertFileExists($this->published('custom/closure/b.css'));
        $this->assertFileExists($this->published('custom/closure/c.css'), 'two links away');
    }

    /**
     * Only relative references, and only what may be served: an absolute URL belongs to whoever hosts it, and `url()` must not become a way to publish anything on disk.
     */
    public function testItPublishesNeitherRemoteNorUnservableReferences(): void
    {
        $dir = $this->instance . '/custom/closure';
        mkdir($dir, 0755, true);
        file_put_contents($dir . '/refs.css', implode("\n", [
            "@import url('https://fonts.example.org/x.css');",
            "@import url('//cdn.example.org/y.css');",
            "@import url('/styles/absolute.css');",
            "background: url('data:image/gif;base64,R0lGOD');",
            "background: url('../../private/secret.png');",
            "background: url('../../yeswiki.config.php');",
        ]));
        file_put_contents($this->instance . '/yeswiki.config.php', "<?php\n");

        AssetPublisher::publishedUrl('custom/closure/refs.css', '1');

        $versionDir = $this->instance . '/' . AssetPublisher::PUBLISHED_PREFIX . '1';
        $published = [];
        $tree = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($versionDir, \FilesystemIterator::SKIP_DOTS));
        foreach ($tree as $file) {
            $published[] = str_replace($versionDir . '/', '', (string)$file);
        }

        $this->assertSame(['custom/closure/refs.css'], $published, 'nothing but the sheet itself');
    }

    /** A source file that changed is published again, release string or not. */
    public function testAChangedSourceFileIsPublishedAgainUnderTheSameVersion(): void
    {
        $dir = $this->instance . '/custom/closure';
        mkdir($dir, 0755, true);
        file_put_contents($dir . '/changing.css', ".before { color: red }\n");
        AssetPublisher::publishedUrl('custom/closure/changing.css', '1');
        $this->assertStringContainsString('.before', (string)file_get_contents($this->published('custom/closure/changing.css')));

        file_put_contents($dir . '/changing.css', ".after { color: blue }\n");
        touch($dir . '/changing.css', time() + 2);
        AssetPublisher::publishedUrl('custom/closure/changing.css', '1');

        $this->assertStringContainsString(
            '.after',
            (string)file_get_contents($this->published('custom/closure/changing.css')),
            'the published copy must follow the source'
        );
    }

    /** ...and the URL moves with it, or no browser will ever ask for the new copy. */
    public function testTheUrlMovesWhenTheSourceChanges(): void
    {
        $dir = $this->instance . '/custom/closure';
        mkdir($dir, 0755, true);
        file_put_contents($dir . '/moving.css', ".before { color: red }\n");
        touch($dir . '/moving.css', time() - 120);

        $this->assertSame('', AssetPublisher::publishedStamp(), 'nothing has gone stale yet');
        $first = AssetPublisher::publishedUrl('custom/closure/moving.css', '1');

        file_put_contents($dir . '/moving.css', ".after { color: blue }\n");
        touch($dir . '/moving.css', time());
        AssetPublisher::publishedUrl('custom/closure/moving.css', '1');

        $stamp = AssetPublisher::publishedStamp();
        $this->assertNotSame('', $stamp, 'the sources moving on is recorded');

        $second = AssetPublisher::publishedUrl('custom/closure/moving.css', '1-' . $stamp);
        $this->assertNotSame($first, $second);
        $this->assertStringContainsString(
            '.after',
            (string)file_get_contents($this->instance . '/' . $second),
            'and it serves the new content'
        );
    }

    /** An instance published by a YesWiki that had no stamp gets one from what is on disk. */
    public function testATreeWithNoStampGetsOneFromItsOwnFiles(): void
    {
        AssetPublisher::publishedUrl('styles/yw-core.css', '1');
        $stampFile = $this->instance . '/' . AssetPublisher::PUBLISHED_PREFIX . '.sources-changed';
        $this->assertFileExists($stampFile);

        unlink($stampFile);

        $stamp = AssetPublisher::publishedStamp();

        $this->assertNotSame('', $stamp, 'read off the published files themselves');
        $this->assertSame(
            (string)filemtime(\YESWIKI_PROGRAM_DIR . '/styles/yw-core.css'),
            $stamp,
            'and it is how new the published set is'
        );
        $this->assertFileExists($stampFile, 'written down, so the walk happens once');
    }

    /** No file of ours points above the Program tree. */
    public function testNoAssetOfOursReferencesSomethingAboveTheSourceTree(): void
    {
        $root = \YESWIKI_PROGRAM_DIR;
        $tree = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root . '/javascripts', \FilesystemIterator::SKIP_DOTS));

        $broken = [];
        foreach ($tree as $file) {
            $path = (string)$file;

            if (!str_ends_with($path, '.js') || str_contains($path, '/vendor/')) {
                continue;
            }
            $content = (string)file_get_contents($path);
            if (!preg_match_all('~(?:^|[\s;])(?:import|export)\s[^;\'"]*?from\s*[\'"](\.[^\'"]+)[\'"]~m', $content, $matches)) {
                continue;
            }
            foreach ($matches[1] as $reference) {
                $target = \dirname($path) . '/' . $reference;
                if (!is_file($target)) {
                    $broken[] = str_replace($root . '/', '', $path) . ' -> ' . $reference;
                }
            }
        }

        $this->assertSame([], $broken, 'relative imports must resolve where they say they do');
    }

    /** An instance published by an older YesWiki has the sheets and not their imports. */
    public function testATreePublishedWithoutItsReferencesIsRepaired(): void
    {
        AssetPublisher::publishedUrl('styles/yw-content.css', '1');
        foreach (['bazar/loading.gif', 'step-circle-icon.svg'] as $referenced) {
            unlink($this->published('src/assets/images/' . $referenced));
        }
        $marker = $this->instance . '/' . AssetPublisher::PUBLISHED_PREFIX . '1/.references-published';
        if (is_file($marker)) {
            unlink($marker);
        }

        AssetPublisher::publishedUrl('styles/yw-core.css', '1');

        $this->assertFileExists($this->published('src/assets/images/bazar/loading.gif'));
        $this->assertFileExists(
            $marker,
            'and it is marked, so the sweep does not run on every request'
        );
    }
}
