<?php

namespace YesWiki\Test\Core\Commands;

use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Depends;
use YesWiki\Kernel\Service\CliOwnership;
use YesWiki\Kernel\Service\ConsoleService;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

#[CoversMethod(ConsoleService::class, 'startConsoleAsync')]
#[CoversMethod(ConsoleService::class, 'startConsoleSync')]
#[CoversMethod(ConsoleService::class, 'startDetached')]
class ConsoleServiceTest extends YesWikiTestCase
{
    public function testConsoleServiceExisting(): ConsoleService
    {
        $wiki = $this->getWiki();
        $this->assertTrue($wiki->services->has(ConsoleService::class));

        return $wiki->services->get(ConsoleService::class);
    }

    /**
     * @param list<int|string> $args
     */
    #[Depends('testConsoleServiceExisting')]
    #[DataProvider('checkStartConsole')]
    public function testStartConsoleAsync(
        string $command,
        array $args,
        bool $processIsNull,
        ?string $stdout,
        ?string $stderr,
        ConsoleService $consoleService
    ): void {
        $process = $consoleService->startConsoleAsync($command, $args);
        if ($processIsNull) {
            $this->assertNull($process);
        } else {
            $this->assertNotNull($process);
            $process->wait();
            $results = $consoleService->getProcessOut($process);
            $result = $results[array_key_first($results)];
            if (!is_null($stdout)) {
                $this->assertArrayHasKey('stdout', $result);
                $this->assertMatchesRegularExpression($stdout, $result['stdout']);
            }
            if (!is_null($stderr)) {
                $this->assertArrayHasKey('stderr', $result);
                $this->assertMatchesRegularExpression($stderr, $result['stderr']);
            }
        }
    }

    /**
     * @param list<int|string> $args
     */
    #[Depends('testConsoleServiceExisting')]
    #[DataProvider('checkStartConsole')]
    public function testStartConsoleSync(
        string $command,
        array $args,
        bool $processIsNull,
        ?string $stdout,
        ?string $stderr,
        ConsoleService $consoleService
    ): void {
        $results = $consoleService->startConsoleSync($command, $args);
        if ($processIsNull) {
            $this->assertNull($results);
        } else {
            $this->assertNotNull($results);
            $result = $results[array_key_first($results)];
            if (!is_null($stdout)) {
                $this->assertArrayHasKey('stdout', $result);
                $this->assertMatchesRegularExpression($stdout, $result['stdout']);
            }
            if (!is_null($stderr)) {
                $this->assertArrayHasKey('stderr', $result);
                $this->assertMatchesRegularExpression($stderr, $result['stderr']);
            }
        }
    }

    /**
     * @return array<string, array{string, list<string>, bool, string|null, string|null}>
     */
    public static function checkStartConsole(): array
    {
        return [
            'hello command ok' => ['helloworld:hello', [], false, "/^Hello !(?:\r|\n)+/", null],
            'hello command with args ok' => ['helloworld:hello', ['John Smith'], false, "/^Hello John Smith !(?:\r|\n)+/", null],
            'not existing command' => ['nocommand:nocommand', [''], false, null, '/There are no commands defined in the "nocommand" namespace\./'],
        ];
    }

    #[Depends('testConsoleServiceExisting')]
    public function testAsync(ConsoleService $consoleService): void
    {
        $tmp_path = tempnam('cache', 'tmp_test_results_');
        $tmpfile = basename($tmp_path);
        $process = $consoleService->startConsoleAsync('core:testconsoleservice', [
            '-f', $tmpfile,
            '-t', 'ParentProcess',
            '-c', 'ChildProcess',
            '-w', 1,
        ]);
        $this->assertNotNull($process, 'the console command must have started');
        $process->wait();
        sleep(3);

        $content = file_get_contents($tmp_path);
        unlink($tmp_path);
        $this->assertIsString($content, 'the parent and child processes must have written the result file');
        $this->assertMatchesRegularExpression('/^ParentProcessChildProcess$/', $content);
    }

    /**
     * A process the end of a request starts outlives that request, and nobody reads what it prints.
     *
     * With pipes, the request's Process object closed them when it was dropped, and the child died
     * of SIGPIPE at its first line. It also has to run in the wiki, wherever the request was, and
     * pass the console's ownership check: the web server's user starts it.
     */
    #[Depends('testConsoleServiceExisting')]
    public function testADetachedProcessOutlivesItsParentEvenWhenItPrints(ConsoleService $consoleService): void
    {
        $done = sys_get_temp_dir() . '/yw-detached-' . bin2hex(random_bytes(4));
        $script = 'usleep(300000); echo str_repeat("x", 100000); fwrite(STDERR, "e");'
            . ' file_put_contents($argv[1], json_encode([getcwd(), getenv($argv[2])]));';
        $previous = (string)getcwd();
        chdir(sys_get_temp_dir());
        try {
            $process = $consoleService->startDetached(PHP_BINARY, ['-r', $script, $done, CliOwnership::ANY_USER]);
        } finally {
            chdir($previous);
        }
        unset($process); // the request ends

        for ($waited = 0; !is_file($done) && $waited < 50; $waited++) {
            usleep(100000);
        }
        $seen = @file_get_contents($done);
        @unlink($done);

        $this->assertIsString($seen, 'the process died once its parent let it go');
        $this->assertSame([YESWIKI_INSTANCE_DIR, '1'], json_decode($seen, true));
    }
}
