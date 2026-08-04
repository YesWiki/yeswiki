<?php

namespace YesWiki\Test\Kernel;

use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * `yeswicli` runs where there is no wiki.
 *
 * It used to stop dead without a `yeswiki.config.php` -- "the command should be launched
 * from your YesWiki root directory" -- which is exactly backwards for the arrangement it
 * exists to serve: one source tree shared by every wiki on the server. That folder has no
 * config, because it is nobody's wiki, and the one thing anyone wants to do there is make
 * one.
 *
 * Run as a real subprocess rather than by including the script: the whole behaviour under
 * test is what the console decides from its working directory, which cannot be faked from
 * inside a test that is already running in this wiki.
 */
class ConsoleWithoutAWikiTest extends YesWikiTestCase
{
    /**
     * @return array{0: string, 1: int} stdout+stderr, exit code
     */
    private function console(string $workingDirectory, string $arguments = ''): array
    {
        // the path constants are defined by src/bootstrap_paths.php, which the wiki's own
        // boot runs -- this test never needs the wiki itself, only where it lives
        $this->getWiki();

        $command = sprintf(
            'cd %s && %s %s %s 2>&1',
            escapeshellarg($workingDirectory),
            escapeshellarg(PHP_BINARY),
            escapeshellarg(\YESWIKI_SOURCE_DIR . '/src/commands/console'),
            $arguments
        );
        exec($command, $lines, $status);

        return [implode("\n", $lines), $status];
    }

    public function testInAWikiItOffersEverything(): void
    {
        // this repo is a wiki: it has a config, so the whole set is there
        $this->getWiki();
        [$output, $status] = $this->console(\YESWIKI_INSTANCE_DIR, 'list');

        $this->assertSame(0, $status, $output);
        $this->assertStringContainsString('core:create-instance', $output);
        $this->assertStringContainsString('core:archive', $output, 'a command that needs the wiki');
        $this->assertStringNotContainsString('not a wiki', $output);
    }

    public function testWithoutAConfigItStillRunsAndSaysWhatIsMissing(): void
    {
        $folder = sys_get_temp_dir() . '/yeswiki-console-test-' . getmypid();
        if (!is_dir($folder) && !mkdir($folder, 0755, true)) {
            $this->markTestSkipped('could not make a folder to run the console in');
        }

        try {
            [$output, $status] = $this->console($folder, 'list');

            $this->assertSame(0, $status, 'no config is not an error: ' . $output);
            $this->assertStringContainsString(
                'core:create-instance',
                $output,
                'the one thing worth doing in a folder with no wiki'
            );
            // ...and nothing that would reach for a wiki that is not there
            $this->assertStringNotContainsString('core:archive', $output);
            $this->assertStringNotContainsString('search:reindex', $output);
        } finally {
            // bootstrap_paths.php provisions the data folders in whatever it is run from
            exec('rm -rf ' . escapeshellarg($folder));
        }
    }

    /**
     * A `yeswiki.config.php` that exists but configures nothing is not a wiki either.
     *
     * Reported from a real server: an install that never finished leaves the file behind,
     * and `file_exists()` was the whole test -- so the console fabricated `$_SERVER` from
     * a `base_url` that was not there (four warnings), booted anyway, and died connecting
     * to a database with no credentials. All from a command asked only to list itself.
     */
    public function testAConfigThatConfiguresNothingIsNotAWiki(): void
    {
        $folder = sys_get_temp_dir() . '/yeswiki-console-halfwritten-' . getmypid();
        if (!is_dir($folder) && !mkdir($folder, 0755, true)) {
            $this->markTestSkipped('could not make a folder to run the console in');
        }
        file_put_contents($folder . '/yeswiki.config.php', "<?php\n");

        try {
            [$output, $status] = $this->console($folder, 'list');

            $this->assertSame(0, $status, $output);
            $this->assertStringNotContainsString('Warning', $output, 'no reading of keys that are not there');
            $this->assertStringNotContainsString('Access denied', $output, 'and no connecting to nothing');
            $this->assertStringContainsString('configures no wiki', $output, 'it says what is wrong instead');
            $this->assertStringContainsString('core:create-instance', $output);
        } finally {
            exec('rm -rf ' . escapeshellarg($folder));
        }
    }

    /**
     * ...and what it offers there actually works: a wiki gets made, with a console of its
     * own pointing back at this source.
     */
    public function testItCanMakeAnInstanceFromAFolderWithNoWiki(): void
    {
        $folder = sys_get_temp_dir() . '/yeswiki-console-test-' . getmypid();
        $instance = $folder . '/wiki';
        if (!is_dir($folder) && !mkdir($folder, 0755, true)) {
            $this->markTestSkipped('could not make a folder to run the console in');
        }

        try {
            [$output, $status] = $this->console($folder, 'core:create-instance ' . escapeshellarg($instance));

            $this->assertSame(0, $status, $output);
            $this->assertFileExists($instance . '/index.php');
            $this->assertFileExists($instance . '/yeswicli', 'each wiki gets a console of its own');
            $this->assertStringContainsString(
                \YESWIKI_SOURCE_DIR,
                (string)file_get_contents($instance . '/index.php'),
                'and it loads this source tree'
            );
            foreach (['cache', 'custom', 'files', 'private'] as $dataFolder) {
                $this->assertDirectoryExists($instance . '/' . $dataFolder);
            }

            // the new wiki has no config either, so its own console says so rather than
            // dying -- it is a wiki waiting to be installed, not a source tree
            [$itsOwn, $itsStatus] = $this->console($instance, 'list');
            $this->assertSame(0, $itsStatus, $itsOwn);
            $this->assertStringContainsString('not installed yet', $itsOwn);
        } finally {
            exec('rm -rf ' . escapeshellarg($folder));
        }
    }
}
