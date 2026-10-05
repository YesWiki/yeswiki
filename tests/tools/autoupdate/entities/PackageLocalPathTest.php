<?php

namespace YesWiki\Test\AutoUpdate\Entity;

use YesWiki\AutoUpdate\Entity\PackageTheme;
use YesWiki\AutoUpdate\Entity\PackageTool;
use YesWiki\AutoUpdate\Entity\Release;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * Extensions and themes install into the wiki being run, not where the autoupdate code lives,
 * which on a farm wiki is the master's folder through a symbolic link.
 */
class PackageLocalPathTest extends YesWikiTestCase
{
    private string $previousDir;
    private string $wikiDir;

    protected function setUp(): void
    {
        $GLOBALS['wiki'] = $this->getWiki();
        foreach ([PackageTool::class, PackageTheme::class, Release::class] as $class) {
            class_exists($class);
        }
        $this->previousDir = getcwd();
        $this->wikiDir = sys_get_temp_dir() . '/yeswiki_farm_wiki_' . uniqid();
        mkdir($this->wikiDir);
        file_put_contents($this->wikiDir . '/wakka.config.php', "<?php\n\$wakkaConfig = [];\n");
        chdir($this->wikiDir);
    }

    protected function tearDown(): void
    {
        chdir($this->previousDir);
        unlink($this->wikiDir . '/wakka.config.php');
        rmdir($this->wikiDir);
    }

    private function localPath(object $package): string
    {
        return (new \ReflectionProperty($package, 'localPath'))->getValue($package);
    }

    public function testAnExtensionInstallsIntoTheWikiBeingRun()
    {
        $package = new PackageTool('4.1.1', 'https://repository.example/doryphore/extension-qrcode-4.1.1.zip', '', '');

        $this->assertSame(realpath($this->wikiDir) . '/tools/qrcode/', $this->localPath($package));
        $this->assertFalse($package->installed);
    }

    public function testAThemeInstallsIntoTheWikiBeingRun()
    {
        $package = new PackageTheme('1.0.0', 'https://repository.example/doryphore/theme-someTheme-1.0.0.zip', '', '');

        $this->assertSame(realpath($this->wikiDir) . '/themes/someTheme/', $this->localPath($package));
    }
}
