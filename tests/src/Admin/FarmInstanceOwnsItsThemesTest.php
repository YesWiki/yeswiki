<?php

namespace YesWiki\Test\Admin;

use PHPUnit\Framework\Attributes\CoversMethod;
use YesWiki\Admin\Entity\PackageTheme;
use YesWiki\Admin\Entity\PackageTool;
use YesWiki\Admin\Service\AutoUpdateService;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** Ticket 46: a farm instance installs its own theme, and upgrades its own extensions. */
#[CoversMethod(AutoUpdateService::class, 'mayUpgrade')]
#[CoversMethod(PackageTheme::class, 'localPath')]
class FarmInstanceOwnsItsThemesTest extends YesWikiTestCase
{
    /** The install target of a package, as decided by a PHP that believes it is a farm instance. */
    private function localPathOn(string $class, string $instanceDir): string
    {
        $program = \YESWIKI_PROGRAM_DIR;
        $script = tempnam(sys_get_temp_dir(), 'yw-farm-') . '.php';
        file_put_contents($script, <<<PHP
        <?php
        require {$this->quoted($program . '/src/bootstrap_paths.php')};
        require {$this->quoted($program . '/vendor/autoload.php')};

        \$package = (new ReflectionClass({$this->quoted($class)}))->newInstanceWithoutConstructor();
        \$name = new ReflectionProperty({$this->quoted($class)}, 'name');
        \$name->setValue(\$package, 'sometheme');
        \$localPath = new ReflectionMethod({$this->quoted($class)}, 'localPath');
        echo \$localPath->invoke(\$package);
        PHP);

        $out = [];
        $status = 0;
        exec(
            'YESWIKI_INSTANCE_DIR=' . escapeshellarg($instanceDir)
            . ' ' . escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($script) . ' 2>&1',
            $out,
            $status
        );
        unlink($script);
        $this->assertSame(0, $status, implode("\n", $out));

        return implode("\n", $out);
    }

    private function quoted(string $value): string
    {
        return var_export($value, true);
    }

    private function makeInstance(string $suffix): string
    {
        $dir = (string)realpath((string)sys_get_temp_dir()) . '/yw-farm-' . $suffix . '-' . getmypid();
        mkdir($dir, 0o755, true);

        return $dir;
    }

    private function removeInstance(string $dir): void
    {
        foreach (['cache', 'custom', 'files', 'private'] as $folder) {
            @rmdir($dir . '/' . $folder);
        }
        @rmdir($dir);
    }

    public function testAFarmInstanceInstallsAThemeIntoItsOwnCustomThemes(): void
    {
        $instance = $this->makeInstance('theme');
        try {
            $this->assertSame(
                $instance . '/custom/themes/sometheme/',
                $this->localPathOn(PackageTheme::class, $instance),
                'a theme an instance installs belongs to that instance, not to the code every instance runs'
            );
        } finally {
            $this->removeInstance($instance);
        }
    }

    /** The behaviour PackageTheme is being made to match. */
    public function testAnExtensionAlreadyWorkedThatWay(): void
    {
        $instance = $this->makeInstance('tool');
        try {
            $this->assertSame(
                $instance . '/custom/extensions/sometheme/',
                $this->localPathOn(PackageTool::class, $instance)
            );
        } finally {
            $this->removeInstance($instance);
        }
    }

    /** One wiki, one tree: nothing changes for a standalone install. */
    public function testAStandaloneInstallStillInstallsIntoTheProgram(): void
    {
        $this->assertSame(
            \YESWIKI_PROGRAM_DIR . '/themes/sometheme/',
            $this->localPathOn(PackageTheme::class, \YESWIKI_PROGRAM_DIR)
        );
    }

    public function testAFarmInstanceMayUpgradeAnExtensionButNotTheCore(): void
    {
        $service = $this->getWiki()->services->get(AutoUpdateService::class);

        $instance = $this->makeInstance('gate');
        $program = $this->makeInstance('program');

        try {
            $this->assertFalse(
                $service->mayUpgrade('yeswiki', $instance, $program),
                'core mutates the shared Program, which is what ADR-0007 gates'
            );
            $this->assertTrue(
                $service->mayUpgrade('some-extension', $instance, $program),
                'an extension installs into this instance alone, so no farm-wide authority is needed'
            );
            $this->assertTrue($service->mayUpgrade('some-theme', $instance, $program));
        } finally {
            $this->removeInstance($instance);
            $this->removeInstance($program);
        }
    }

    public function testAStandaloneInstallMayStillUpgradeTheCore(): void
    {
        $service = $this->getWiki()->services->get(AutoUpdateService::class);

        $this->assertTrue($service->mayUpgrade('yeswiki'));
    }
}
