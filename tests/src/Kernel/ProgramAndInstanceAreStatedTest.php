<?php

namespace YesWiki\Test\Kernel;

use PHPUnit\Framework\Attributes\CoversMethod;
use YesWiki\Admin\Service\ArchiveService;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** Ticket 43: which Program and which Instance are meant is stated, not inferred. */
#[CoversMethod(ArchiveService::class, 'assertArchivableFrom')]
class ProgramAndInstanceAreStatedTest extends YesWikiTestCase
{
    /**
     * @param array<string, string> $env
     *
     * @return array{status: int, out: string}
     */
    private function boot(string $php, array $env = [], ?string $cwd = null): array
    {
        $script = tempnam(sys_get_temp_dir(), 'yw-paths-') . '.php';
        file_put_contents($script, "<?php\nrequire " . var_export(\YESWIKI_PROGRAM_DIR . '/src/bootstrap_paths.php', true) . ";\n" . $php);

        $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($script) . ' 2>&1';
        foreach ($env as $name => $value) {
            $command = $name . '=' . escapeshellarg($value) . ' ' . $command;
        }
        if ($cwd !== null) {
            $command = 'cd ' . escapeshellarg($cwd) . ' && ' . $command;
        }

        $out = [];
        $status = 0;
        exec($command, $out, $status);
        unlink($script);

        return ['status' => $status, 'out' => implode("\n", $out)];
    }

    public function testTheEnvironmentNamesTheInstance(): void
    {
        $stated = (string)realpath((string)sys_get_temp_dir());
        $result = $this->boot(
            'echo YESWIKI_INSTANCE_DIR;',
            ['YESWIKI_INSTANCE_DIR' => $stated]
        );

        $this->assertSame(0, $result['status'], $result['out']);
        $this->assertSame($stated, $result['out'], 'a stated instance beats the working directory');
    }

    public function testWithoutAStatementTheWorkingDirectoryIsUsed(): void
    {
        $cwd = (string)realpath((string)sys_get_temp_dir());
        $result = $this->boot('echo YESWIKI_INSTANCE_DIR;', [], $cwd);

        $this->assertSame(0, $result['status'], $result['out']);
        $this->assertSame($cwd, $result['out'], 'the fallback is still getcwd(), which is right under php-fpm');
    }

    public function testAStatedDirectoryThatIsNotThereStopsTheBootAndSaysWhich(): void
    {
        $result = $this->boot(
            'echo YESWIKI_INSTANCE_DIR;',
            ['YESWIKI_INSTANCE_DIR' => '/yeswiki-ticket-43-no-such-directory']
        );

        $this->assertNotSame(0, $result['status'], 'continuing on a path that does not resolve hides the failure');
        $this->assertStringContainsString('YESWIKI_INSTANCE_DIR', $result['out']);
        $this->assertStringContainsString('/yeswiki-ticket-43-no-such-directory', $result['out'], 'the message names the value it was given');
    }

    public function testTheProgramIsStatedTheSameWay(): void
    {
        $stated = (string)realpath((string)sys_get_temp_dir());
        $result = $this->boot(
            'echo YESWIKI_PROGRAM_DIR;',
            ['YESWIKI_PROGRAM_DIR' => $stated, 'YESWIKI_INSTANCE_DIR' => $stated]
        );

        $this->assertSame(0, $result['status'], $result['out']);
        $this->assertSame($stated, $result['out']);
    }

    /** The bug this ticket names: `composer.json` and `composer.lock` live in the Program, and were being looked for in the Instance. */
    public function testAFarmInstanceCanBeArchived(): void
    {
        $wiki = $this->getWiki();
        $service = $wiki->services->get(ArchiveService::class);
        $assert = new \ReflectionMethod(ArchiveService::class, 'assertArchivableFrom');

        $instance = (string)realpath((string)sys_get_temp_dir()) . '/yw-farm-instance-' . getmypid();
        mkdir($instance, 0o755, true);
        touch($instance . '/index.php');

        try {
            $this->assertFalse(file_exists($instance . '/composer.json'));

            $assert->invoke($service, $instance, \YESWIKI_PROGRAM_DIR);
            $this->addToAssertionCount(1);
        } finally {
            @unlink($instance . '/index.php');
            @rmdir($instance);
        }
    }

    public function testSomethingThatIsNotAWikiIsStillRefused(): void
    {
        $wiki = $this->getWiki();
        $service = $wiki->services->get(ArchiveService::class);
        $assert = new \ReflectionMethod(ArchiveService::class, 'assertArchivableFrom');

        $empty = (string)realpath((string)sys_get_temp_dir());

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('index.php');
        $assert->invoke($service, $empty . '/yw-not-a-wiki-' . getmypid(), \YESWIKI_PROGRAM_DIR);
    }
}
