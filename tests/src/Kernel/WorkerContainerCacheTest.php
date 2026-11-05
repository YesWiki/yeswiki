<?php

namespace YesWiki\Test\Kernel;

use YesWiki\Core\YesWikiKernel;
use YesWiki\Files\Service\Storage;
use YesWiki\Kernel\Service\CacheClearer;
use YesWiki\Kernel\Service\ConfigurationFileProvider;
use YesWiki\Kernel\Service\EnvironmentConfiguration;
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
        $booted = new \ReflectionProperty($wiki, 'bootedCacheDir');
        $was = $booted->getValue($wiki);
        $booted->setValue($wiki, (new YesWikiKernel($wiki, $wiki->getEnvironment()))->getCacheDir());

        $name = (string)array_key_first(EnvironmentConfiguration::knownEnvNames());
        $value = getenv($name);
        try {
            $this->assertFalse($wiki->configurationChanged(), 'nothing changed since this worker booted');
            putenv($name . '=changed-under-a-running-worker');
            $this->assertTrue($wiki->configurationChanged());
        } finally {
            putenv($value === false ? $name : $name . '=' . $value);
            $booted->setValue($wiki, $was);
        }
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
        $this->assertFalse($wiki->configurationChanged(), 'a wiki boots on the hash of what it loaded');
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
}
