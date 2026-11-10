<?php

namespace YesWiki\Test\Kernel;

use PHPUnit\Framework\TestCase;

/** Replicas 09: work that outlives a request is a message, so `startConsoleAsync()` is called only by commands that start each other. */
class NoRequestStartsAJobTest extends TestCase
{
    public function testOnlyCommandsStartAConsoleOfTheirOwn(): void
    {
        $callers = [];
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator('src', \FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            $path = str_replace('\\', '/', $file->getPathname());
            if ($file->getExtension() !== 'php' || str_contains($path, '/Command/') || str_ends_with($path, 'src/Kernel/Service/ConsoleService.php')) {
                continue;
            }
            if (str_contains((string)file_get_contents($path), '->startConsoleAsync(')) {
                $callers[] = $path;
            }
        }

        $this->assertSame([], $callers, 'a request that starts a child process loses it with its pod: dispatch a message instead');
    }
}
