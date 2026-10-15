<?php

namespace YesWiki\Test\Kernel;

use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** `yeswicli` runs where there is no wiki. */
class ConsoleWithoutAWikiTest extends YesWikiTestCase
{
    /**
     * @return array{0: string, 1: int} stdout+stderr, exit code
     */
    private function console(string $workingDirectory, string $arguments = ''): array
    {
        $this->getWiki();

        $command = sprintf(
            'cd %s && %s %s %s 2>&1',
            escapeshellarg($workingDirectory),
            escapeshellarg(PHP_BINARY),
            escapeshellarg(\YESWIKI_PROGRAM_DIR . '/src/commands/console'),
            $arguments
        );
        exec($command, $lines, $status);

        return [implode("\n", $lines), $status];
    }

    public function testInAWikiItOffersEverything(): void
    {
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

            $this->assertStringNotContainsString('core:archive', $output);
            $this->assertStringNotContainsString('search:reindex', $output);
        } finally {
            exec('rm -rf ' . escapeshellarg($folder));
        }
    }

    /** A `yeswiki.config.php` that exists but configures nothing is not a wiki either. */
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

    /** A config whose database is gone says so and exits non-zero, rather than exiting 0 having run nothing. */
    public function testAWikiThatDoesNotBootIsAnError(): void
    {
        $folder = sys_get_temp_dir() . '/yeswiki-console-unbootable-' . getmypid();
        if (!is_dir($folder) && !mkdir($folder, 0755, true)) {
            $this->markTestSkipped('could not make a folder to run the console in');
        }

        $config = [
            'db_driver' => 'sqlite',
            'db_database' => 'not-a-database.sqlite',
            'table_prefix' => 'yeswiki_',
            'base_url' => 'http://console.test/?',
            'root_page' => 'PagePrincipale',
        ];
        file_put_contents($folder . '/yeswiki.config.php', "<?php\n\n\$yeswikiConfig = " . var_export($config, true) . ";\n");
        file_put_contents($folder . '/not-a-database.sqlite', "not a database\n");

        try {
            [$output, $status] = $this->console($folder, 'list');

            $this->assertNotSame(0, $status, 'a console that could not boot the wiki reported success: ' . $output);
            $this->assertStringContainsString('does not boot', $output);
        } finally {
            exec('rm -rf ' . escapeshellarg($folder));
        }
    }

    /**
     * ...and what it offers there actually works: a wiki gets made, with a console of its own pointing back at this source.
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
                \YESWIKI_PROGRAM_DIR,
                (string)file_get_contents($instance . '/index.php'),
                'and it loads this program tree'
            );
            foreach (['cache', 'custom', 'files', 'private'] as $dataFolder) {
                $this->assertDirectoryExists($instance . '/' . $dataFolder);
            }

            [$itsOwn, $itsStatus] = $this->console($instance, 'list');
            $this->assertSame(0, $itsStatus, $itsOwn);
            $this->assertStringContainsString('not installed yet', $itsOwn);
        } finally {
            exec('rm -rf ' . escapeshellarg($folder));
        }
    }

    /** A wiki installed with its own copy of YesWiki becomes an instance of this program, its data and own extensions kept. */
    public function testItConvertsAFullInstallIntoAnInstance(): void
    {
        $folder = sys_get_temp_dir() . '/yeswiki-convert-test-' . getmypid();
        $wiki = $folder . '/wiki';
        $backup = $folder . '/backup';
        try {
            foreach (['src/Kernel', 'vendor', 'extensions/lms', 'extensions/mine', 'themes/yeswiki', 'themes/montheme', 'files', 'custom', 'cache/container'] as $dir) {
                mkdir($wiki . '/' . $dir, 0755, true);
            }
            copy(\YESWIKI_PROGRAM_DIR . '/composer.json', $wiki . '/composer.json');
            file_put_contents($wiki . '/index.php', '<?php require "src/YesWikiInit.php";');
            file_put_contents($wiki . '/yeswiki.config.php', '<?php $wakkaConfig = [];');
            file_put_contents($wiki . '/files/photo.jpg', 'jpg');
            file_put_contents($wiki . '/google123.html', 'verification');
            file_put_contents($wiki . '/cache/container/compiled.php', '<?php');

            [$dryRun, $dryStatus] = $this->console($folder, 'core:convert-to-instance wiki --dry-run --backup=' . escapeshellarg($backup));
            $this->assertSame(0, $dryStatus, $dryRun);
            $this->assertDirectoryExists($wiki . '/src', 'a dry run changes nothing');
            $this->assertDirectoryDoesNotExist($backup);

            [$output, $status] = $this->console($folder, 'core:convert-to-instance wiki --backup=' . escapeshellarg($backup));
            $this->assertSame(0, $status, $output);
            $this->assertStringContainsString(\YESWIKI_PROGRAM_DIR, (string)file_get_contents($wiki . '/index.php'));
            $this->assertFileExists($wiki . '/yeswicli');
            $this->assertFileExists($wiki . '/yeswiki.config.php');
            $this->assertFileExists($wiki . '/files/photo.jpg');
            $this->assertFileExists($wiki . '/google123.html');
            $this->assertDirectoryExists($wiki . '/custom/extensions/mine', 'an extension the program lacks stays with the wiki');
            $this->assertDirectoryExists($wiki . '/custom/themes/montheme', 'so does a theme');
            $this->assertDirectoryDoesNotExist($wiki . '/cache/container');
            foreach (['src', 'vendor', 'extensions', 'themes', 'composer.json'] as $replaced) {
                $this->assertFileDoesNotExist($wiki . '/' . $replaced);
                $this->assertFileExists($backup . '/' . $replaced);
            }
            $this->assertFileExists($backup . '/extensions/lms', 'a shipped extension goes to the backup');

            [$again, $againStatus] = $this->console($folder, 'core:convert-to-instance wiki');
            $this->assertSame(1, $againStatus, $again);
            $this->assertStringContainsString('already an instance', $again);
        } finally {
            exec('rm -rf ' . escapeshellarg($folder));
        }
    }
}
