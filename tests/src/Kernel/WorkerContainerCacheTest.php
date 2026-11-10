<?php

namespace YesWiki\Test\Kernel;

use YesWiki\Core\YesWikiKernel;
use YesWiki\Core\YesWikiRuntime;
use YesWiki\Files\Service\Storage;
use YesWiki\Kernel\Service\CacheClearer;
use YesWiki\Kernel\Service\ConfigurationFileProvider;
use YesWiki\Kernel\Service\ConfigurationWatch;
use YesWiki\Kernel\Service\EnvironmentConfiguration;
use YesWiki\Kernel\Service\WikiSettings;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** A worker has to be able to tell that its compiled container has been deleted (ADR-0024). */
class WorkerContainerCacheTest extends YesWikiTestCase
{
    public function testABootedWikiKnowsWhereItsContainerWasDumped(): void
    {
        $wiki = $this->getWiki();

        $directory = new \ReflectionProperty($wiki, 'containerDirectory');
        $directory->setAccessible(true);
        $path = $directory->getValue($wiki);

        $this->assertIsString($path, 'the container directory could not be derived at all');
        $this->assertDirectoryExists($path);
        $this->assertNotEmpty(
            glob($path . '/get*Service.php'),
            'that is not the directory the container requires its services from'
        );
    }

    public function testAWikiWhoseContainerIsStillThereKeepsServing(): void
    {
        $this->assertFalse($this->getWiki()->containerCacheIsGone());
    }

    public function testAWikiWhoseContainerHasGoneSaysSo(): void
    {
        $wiki = $this->getWiki();

        $directory = new \ReflectionProperty($wiki, 'containerDirectory');
        $directory->setAccessible(true);
        $was = $directory->getValue($wiki);

        $directory->setValue($wiki, sys_get_temp_dir() . '/a-container-directory-that-was-cleared');
        try {
            $this->assertTrue($wiki->containerCacheIsGone());
        } finally {
            $directory->setValue($wiki, $was);
        }
    }

    /** A saved configuration builds a new container elsewhere, so the old one is still there and only the fingerprint tells. */
    public function testAWikiWhoseConfigurationChangedSaysSo(): void
    {
        $wiki = $this->getWiki();

        $this->withBootedCacheDir($wiki, function () use ($wiki): void {
            $this->assertFalse($wiki->configurationChanged(), 'nothing changed since this worker booted');
            $this->withChangedEnvironment(fn () => $this->assertTrue($wiki->configurationChanged()));
        });
    }

    /** A blocking queue consumer is told the same thing, through the ConfigurationWatch the runtime binds at boot. */
    public function testTheConfigurationWatchAsksTheRuntime(): void
    {
        $wiki = $this->getWiki();
        $watch = $wiki->services->get(ConfigurationWatch::class);

        $this->withBootedCacheDir($wiki, function () use ($watch): void {
            $this->assertFalse($watch->changedSinceBoot());
            $this->withChangedEnvironment(fn () => $this->assertTrue($watch->changedSinceBoot()));
        });
    }

    public function testAWatchBuiltByHandNeverReportsAChange(): void
    {
        $this->withChangedEnvironment(fn () => $this->assertFalse((new ConfigurationWatch())->changedSinceBoot(), 'unbound, as a service built by hand is: never changed'));
    }

    /**
     * An upgrade (`git pull && migrate`) or a `cache:clear` empties the container cache with the configuration unchanged: the consumer then runs old classes against a new schema, and is told to restart as FrankenPHP's worker is.
     */
    public function testTheConfigurationWatchSaysSoWhenTheContainerIsGone(): void
    {
        $wiki = $this->getWiki();
        $watch = $wiki->services->get(ConfigurationWatch::class);
        $directory = new \ReflectionProperty($wiki, 'containerDirectory');
        $wasDirectory = $directory->getValue($wiki);

        $this->withBootedCacheDir($wiki, function () use ($watch, $wiki, $directory, $wasDirectory): void {
            try {
                $this->assertFalse($watch->changedSinceBoot());
                $directory->setValue($wiki, sys_get_temp_dir() . '/a-container-directory-that-was-cleared');
                $this->assertTrue($watch->changedSinceBoot(), 'the configuration is the same, the code may not be');
            } finally {
                $directory->setValue($wiki, $wasDirectory);
            }
        });
    }

    /** A request that read the configuration before a save must not cache its old values under the new file's hash. */
    public function testAContainerIsCachedUnderTheConfigurationItWasBuiltFrom(): void
    {
        $wiki = $this->getWiki();
        $onDisk = new YesWikiKernel($wiki, 'test');
        $readBeforeTheSave = new YesWikiKernel($wiki, 'test', md5('the configuration before the save'));
        $readJustNow = new YesWikiKernel($wiki, 'test', (string)md5_file(ConfigurationFileProvider::getConfigFileFromEnv()));

        $this->assertNotSame($onDisk->getCacheDir(), $readBeforeTheSave->getCacheDir());
        $this->assertSame($onDisk->getCacheDir(), $readJustNow->getCacheDir());
        $this->onTheSettingsAsTheyAre($wiki, fn () => $this->assertFalse($wiki->configurationChanged(), 'a wiki boots on the hash of what it loaded'));
    }

    /** Twig keeps a compiled template's class in memory, so an override saved elsewhere is only seen by a fresh worker. */
    public function testAWikiWhoseTemplatesWereClearedSaysSo(): void
    {
        $wiki = $this->getWiki();
        $stamp = new \ReflectionProperty($wiki, 'templatesStamp');
        $was = $stamp->getValue($wiki);
        $current = @file_get_contents(CacheClearer::TEMPLATES_STAMP);
        $stamp->setValue($wiki, $current === false ? '' : $current);

        try {
            $this->assertFalse($wiki->templatesChanged(), 'nothing was cleared since this worker booted');
            CacheClearer::stampTemplatesCleared(new Storage());
            $this->assertTrue($wiki->templatesChanged());
        } finally {
            $stamp->setValue($wiki, $was);
        }
    }

    /** Runs $test as a worker that booted on the configuration as it is now would see it, then puts the wiki back. */
    private function withBootedCacheDir(YesWikiRuntime $wiki, callable $test): void
    {
        $booted = new \ReflectionProperty($wiki, 'bootedCacheDir');
        $was = $booted->getValue($wiki);
        $booted->setValue($wiki, (new YesWikiKernel($wiki, $wiki->getEnvironment()))->getCacheDir());

        try {
            $this->onTheSettingsAsTheyAre($wiki, $test);
        } finally {
            $booted->setValue($wiki, $was);
        }
    }

    /** Runs $test as a worker that read the wiki settings as they are now, whatever another test stored since this one booted. */
    private function onTheSettingsAsTheyAre(YesWikiRuntime $wiki, callable $test): void
    {
        $settings = new \ReflectionProperty($wiki, 'settingsVersion');
        $was = $settings->getValue($wiki);
        $settings->setValue($wiki, $wiki->services->get(WikiSettings::class)->freshVersion());

        try {
            $test();
        } finally {
            $settings->setValue($wiki, $was);
        }
    }

    /** Runs $test with one of the wiki's environment settings changed, then puts it back. */
    private function withChangedEnvironment(callable $test): void
    {
        $name = (string)array_key_first(EnvironmentConfiguration::knownEnvNames());
        $value = getenv($name);
        putenv($name . '=changed-under-a-running-worker');

        try {
            $test();
        } finally {
            putenv($value === false ? $name : $name . '=' . $value);
        }
    }
}
