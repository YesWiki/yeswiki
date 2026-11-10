<?php

namespace YesWiki\Test\Kernel;

use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Kernel\Service\ConfigurationFileProvider;
use YesWiki\Kernel\Service\ConfigurationSecrets;
use YesWiki\Kernel\Service\ConfigurationService;
use YesWiki\Kernel\Service\EnvironmentConfiguration;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** Replicas 10: a secret of the configuration is read when it is used, and the compiled container in cache/ holds its name only. */
#[CoversClass(ConfigurationSecrets::class)]
class ConfigurationSecretsTest extends YesWikiTestCase
{
    public function testOnlyTheSecretsThatHoldAValueBecomePlaceholders(): void
    {
        $compiled = ConfigurationSecrets::withPlaceholders([
            'db_password' => 'hunter2',
            'contact_smtp_pass' => '',
            'db_user' => 'yeswiki',
            'herse_password' => 42,
        ]);

        $this->assertSame([
            'db_password' => '%env(yeswiki_secret:db_password)%',
            'contact_smtp_pass' => '',
            'db_user' => 'yeswiki',
            'herse_password' => '%env(yeswiki_secret:herse_password)%',
        ], $compiled);
    }

    public function testAPlaceholderResolvesToTheValueReadForTheRequest(): void
    {
        $secrets = new ConfigurationSecrets(['db_password' => 'hunter2', 'herse_password' => 42]);
        $never = static fn (): mixed => null;

        $this->assertSame('hunter2', $secrets->getEnv(ConfigurationSecrets::PREFIX, 'db_password', $never));
        $this->assertSame('42', $secrets->getEnv(ConfigurationSecrets::PREFIX, 'herse_password', $never));
        $this->assertSame('', $secrets->getEnv(ConfigurationSecrets::PREFIX, 'sync_secret', $never));
    }

    public function testTheWikiReadsItsSecretsButTheCompiledContainerDoesNotHoldThem(): void
    {
        $wiki = self::getWiki();
        $file = (new ConfigurationService())->getConfiguration(ConfigurationFileProvider::getConfigFileFromEnv());
        $file->load();
        $configured = EnvironmentConfiguration::apply(['db_password' => $file['db_password'] ?? '']);
        $password = is_scalar($configured['db_password'] ?? null) ? (string)$configured['db_password'] : '';
        if ($password === '') {
            $this->markTestSkipped('this wiki has no database password to hide');
        }

        $this->assertSame($password, $wiki->services->get(ParameterBagInterface::class)->get('db_password'));

        $container = (string)\dirname((string)(new \ReflectionClass($wiki->services))->getFileName());
        $leaks = [];
        foreach (glob($container . '/*.php') ?: [] as $compiled) {
            if (str_contains((string)file_get_contents($compiled), var_export($password, true))) {
                $leaks[] = basename($compiled);
            }
        }
        $this->assertSame([], $leaks, 'the database password is written in clear in the compiled container');
    }
}
