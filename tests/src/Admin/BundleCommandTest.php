<?php

namespace YesWiki\Test\Admin;

use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Console\Tester\CommandTester;
use YesWiki\Admin\Command\BundleCommand;
use YesWiki\Admin\Service\PackageInstaller;
use YesWiki\Kernel\Command\RunsOutsideAnInstance;
use YesWiki\Kernel\Service\ProgramVersion;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** Replicas 05: an image build installs extensions into its program tree from the repository, with the update screen's own steps and no wiki. */
#[CoversClass(BundleCommand::class)]
#[CoversClass(PackageInstaller::class)]
class BundleCommandTest extends YesWikiTestCase
{
    private const NAME = 'bundleprobe';

    private string $repository = '';

    /** @var resource|null */
    private $server = null;

    private string $noProxyWas = '';

    protected function setUp(): void
    {
        parent::setUp();
        self::getWiki();
        if (realpath(YESWIKI_INSTANCE_DIR) !== realpath(YESWIKI_PROGRAM_DIR)) {
            $this->markTestSkipped('core:bundle installs into the program tree, and this run keeps its wiki elsewhere');
        }
        $this->repository = sys_get_temp_dir() . '/yw-bundle-' . bin2hex(random_bytes(4));
        $line = strtolower((new ProgramVersion())->releaseLine() ?: 'cercopitheque');
        mkdir($this->repository . '/' . $line, 0o755, true);
        $archive = 'extension-' . self::NAME . '-2026-01-01-1.zip';
        $zip = new \ZipArchive();
        $zip->open($this->repository . '/' . $line . '/' . $archive, \ZipArchive::CREATE);
        $zip->addFromString(self::NAME . '/composer.json', (string)json_encode(['name' => 'yeswiki/' . self::NAME, 'version' => '2026.01.01']));
        $zip->addFromString(self::NAME . '/README.md', 'probe');
        $zip->close();
        file_put_contents($this->repository . '/' . $line . '/' . $archive . '.md5', md5_file($this->repository . '/' . $line . '/' . $archive) . '  ' . $archive);
        file_put_contents($this->repository . '/' . $line . '/packages.json', (string)json_encode([
            'extension-' . self::NAME => ['version' => '2026-01-01-1', 'file' => $archive, 'description' => 'probe', 'documentation' => ''],
        ]));
        $this->noProxyWas = (string)getenv('no_proxy');
        putenv('no_proxy=127.0.0.1');
        putenv('NO_PROXY=127.0.0.1');
    }

    protected function tearDown(): void
    {
        if (is_resource($this->server)) {
            proc_terminate($this->server);
            proc_close($this->server);
        }
        putenv('no_proxy=' . $this->noProxyWas);
        putenv('NO_PROXY=' . $this->noProxyWas);
        foreach ([YESWIKI_PROGRAM_DIR . '/extensions/' . self::NAME, $this->repository] as $tree) {
            if ($tree !== '' && is_dir($tree)) {
                exec('rm -rf ' . escapeshellarg($tree));
            }
        }
        parent::tearDown();
    }

    public function testItInstallsIntoTheProgramTreeAndFailsOnANameTheRepositoryLacks(): void
    {
        $this->assertContains(RunsOutsideAnInstance::class, class_implements(BundleCommand::class), 'an image build has no wiki to boot');
        $port = $this->serve();
        $tester = new CommandTester(new BundleCommand());

        $status = $tester->execute(['packages' => [self::NAME, 'nothere'], '--repository' => "http://127.0.0.1:{$port}"]);

        $this->assertFileExists(YESWIKI_PROGRAM_DIR . '/extensions/' . self::NAME . '/composer.json', $tester->getDisplay());
        $this->assertStringContainsString('nothere is no extension or theme of this repository', $tester->getDisplay());
        $this->assertSame(1, $status, 'a build that asked for something it did not get must fail');
    }

    public function testAnUnreachableRepositoryFailsTheBuild(): void
    {
        $tester = new CommandTester(new BundleCommand());

        $this->assertSame(1, $tester->execute(['packages' => [self::NAME], '--repository' => 'not a repository']));
        $this->assertDirectoryDoesNotExist(YESWIKI_PROGRAM_DIR . '/extensions/' . self::NAME);
    }

    private function serve(): int
    {
        $port = random_int(20000, 40000);
        $server = proc_open([PHP_BINARY, '-S', "127.0.0.1:{$port}", '-t', $this->repository], [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes);
        $this->assertIsResource($server);
        $this->server = $server;
        for ($try = 0; $try < 50; ++$try) {
            $socket = @fsockopen('127.0.0.1', $port, $errno, $error, 0.1);
            if ($socket !== false) {
                fclose($socket);

                return $port;
            }
            usleep(100000);
        }
        $this->fail('the fixture repository did not start');
    }
}
