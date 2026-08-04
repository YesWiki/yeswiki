<?php

namespace YesWiki\Test\Kernel;

use YesWiki\Kernel\Service\AssetPublisher;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * What a published stylesheet points at must be published with it.
 *
 * A farm instance's docroot holds no styles of its own: `AssetRegistry` copies each
 * registered file into `cache/assets/{version}/` and links it there. But nobody registers
 * the sheets `bazar.css` `@import`s, the fonts a stylesheet's `url()` names, or the modules
 * `aceditor.js` imports -- the browser derives those from inside the file it was handed, and
 * asks for them at the matching published path.
 *
 * They used to arrive only through the request interception, which needs the webserver to
 * send missing files to index.php. Where the vhost has no such fallback -- a wiki in a plain
 * subdirectory on a shared host -- the page rendered, every registered asset was served off
 * disk, and precisely the imported sheets 404'd. Reported from a farm as "bloquée en raison
 * d'un type MIME (« text/html ») incorrect": the webserver's own error page, offered as a
 * stylesheet.
 *
 * Driven at the publishing layer, not through a server: this is about what ends up on disk,
 * before any request is made.
 */
class PublishedAssetClosureTest extends YesWikiTestCase
{
    private string $instance = '';
    private string $previousCwd = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->getWiki(); // for the path constants
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

    public function testTheSheetsAStylesheetImportsArePublishedWithIt(): void
    {
        $url = AssetPublisher::publishedUrl('styles/bazar/bazar.css', '1');

        $this->assertNotNull($url, 'the stylesheet itself');
        // styles/bazar/bazar.css opens with four `@import url('./entries/...')`
        foreach (['index.css', 'index-filters.css', 'form.css', 'view.css'] as $imported) {
            $this->assertFileExists(
                $this->published('styles/bazar/entries/' . $imported),
                $imported . ' is imported by bazar.css and must be published with it'
            );
        }
    }

    public function testAModulesStaticImportsArePublishedWithIt(): void
    {
        $source = \YESWIKI_SOURCE_DIR . '/javascripts/aceditor.js';
        if (!is_file($source)) {
            $this->markTestSkipped('no aceditor.js to read imports from');
        }
        AssetPublisher::publishedUrl('javascripts/aceditor.js', '1');

        // the ones the farm reported: aceditor.js imports them by relative path
        foreach (['link-panel.js', 'file-picker-panel.js', 'editor-rails.js'] as $imported) {
            if (!is_file(\YESWIKI_SOURCE_DIR . '/javascripts/' . $imported)) {
                continue;
            }
            $this->assertFileExists(
                $this->published('javascripts/' . $imported),
                $imported . ' is imported by aceditor.js'
            );
        }
    }

    /**
     * ...including transitively, and without looping on a cycle.
     */
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
     * Only relative references, and only what may be served: an absolute URL belongs to
     * whoever hosts it, and `url()` must not become a way to publish anything on disk.
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

    /**
     * An instance published by an older YesWiki has the sheets and not their imports. It gets
     * swept once, when anything is next published -- otherwise it would stay broken until the
     * next release changed the version.
     */
    public function testATreePublishedWithoutItsReferencesIsRepaired(): void
    {
        // publish the whole closure, then take the imports away again: an old instance
        AssetPublisher::publishedUrl('styles/bazar/bazar.css', '1');
        foreach (['index.css', 'index-filters.css', 'form.css', 'view.css'] as $imported) {
            unlink($this->published('styles/bazar/entries/' . $imported));
        }
        $marker = $this->instance . '/' . AssetPublisher::PUBLISHED_PREFIX . '1/.references-published';
        if (is_file($marker)) {
            unlink($marker);
        }

        // anything at all being published now triggers the sweep
        AssetPublisher::publishedUrl('styles/yw-core.css', '1');

        $this->assertFileExists($this->published('styles/bazar/entries/index.css'));
        $this->assertFileExists(
            $marker,
            'and it is marked, so the sweep does not run on every request'
        );
    }
}
