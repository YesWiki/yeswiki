<?php

namespace YesWiki\Test\Core\Service;

use YesWiki\Core\Service\AssetsManager;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * Every module a page's modules import gets the same versioned URL as a script tag, so a browser cannot mix versions.
 */
class AssetsManagerImportMapTest extends YesWikiTestCase
{
    private const DIRECTORY = 'custom/assets-manager-import-map-test';

    private $wiki;
    private $savedJs;

    protected function setUp(): void
    {
        $this->wiki = $this->getWiki();
        $GLOBALS['wiki'] = $this->wiki;
        $this->savedJs = $GLOBALS['js'] ?? null;
        $GLOBALS['js'] = '';
        $this->assertDirectoryDoesNotExist(self::DIRECTORY);
        mkdir(self::DIRECTORY . '/lib', 0777, true);
        $this->write('entry.js', "import { b } from './lib/b.js'\nimport './lib/side-effect.js'\nexport const url = import.meta.resolve('./lib/resolved.js')\n");
        $this->write('lib/b.js', "export { c as b } from '../c.js'\n");
        $this->write('c.js', "export const c = () => import('./lib/lazy.js')\nimport 'some-package'\n");
        $this->write('lib/side-effect.js', '');
        $this->write('lib/lazy.js', '');
        $this->write('lib/resolved.js', '');
        $this->write('unrelated.js', '');
    }

    protected function tearDown(): void
    {
        foreach (['lib/b.js', 'lib/side-effect.js', 'lib/lazy.js', 'lib/resolved.js', 'entry.js', 'c.js', 'unrelated.js'] as $file) {
            @unlink(self::DIRECTORY . '/' . $file);
        }
        @rmdir(self::DIRECTORY . '/lib');
        @rmdir(self::DIRECTORY);
        $GLOBALS['js'] = $this->savedJs;
    }

    private function write(string $file, string $source): void
    {
        file_put_contents(self::DIRECTORY . '/' . $file, $source);
    }

    private function importsOfMap(string $map): array
    {
        $this->assertMatchesRegularExpression('#^<script type="importmap">.*</script>\s*$#s', $map);

        return json_decode(preg_replace('#^<script type="importmap">|</script>\s*$#', '', $map), true)['imports'];
    }

    public function testTheMapListsWhatTheEntryReachesWithTheUrlsOfScriptTags()
    {
        $assets = new AssetsManager($this->wiki);
        $assets->AddJavascriptFile(self::DIRECTORY . '/entry.js', false, true);

        $imports = $this->importsOfMap($assets->importMap());

        $expected = [];
        foreach (['c.js', 'lib/b.js', 'lib/lazy.js', 'lib/resolved.js', 'lib/side-effect.js'] as $file) {
            $path = self::DIRECTORY . '/' . $file;
            $expected["{$this->wiki->getBaseUrl()}/$path"] = "{$this->wiki->getBaseUrl()}/$path?v=" . filemtime($path);
        }
        $this->assertSame($expected, $imports);
        $this->assertStringContainsString("src='{$assets->versionedUrl(self::DIRECTORY . '/entry.js')}'", $GLOBALS['js']);
    }

    public function testAnImportAddedToAModuleIsMappedOnTheNextPage()
    {
        (new AssetsManager($this->wiki))->AddJavascriptFile(self::DIRECTORY . '/unrelated.js', false, true);
        $this->write('unrelated.js', "import './lib/lazy.js'\n");
        touch(self::DIRECTORY . '/unrelated.js', time() + 5);
        clearstatcache();

        $assets = new AssetsManager($this->wiki);
        $assets->AddJavascriptFile(self::DIRECTORY . '/unrelated.js', false, true);

        $this->assertSame(["{$this->wiki->getBaseUrl()}/" . self::DIRECTORY . '/lib/lazy.js'], array_keys($this->importsOfMap($assets->importMap())));
    }

    public function testAnInlineModuleImportingFromTheWikiRootIsMapped()
    {
        $basePath = rtrim((string)parse_url($this->wiki->getBaseUrl(), PHP_URL_PATH), '/');
        $assets = new AssetsManager($this->wiki);
        $assets->AddJavascript("import { b } from '$basePath/" . self::DIRECTORY . "/lib/b.js'", true);

        $this->assertSame([
            "{$this->wiki->getBaseUrl()}/" . self::DIRECTORY . '/c.js',
            "{$this->wiki->getBaseUrl()}/" . self::DIRECTORY . '/lib/b.js',
            "{$this->wiki->getBaseUrl()}/" . self::DIRECTORY . '/lib/lazy.js',
        ], array_keys($this->importsOfMap($assets->importMap())));
    }

    public function testAPageWithoutModulesGetsNoMap()
    {
        $assets = new AssetsManager($this->wiki);
        $assets->AddJavascriptFile(self::DIRECTORY . '/entry.js');

        $this->assertSame('', $assets->importMap());
    }
}
