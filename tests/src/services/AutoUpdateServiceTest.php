<?php

namespace YesWiki\Test\Core\Service;

use PHPUnit\Framework\Attributes\CoversMethod;
use YesWiki\Admin\Entity\PackageCore;
use YesWiki\Admin\Entity\PackageTheme;
use YesWiki\Admin\Entity\PackageTool;
use YesWiki\Admin\Entity\Release;
use YesWiki\Admin\Service\AutoUpdateService;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * Regression test for ticket 19 (autoupdate absorbed into core, ADR-0007): unifies PackageCore/PackageTool/PackageTheme's install-target path resolution onto YESWIKI_PROGRAM_DIR (previously PackageCore alone resolved via the REQUESTING instance's own docroot, silently diverging from the other two on a farm setup where instance dir != source dir), and adds the new "designated update-triggering instance" authorization check.
 */
#[CoversMethod(AutoUpdateService::class, 'isDesignatedUpdateInstance')]
class AutoUpdateServiceTest extends YesWikiTestCase
{
    public function testIsDesignatedUpdateInstanceDefaultsToTrueOnThisStandaloneTestEnvironment(): void
    {
        $wiki = $this->getWiki();
        $service = $wiki->services->get(AutoUpdateService::class);

        $this->assertTrue($service->isDesignatedUpdateInstance());
    }

    public function testIsDesignatedUpdateInstanceTrueWhenPathsMatch(): void
    {
        $wiki = $this->getWiki();
        $service = $wiki->services->get(AutoUpdateService::class);

        $this->assertTrue($service->isDesignatedUpdateInstance(sys_get_temp_dir(), sys_get_temp_dir()));
    }

    public function testIsDesignatedUpdateInstanceFalseForASimulatedFarmSatellite(): void
    {
        $wiki = $this->getWiki();
        $service = $wiki->services->get(AutoUpdateService::class);

        $instanceDir = sys_get_temp_dir() . '/AutoUpdateServiceTest-instance-' . uniqid();
        $programDir = sys_get_temp_dir() . '/AutoUpdateServiceTest-source-' . uniqid();
        mkdir($instanceDir);
        mkdir($programDir);

        try {
            $this->assertFalse($service->isDesignatedUpdateInstance($instanceDir, $programDir));
        } finally {
            rmdir($instanceDir);
            rmdir($programDir);
        }
    }

    public function testPackageToolAndPackageThemeLocalPathResolveUnderProgramDir(): void
    {
        $programDir = YESWIKI_PROGRAM_DIR;
        if ($programDir === '') {
            $this->fail('the Program directory must be stated');
        }

        foreach ([PackageTool::class, PackageTheme::class] as $class) {
            $package = (new \ReflectionClass($class))->newInstanceWithoutConstructor();
            $nameProperty = new \ReflectionProperty($class, 'name');
            $nameProperty->setValue($package, 'somepackage');

            $localPathMethod = new \ReflectionMethod($class, 'localPath');
            $localPath = $localPathMethod->invoke($package);

            $this->assertStringStartsWith($programDir, $localPath, "$class::localPath() should resolve under YESWIKI_PROGRAM_DIR");
        }
    }

    public function testPackageCoreLocalPathIsProgramDirNotRequestingInstanceDir(): void
    {
        $package = new PackageCore(new Release('9999-01-01-1'), 'yeswiki-test-2020-01-01-1.zip', 'desc', 'doc');

        $localPathProperty = new \ReflectionProperty(PackageCore::class, 'localPath');

        $this->assertSame(YESWIKI_PROGRAM_DIR, $localPathProperty->getValue($package));
    }
}
