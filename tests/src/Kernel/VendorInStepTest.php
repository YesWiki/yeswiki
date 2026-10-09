<?php

namespace YesWiki\Test\Kernel;

use YesWiki\Kernel\Service\ComposerManifest;
use YesWiki\Kernel\Service\HealthService;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** A vendor/ left behind by an update that did not run `composer install` is reported, not discovered on a crashed page. */
class VendorInStepTest extends YesWikiTestCase
{
    /** @param array<string, string> $versions */
    private static function packages(array $versions, bool $wrapped = true): string
    {
        $packages = [];
        foreach ($versions as $name => $version) {
            $packages[] = ['name' => $name, 'version' => $version];
        }

        return (string)json_encode($wrapped ? ['packages' => $packages] : $packages);
    }

    public function testAVendorMatchingTheLockIsInStep(): void
    {
        $this->assertSame([], ComposerManifest::outOfStep(
            self::packages(['twig/twig' => 'v3.21.1', 'altcha-org/altcha' => 'v2.3.0']),
            self::packages(['altcha-org/altcha' => 'v2.3.0', 'twig/twig' => 'v3.21.1', 'phpunit/phpunit' => '12.5.35'])
        ));
    }

    public function testAMissingOrStalePackageIsNamed(): void
    {
        $this->assertSame(
            ['altcha-org/altcha' => null, 'twig/twig' => 'v3.20.0'],
            ComposerManifest::outOfStep(
                self::packages(['twig/twig' => 'v3.21.1', 'altcha-org/altcha' => 'v2.3.0']),
                self::packages(['twig/twig' => 'v3.20.0'])
            )
        );
    }

    public function testTheInstalledFormatOfComposerOneIsReadToo(): void
    {
        $this->assertSame([], ComposerManifest::outOfStep(
            self::packages(['twig/twig' => 'v3.21.1']),
            self::packages(['twig/twig' => 'v3.21.1'], false)
        ));
    }

    public function testNoLockMeansNothingToCompare(): void
    {
        $this->assertSame([], ComposerManifest::outOfStep('', self::packages(['twig/twig' => 'v3.21.1'])));
    }

    public function testTheCheckIsDeclaredAndThisCheckoutPassesIt(): void
    {
        $health = self::getWiki()->services->get(HealthService::class);

        $this->assertContains('vendor-in-step', $health->ids());
        $this->assertSame([], self::getWiki()->services->get(ComposerManifest::class)->packagesOutOfStep());
    }
}
