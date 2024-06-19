<?php

namespace YesWiki\Test\Helloworld\Commands;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use YesWiki\Helloworld\Commands\HelloCommand;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

class HelloCommandTest extends YesWikiTestCase
{
    /**
     * @covers \HelloCommand::execute
     * @dataProvider providerTestExecute
     */
    public function testExecute(?string $username, bool $uppercase, int $statusCode, string $waitedOutput)
    {
        $wiki = $this->getWiki();
        // create Application
        $application = new Application();
        $application->add(new HelloCommand($wiki));

        $command = $application->find('helloworld:hello');
        $commandTester = new CommandTester($command);
        $params = [];
        if (!empty($username)) {
            $params['username'] = $username;
        }
        if ($uppercase) {
            $params['--uppercase'] = '1';
        }
        // pass arguments to the helper
        // [
        //'username' => 'Wouter',

        // prefix the key with two dashes when passing options,
        // e.g: '--some-option' => 'option_value',
        //]
        $commandTester->execute($params);
        // $commandTester->assertCommandIsSuccessful();
        $this->assertEquals($statusCode, $commandTester->getStatusCode());
        // the output of the command in the console
        $this->assertMatchesRegularExpression($waitedOutput, $commandTester->getDisplay());
    }

    public function providerTestExecute()
    {
        return [
            'no arg, no option' => [
                'username' => null,
                'uppercase' => false,
                'statusCode' => Command::SUCCESS,
                'output' => "/^Hello !(?:\r|\n)+$/",
            ],
            'with username' => [
                'username' => 'John Smith',
                'uppercase' => false,
                'statusCode' => Command::SUCCESS,
                'output' => "/^Hello John Smith !(?:\r|\n)+$/",
            ],
            'with username uppercase' => [
                'username' => 'John Smith',
                'uppercase' => true,
                'statusCode' => Command::SUCCESS,
                'output' => "/^HELLO JOHN SMITH !(?:\r|\n)+$/",
            ],
            'uppercase no username ' => [
                'username' => null,
                'uppercase' => true,
                'statusCode' => Command::SUCCESS,
                'output' => "/^HELLO !(?:\r|\n)+$/",
            ],
        ];
    }
}
