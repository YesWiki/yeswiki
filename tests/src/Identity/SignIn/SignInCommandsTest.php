<?php

namespace YesWiki\Test\Identity\SignIn;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use YesWiki\Identity\Command\SignInDisableCommand;
use YesWiki\Identity\Command\SignInEnableCommand;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\WikiSettings;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * `./yeswicli sign-in:enable local` and `sign-in:disable`: the way back in when the provider in charge is down and local accounts are off. The providers
 * live in the wiki's database (replicas 04), where editing the configuration file cannot reach them, so a command must.
 */
class SignInCommandsTest extends YesWikiTestCase
{
    /** @var array<string, mixed> */
    private array $storedWas = [];

    private string|false $configFileWas = false;

    public static function setUpBeforeClass(): void
    {
        self::getWiki();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->configFileWas = getenv('YESWIKI_CONFIG_FILE');
        putenv('YESWIKI_CONFIG_FILE');
        if (!is_file(YESWIKI_INSTANCE_DIR . '/yeswiki.config.php')) {
            $this->markTestSkipped('this wiki runs on another configuration file, so its settings are not stored in the database');
        }
        $settings = self::getWiki()->services->get(WikiSettings::class);
        $settings->startNewRequest();
        $this->storedWas = $settings->all();
        // as the upgrade leaves a wiki LDAP signed in on: the directory in charge, local accounts off
        $settings->store(['sso_providers' => [
            'local' => ['type' => 'local', 'enabled' => false],
            'ldap' => ['type' => 'ldap', 'label' => 'LDAP', 'enabled' => true, 'connection' => ['uri' => 'ldap://down.example:389', 'bind_dn_template' => 'uid={login},dc=x']],
        ]]);
    }

    protected function tearDown(): void
    {
        $services = self::getWiki()->services;
        $db = $services->get(DbService::class);
        $db->query('DELETE FROM' . $db->prefixTable('triples') . 'WHERE resource = ?', [WikiSettings::RESOURCE]);
        $services->get(WikiSettings::class)->store($this->storedWas);
        $services->get(WikiSettings::class)->startNewRequest();
        putenv($this->configFileWas === false ? 'YESWIKI_CONFIG_FILE' : 'YESWIKI_CONFIG_FILE=' . $this->configFileWas);
        parent::tearDown();
    }

    private function run_(string $id, string $command = 'sign-in:enable'): CommandTester
    {
        $application = new Application();
        $application->add(new SignInEnableCommand(self::getWiki()->services));
        $application->add(new SignInDisableCommand(self::getWiki()->services));
        $tester = new CommandTester($application->find($command));
        $tester->execute(['command' => $command, 'id' => $id], ['interactive' => false]);
        self::getWiki()->services->get(WikiSettings::class)->startNewRequest();

        return $tester;
    }

    /** @return array<string, mixed> */
    private function stored(string $id): array
    {
        return self::getWiki()->services->get(WikiSettings::class)->all()['sso_providers'][$id] ?? [];
    }

    public function testLocalAccountsAreSwitchedBackOnWhereTheWikiReadsThem(): void
    {
        $tester = $this->run_('local');

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());
        $this->assertTrue($this->stored('local')['enabled'] ?? null);
        $this->assertTrue($this->stored('ldap')['enabled'] ?? null, 'the provider in charge is left as it was');
    }

    public function testAProviderIsSwitchedOffOnceAnotherIsOn(): void
    {
        $this->run_('local');

        $tester = $this->run_('ldap', 'sign-in:disable');

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());
        $this->assertFalse($this->stored('ldap')['enabled'] ?? null);
        $this->assertTrue($this->stored('local')['enabled'] ?? null);
    }

    public function testTheLastProviderOnIsNotSwitchedOff(): void
    {
        $tester = $this->run_('ldap', 'sign-in:disable');

        $this->assertSame(Command::FAILURE, $tester->getStatusCode(), 'nobody could sign in any more');
        $this->assertTrue($this->stored('ldap')['enabled'] ?? null);
    }

    public function testAnUnknownProviderIsRefusedAndNothingChanges(): void
    {
        $tester = $this->run_('nobody');

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertFalse($this->stored('local')['enabled'] ?? null);
    }
}
