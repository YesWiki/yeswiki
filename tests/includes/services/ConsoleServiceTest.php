<?php

namespace YesWiki\Test\Core\Commands;

use YesWiki\Core\Service\ConsoleService;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

class ConsoleServiceTest extends YesWikiTestCase
{
    public function testConsoleServiceExisting(): ConsoleService
    {
        $wiki = $this->getWiki();
        $this->assertTrue($wiki->services->has(ConsoleService::class));

        return $wiki->services->get(ConsoleService::class);
    }

    /**
     * @depends testConsoleServiceExisting
     * @dataProvider checkStartConsole
     * @covers \ConsoleService::startConsoleAsync
     */
    public function testStartConsoleAsync(
        string $command,
        array $args,
        bool $processIsNull,
        ?string $stdout,
        ?string $stderr,
        ConsoleService $consoleService
    ) {
        $process = $consoleService->startConsoleAsync($command, $args);
        if ($processIsNull) {
            $this->assertNull($process);
        } else {
            $this->assertNotNull($process);
            $process->wait();
            $results = $consoleService->getProcessOut($process);
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
     * @depends testConsoleServiceExisting
     * @dataProvider checkStartConsole
     * @covers \ConsoleService::startConsoleSync
     */
    public function testStartConsoleSync(
        string $command,
        array $args,
        bool $processIsNull,
        ?string $stdout,
        ?string $stderr,
        ConsoleService $consoleService
    ) {
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

    public function checkStartConsole()
    {
        return [
            'hello command ok' => [
                'command' => 'helloworld:hello',
                'args' => [],
                'processIsNull' => false,
                'stdout' => "/^Hello !(?:\r|\n)+/",
                'stderr' => null,
            ],
            'hello command with args ok' => [
                'command' => 'helloworld:hello',
                'args' => ['John Smith'],
                'processIsNull' => false,
                'stdout' => "/^Hello John Smith !(?:\r|\n)+/",
                'stderr' => null,
            ],
            'not existing command' => [
                'command' => 'nocommand:nocommand',
                'args' => [''],
                'processIsNull' => false,
                'stdout' => null,
                'stderr' => '/There are no commands defined in the "nocommand" namespace\\./',
            ],
        ];
    }

    /**
     * @depends testConsoleServiceExisting
     */
    public function testAsync(ConsoleService $consoleService)
    {
        $tmp_path = tempnam('cache', 'tmp_test_results_');
        $tmpfile = basename($tmp_path);
        $process = $consoleService->startConsoleAsync('core:testconsoleservice', [
            '-f', $tmpfile,
            '-t', 'ParentProcess',
            '-c', 'ChildProcess',
            '-w', 1,
        ]);
        $process->wait();
        sleep(3);

        $content = file_get_contents($tmp_path);
        unlink($tmp_path);
        $this->assertMatchesRegularExpression('/^ParentProcessChildProcess$/', $content);
    }
}
