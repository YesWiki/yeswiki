<?php

namespace YesWiki\Test\Kernel;

use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * A worker has to be able to tell that its compiled container has been deleted (ADR-0024).
 *
 * `worker.php` boots once and serves hundreds of requests, and the container it booted on resolves
 * each service by requiring a file per service. Anything that empties `cache/container` --
 * `cache:clear`, `migrate`, the browser suite's own reset -- leaves that worker unable to build
 * another service for the rest of its life, with every page failing on whichever service it
 * reaches for first. The loop asks between requests and stops instead.
 *
 * The answer is derived by reflection from the container class, so this test is what notices if
 * Symfony ever dumps it somewhere else: a null directory is a worker that never restarts, and
 * nothing else would say so.
 */
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
}
