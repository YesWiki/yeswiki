<?php

namespace YesWiki\Test\Kernel;

use YesWiki\Kernel\Service\ComposerManifest;
use YesWiki\Kernel\Service\HealthService;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** Ticket 52 / 53: the checks core declares, and what the migrations ask for by name. */
class HealthChecksWiringTest extends YesWikiTestCase
{
    private static function health(): HealthService
    {
        return self::getWiki()->services->get(HealthService::class);
    }

    /** A module enrols by implementing the interface and nothing else -- there is no list of providers. */
    public function testTheDeclaredChecksReachTheRegistry(): void
    {
        $ids = self::health()->ids();

        $this->assertContains('php-version', $ids);
        $this->assertContains('required-extensions', $ids);
        $this->assertContains('runtime-free-space', $ids);
        $this->assertContains('bucket-reachable', $ids);
        $this->assertContains('ext-intl', $ids, 'Search declares the extension it degrades without');
        $this->assertContains('core-update', $ids);
    }

    /**
     * `reportCheck('...')` in a migration silently reports nothing when the name is wrong, so the
     * names the migrations use are asserted here rather than discovered on somebody's upgrade.
     */
    public function testEveryFindingAMigrationRunsIsDeclaredBySomeModule(): void
    {
        $ids = self::health()->ids();

        foreach ([
            'themes-call-retired-search-actions',
            'files-name-renamed-actions',
            'leftover-tools-directory',
            'pages-override-retired-chrome',
        ] as $named) {
            $this->assertContains($named, $ids);
        }
    }

    /** The requirements are composer.json's, and a second copy would rot within a release. */
    public function testTheRequirementsAreReadFromComposerJson(): void
    {
        $manifest = self::getWiki()->services->get(ComposerManifest::class);

        $this->assertSame('^8.3', $manifest->phpConstraint());
        $this->assertSame('8.3.0', $manifest->minimumPhpVersion());
        $this->assertContains('gd', $manifest->requiredExtensions());
        $this->assertContains('json', $manifest->requiredExtensions());
        $this->assertNotContains('intl', $manifest->requiredExtensions(), 'intl is a suggestion, not a requirement');
    }

    /** Every optional extension a module names carries composer.json's own sentence about it. */
    public function testAnOptionalExtensionCarriesItsConsequence(): void
    {
        $suggested = self::getWiki()->services->get(ComposerManifest::class)->suggestedExtensions();

        foreach (['intl', 'imap', 'zend-opcache'] as $extension) {
            $this->assertArrayHasKey($extension, $suggested);
            $this->assertNotSame('', $suggested[$extension]);
        }
    }
}
