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

        // the ones the farm reported: aceditor.js imports them by relative path, and
        // mode-yeswiki.js is imported by ace-wrapper.js rather than fetched by ACE's own
        // loader -- a module fetched by a URL built at runtime is one nothing can publish
        foreach (['link-panel.js', 'file-picker-panel.js', 'editor-rails.js', 'mode-yeswiki.js'] as $imported) {
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
     * A source file that changed is published again, release string or not.
     *
     * Freshness used to be keyed on the version: anything but `dev` was taken as final and
     * the published copy was never looked at again. That is right for a released instance
     * and wrong for every instance following a branch -- and those are the ones where a fix
     * is pulled and nothing changes, because the wiki goes on serving the copy it published
     * the first time.
     */
    public function testAChangedSourceFileIsPublishedAgainUnderTheSameVersion(): void
    {
        $dir = $this->instance . '/custom/closure';
        mkdir($dir, 0755, true);
        file_put_contents($dir . '/changing.css', ".before { color: red }\n");
        AssetPublisher::publishedUrl('custom/closure/changing.css', '1');
        $this->assertStringContainsString('.before', (string)file_get_contents($this->published('custom/closure/changing.css')));

        file_put_contents($dir . '/changing.css', ".after { color: blue }\n");
        touch($dir . '/changing.css', time() + 2); // an edit a second after the copy
        AssetPublisher::publishedUrl('custom/closure/changing.css', '1');

        $this->assertStringContainsString(
            '.after',
            (string)file_get_contents($this->published('custom/closure/changing.css')),
            'the published copy must follow the source'
        );
    }

    /**
     * ...and the URL moves with it, or no browser will ever ask for the new copy.
     *
     * Published assets are served `immutable, max-age=1 year`: the version folder in the path
     * IS the cache key. Keyed on the release string alone it never moves on an instance
     * following a branch -- the update lands on disk, the URL stays, and every returning
     * visitor goes on running the code they cached. A stamp records that the sources moved on
     * and lands in the next request's URLs.
     */
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
        AssetPublisher::publishedUrl('custom/closure/moving.css', '1'); // the request that notices

        $stamp = AssetPublisher::publishedStamp();
        $this->assertNotSame('', $stamp, 'the sources moving on is recorded');

        // the next request asks under the stamped version, which no browser has seen
        $second = AssetPublisher::publishedUrl('custom/closure/moving.css', '1-' . $stamp);
        $this->assertNotSame($first, $second);
        $this->assertStringContainsString(
            '.after',
            (string)file_get_contents($this->instance . '/' . $second),
            'and it serves the new content'
        );
    }

    /**
     * An instance published by a YesWiki that had no stamp gets one from what is on disk.
     *
     * This is the state the first attempt left behind, and it could not get out of it: the
     * stamp was written when a published file was *caught* going stale, and an instance whose
     * copies had already been refreshed had nothing stale left to catch. No stamp, so the URL
     * never moved, so every browser kept serving the modules it had cached -- for good, since
     * no second update to those files was coming. Published copies carry their source's
     * mtime, so the tree can say how old it is without being asked at the right moment.
     */
    public function testATreeWithNoStampGetsOneFromItsOwnFiles(): void
    {
        AssetPublisher::publishedUrl('styles/yw-core.css', '1');
        $stampFile = $this->instance . '/' . AssetPublisher::PUBLISHED_PREFIX . '.sources-changed';
        $this->assertFileExists($stampFile);

        // an instance published before any of this existed: files current, no stamp
        unlink($stampFile);

        $stamp = AssetPublisher::publishedStamp();

        $this->assertNotSame('', $stamp, 'read off the published files themselves');
        $this->assertSame(
            (string)filemtime(\YESWIKI_SOURCE_DIR . '/styles/yw-core.css'),
            $stamp,
            'and it is how new the published set is'
        );
        $this->assertFileExists($stampFile, 'written down, so the walk happens once');
    }

    /**
     * No file of ours points above the source tree.
     *
     * `javascripts/entries-index-dynamic.js` opened with
     * `import Panel from '../../../../javascripts/shared-components/Panel.js'` -- four levels
     * up, correct back when the file lived in `tools/bazar/presentation/javascripts/` and
     * never corrected when it moved. Browsers clamp the extra `..` at the site root, so on a
     * standalone install it kept resolving to the same file and nothing ever complained.
     *
     * On a farm it does not: from `cache/assets/{version}/javascripts/` those four levels
     * land exactly on the site root, and the import goes to the unversioned source path --
     * a path that only answers where the webserver sends misses to index.php. Reported as
     * `/ecto/javascripts/shared-components/Panel.js` blocked for its MIME type.
     */
    public function testNoAssetOfOursReferencesSomethingAboveTheSourceTree(): void
    {
        $root = \YESWIKI_SOURCE_DIR;
        $tree = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root . '/javascripts', \FilesystemIterator::SKIP_DOTS));

        $broken = [];
        foreach ($tree as $file) {
            $path = (string)$file;
            // vendored builds are somebody else's business, and are not ES modules anyway
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
