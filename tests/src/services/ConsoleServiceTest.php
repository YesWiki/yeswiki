<?php

namespace YesWiki\Test\Core\Commands;

use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Depends;
use YesWiki\Kernel\Service\ConsoleService;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

#[CoversMethod(ConsoleService::class, 'startConsoleAsync')]
#[CoversMethod(ConsoleService::class, 'startConsoleSync')]
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
}
