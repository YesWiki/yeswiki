<?php

namespace YesWiki\Test\Helloworld\Commands;

use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use YesWiki\Helloworld\Commands\HelloCommand;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

#[CoversMethod(HelloCommand::class, 'execute')]
class HelloCommandTest extends YesWikiTestCase
{
    #[DataProvider('providerTestExecute')]
    public function testExecute(?string $username, bool $uppercase, int $statusCode, string $waitedOutput)
    {
        $wiki = $this->getWiki();
        // create Application
        $application = new Application();
        $application->add(new HelloCommand($wiki->services));

        $command = $application->find('helloworld:hello');
        $commandTester = new CommandTester($command);
        $params = [];
        if (!empty($username)) {
            $params['username'] = $username;
        }
        if ($uppercase) {
            $params['--uppercase'] = '1';
        }
        $commandTester->execute($params);
        $this->assertEquals($statusCode, $commandTester->getStatusCode());
        $this->assertMatchesRegularExpression($waitedOutput, $commandTester->getDisplay());
    }

    public static function providerTestExecute()
    {
        return [
            'no arg, no option' => [null, false, Command::SUCCESS, "/^Hello !(?:\r|\n)+$/"],
            'with username' => ['John Smith', false, Command::SUCCESS, "/^Hello John Smith !(?:\r|\n)+$/"],
            'with username uppercase' => ['John Smith', true, Command::SUCCESS, "/^HELLO JOHN SMITH !(?:\r|\n)+$/"],
            'uppercase no username' => [null, true, Command::SUCCESS, "/^HELLO !(?:\r|\n)+$/"],
        ];
    }
}
