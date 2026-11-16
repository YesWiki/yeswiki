<?php

namespace YesWiki\Test\Core\Migrations;

use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Service\PageManager;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Kernel\Entity\ConfigurationFile;
use YesWiki\Kernel\Service\ConfigurationService;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\DoryphoreLeftovers;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * A Doryphore wiki signing in through loginsso, loginldap or logincas keeps signing its people in: providers in the configuration, links in the accounts, no revision added.
 * Only the accounts the test creates are walked, and the configuration is a temporary file.
 */
class LoginExtensionsBecomeProvidersTest extends YesWikiTestCase
{
    private const ACCOUNTS = ['TestLoginExtMarie', 'TestLoginExtPaul', 'TestLoginExtLinked'];

    private const LEGACY_USER = 'TestLoginExtLegacy';

    private const MD5 = '5f4dcc3b5aa765d61d8327deb882cf99';

    private string $instance = '';

    private string $config = '';

    private DbService $db;

    private string $pages = '';

    public static function setUpBeforeClass(): void
    {
        self::getWiki();
        require_once 'src/migrations/20261111100000_LoginExtensionsBecomeProviders.php';
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = $this->getWiki()->services->get(DbService::class);
        $this->pages = trim($this->db->prefixTable('pages'));
        $this->instance = sys_get_temp_dir() . '/login-extensions-' . bin2hex(random_bytes(4));
        mkdir($this->instance . '/' . DoryphoreLeftovers::ASIDE . '/tools', 0755, true);
        $this->config = $this->instance . '/yeswiki.config.php';
        putenv('YESWIKI_CONFIG_FILE=' . $this->config);
        $this->removeAccounts();
    }

    protected function tearDown(): void
    {
        putenv('YESWIKI_CONFIG_FILE');
        $this->removeAccounts();
        exec('rm -rf ' . escapeshellarg($this->instance));
        parent::tearDown();
    }

    private function removeAccounts(): void
    {
        $this->db->query("DELETE FROM {$this->pages} WHERE tag IN (?, ?, ?)", self::ACCOUNTS);
        $users = $this->getWiki()->services->get(UserManager::class);
        $legacy = $users->getOneByName(self::LEGACY_USER);
        if ($legacy !== null) {
            $users->delete($legacy);
        }
        $this->getWiki()->services->get(PageManager::class)->deleteOrphaned(self::LEGACY_USER);
    }

    /** @param array<string, mixed> $values */
    private function writeConfig(array $values): void
    {
        file_put_contents($this->config, "<?php\n\n\$yeswikiConfig = " . var_export(['wakka_name' => 'test', 'base_url' => 'https://w/?'] + $values, true) . ";\n");
    }

    /** @return array<array-key, mixed> */
    private function readConfig(): array
    {
        $configuration = new ConfigurationFile($this->config);
        $configuration->load();

        return $configuration->__get('_parameters');
    }

    private function installTools(string ...$tools): void
    {
        foreach ($tools as $tool) {
            mkdir($this->instance . '/' . DoryphoreLeftovers::ASIDE . '/tools/' . $tool);
        }
    }

    /**
     * An account as MigrateUsersToPages left it, oldest revision first.
     *
     * @param list<array<string, mixed>> $revisions
     */
    private function account(string $tag, array $revisions): void
    {
        $last = count($revisions) - 1;
        foreach ($revisions as $n => $body) {
            $this->db->query(
                "INSERT INTO {$this->pages} (tag, {$this->db->quoteIdentifier('time')}, body, owner, {$this->db->quoteIdentifier('user')}, latest, {$this->db->quoteIdentifier('type')}, parent) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                [$tag, sprintf('2024-01-0%d 00:00:00', $n + 1), PageBody::encode($body + ['email' => strtolower($tag) . '@example.com']), $tag, $tag, $n === $last ? 'Y' : 'N', 'user', '']
            );
        }
    }

    /** @return list<array<string, mixed>> every revision's body, oldest first */
    private function revisions(string $tag): array
    {
        $rows = $this->db->loadAll("SELECT body FROM {$this->pages} WHERE tag = ? ORDER BY {$this->db->quoteIdentifier('time')} ASC, id ASC", [$tag]);

        return array_map(fn (array $row): array => PageBody::decode((string)$row['body']), $rows);
    }

    /** @return array<string, mixed> */
    private function latest(string $tag): array
    {
        $row = $this->db->loadSingle("SELECT body FROM {$this->pages} WHERE tag = ? AND latest = 'Y'", [$tag]);

        return PageBody::decode((string)($row['body'] ?? ''));
    }

    private function migrate(): \LoginExtensionsBecomeProviders
    {
        $migration = new \LoginExtensionsBecomeProviders();
        $migration->setServices($this->getWiki()->services);
        $migration->setDbService($this->db);
        $migration->convertWiki($this->instance, $this->config, new \DateTimeImmutable('2026-11-11 10:00:00'), self::ACCOUNTS);

        return $migration;
    }

    /** @return array<string, mixed> */
    private function ssoEntry(string $label = 'My Auth Server'): array
    {
        return [
            'auth_type' => 'oauth2',
            'auth_options' => [
                'clientId' => 'the-client',
                'clientSecret' => 'sso-client-secret-value',
                'urlAuthorize' => 'https://s/auth',
                'urlAccessToken' => 'https://s/token',
                'urlResourceOwnerDetails' => 'https://s/userinfo',
            ],
            'id_sso_field' => 'sub',
            'email_sso_field' => 'email',
            'create_user_from' => '#[given_name] #[family_name]',
            'button_style' => ['button_label' => $label],
        ];
    }

    public function testOneLoginssoProviderTurnsTheColumnIntoLinks(): void
    {
        $this->writeConfig(['sso_config' => ['providers' => [$this->ssoEntry()]]]);
        $this->installTools('loginsso');
        $this->account('TestLoginExtMarie', [['password' => 'old-hash', 'loginsso_id' => 'x'], ['password' => '$2y$10$abcdefghijklmnopqrstuuJf7kq0YhJ0fM8bRrN7l2QX0uWx1cGa', 'loginsso_id' => 'x']]);
        $this->account('TestLoginExtPaul', [['password' => 'p']]);

        $this->migrate();

        $body = $this->latest('TestLoginExtMarie');
        $this->assertSame('my-auth-server', $body['sso_provider'] ?? null);
        $this->assertSame('x', $body['sso_subject'] ?? null);
        $this->assertArrayNotHasKey('loginsso_id', $body);
        $this->assertSame('', $body['password'], 'a linked account signs in through its provider only');
        $this->assertSame('x', $this->revisions('TestLoginExtMarie')[0]['loginsso_id'] ?? null, 'the link is written on the latest revision only');
        $this->assertCount(2, $this->revisions('TestLoginExtMarie'), 'no revision is added');
        $this->assertArrayNotHasKey('sso_provider', $this->latest('TestLoginExtPaul'), 'an account loginsso never saw stays local');
    }

    /**
     * loginsso's own migration filled `loginsso_id` with every existing account's email: that copy names nobody at the provider,
     * so linking on it would lock out every account that never signed in through loginsso (their password goes with the link).
     */
    public function testAStoredIdThatIsOnlyTheAccountsEmailLinksNothing(): void
    {
        $this->writeConfig(['sso_config' => ['providers' => [$this->ssoEntry()]]]);
        $this->installTools('loginsso');
        $bcrypt = '$2y$10$abcdefghijklmnopqrstuuJf7kq0YhJ0fM8bRrN7l2QX0uWx1cGa';
        $this->account('TestLoginExtPaul', [['password' => $bcrypt, 'email' => 'paul@doryphore.example', 'loginsso_id' => 'paul@doryphore.example']]);
        $this->account('TestLoginExtMarie', [['password' => 'sso', 'email' => 'marie@doryphore.example', 'loginsso_id' => 'marie']]);

        $migration = $this->migrate();

        $paul = $this->latest('TestLoginExtPaul');
        $this->assertArrayNotHasKey('sso_provider', $paul, 'the email loginsso copied is not a subject');
        $this->assertArrayNotHasKey('loginsso_id', $paul);
        $this->assertSame($bcrypt, $paul['password'], 'the account keeps the password it signs in with');
        $this->assertSame('marie', $this->latest('TestLoginExtMarie')['sso_subject'] ?? null, 'a real subject still links');
        $this->assertStringContainsString('1 account(s) linked', implode("\n", $migration->notes()));
    }

    public function testAnEmailIsTheSubjectWhenLoginssoIdentifiedPeopleByTheirEmail(): void
    {
        $entry = ['id_sso_field' => 'mail', 'email_sso_field' => 'mail'] + $this->ssoEntry();
        $this->writeConfig(['sso_config' => ['providers' => [$entry]]]);
        $this->installTools('loginsso');
        $this->account('TestLoginExtPaul', [['password' => 'sso', 'email' => 'paul@doryphore.example', 'loginsso_id' => 'paul@doryphore.example']]);

        $this->migrate();

        $paul = $this->latest('TestLoginExtPaul');
        $this->assertSame('my-auth-server', $paul['sso_provider'] ?? null);
        $this->assertSame('paul@doryphore.example', $paul['sso_subject'] ?? null);
    }

    public function testAnAccountAlreadyLinkedKeepsItsLink(): void
    {
        $this->writeConfig(['sso_config' => ['providers' => [$this->ssoEntry()]]]);
        $this->installTools('loginsso');
        $this->account('TestLoginExtLinked', [['password' => '', 'loginsso_id' => 'x', 'sso_provider' => 'acme', 'sso_subject' => 'kept']]);

        $this->migrate();

        $body = $this->latest('TestLoginExtLinked');
        $this->assertSame('acme', $body['sso_provider']);
        $this->assertSame('kept', $body['sso_subject']);
        $this->assertArrayNotHasKey('loginsso_id', $body);
    }

    public function testSeveralProvidersDropTheColumnWithNoLink(): void
    {
        $this->writeConfig(['sso_config' => ['providers' => [$this->ssoEntry('First'), $this->ssoEntry('Second')]]]);
        $this->installTools('loginsso');
        $this->account('TestLoginExtMarie', [['password' => 'h', 'loginsso_id' => 'x']]);

        $migration = $this->migrate();

        $body = $this->latest('TestLoginExtMarie');
        $this->assertArrayNotHasKey('loginsso_id', $body);
        $this->assertArrayNotHasKey('sso_provider', $body);
        $this->assertArrayNotHasKey('sso_subject', $body);
        $this->assertSame(['local', 'first', 'second'], array_keys($this->readConfig()['sso_providers']));
        $this->assertStringContainsString('match by email', implode("\n", $migration->notes()));
    }

    public function testMd5IsClearedOnAnLdapWikiIncludingRevisions(): void
    {
        $this->writeConfig(['ldap_host' => 'h', 'ldap_port' => '389', 'ldap_base' => 'ou=u,dc=x']);
        $this->installTools('loginldap');
        $this->account('TestLoginExtMarie', [['password' => self::MD5], ['password' => strtoupper(self::MD5)], ['password' => self::MD5]]);

        $migration = $this->migrate();

        foreach ($this->revisions('TestLoginExtMarie') as $n => $body) {
            $this->assertSame('', $body['password'], "revision {$n}");
        }
        $this->assertCount(3, $this->revisions('TestLoginExtMarie'));
        $said = implode("\n", $migration->notes());
        $this->assertStringContainsString('md5', $said);
        $this->assertStringNotContainsString(self::MD5, $said);
    }

    public function testMd5IsClearedOnAnLdapWikiEvenWithAnExistingSection(): void
    {
        $this->writeConfig(['sso_providers' => ['local' => ['type' => 'local', 'enabled' => true]], 'ldap_host' => 'h', 'ldap_port' => '389']);
        $this->installTools('loginldap');
        $this->account('TestLoginExtMarie', [['password' => self::MD5], ['password' => self::MD5]]);

        $this->migrate();

        $this->assertSame(['', ''], array_column($this->revisions('TestLoginExtMarie'), 'password'), 'loginldap signed people in on Doryphore, whatever the section says now');
        $this->assertTrue($this->readConfig()['sso_providers']['local']['enabled'], 'the section the administrator wrote is kept');
        $this->assertFalse($this->readConfig()['sso_providers']['ldap']['enabled']);
    }

    /** A migration whose account step dies on its second rewrite, after the plan is computed and a first account was rewritten. */
    private function failingAccountStep(): \LoginExtensionsBecomeProviders
    {
        $migration = new class extends \LoginExtensionsBecomeProviders {
            private int $rewrites = 0;

            protected function rewriteBody(string $id, array $body): void
            {
                if (++$this->rewrites === 2) {
                    throw new \RuntimeException('the account step failed');
                }
                parent::rewriteBody($id, $body);
            }
        };
        $migration->setServices($this->getWiki()->services);
        $migration->setDbService($this->db);

        return $migration;
    }

    public function testAFailedAccountStepWritesNothingAndTheNextRunCompletes(): void
    {
        $this->writeConfig(['ldap_host' => 'h', 'ldap_port' => '389', 'ldap_base' => 'ou=u,dc=x']);
        $this->installTools('loginldap');
        $this->account('TestLoginExtMarie', [['password' => self::MD5], ['password' => self::MD5]]);
        $this->account('TestLoginExtPaul', [['password' => self::MD5]]);
        $config = (string)file_get_contents($this->config);

        $failure = null;
        try {
            $this->failingAccountStep()->convertWiki($this->instance, $this->config, new \DateTimeImmutable('2026-11-11 10:00:00'), self::ACCOUNTS);
        } catch (\RuntimeException $thrown) {
            $failure = $thrown;
        }
        $this->assertSame('the account step failed', $failure?->getMessage(), 'the failure reaches the migration runner, which runs the migration again next time');

        $this->assertSame($config, (string)file_get_contents($this->config), 'the configuration is written only once the accounts are');
        $this->assertSame([self::MD5, self::MD5], array_column($this->revisions('TestLoginExtMarie'), 'password'), 'the first rewrite is rolled back');
        $this->assertSame([self::MD5], array_column($this->revisions('TestLoginExtPaul'), 'password'));

        $this->migrate();

        $this->assertSame(['', ''], array_column($this->revisions('TestLoginExtMarie'), 'password'));
        $this->assertSame([''], array_column($this->revisions('TestLoginExtPaul'), 'password'));
        $this->assertTrue($this->readConfig()['sso_providers']['ldap']['enabled']);
    }

    public function testAConfigurationThatCannotBeWrittenLeavesTheAccountsAsTheyWere(): void
    {
        $this->writeConfig(['sso_config' => ['providers' => [$this->ssoEntry()]]]);
        $this->installTools('loginsso');
        $this->account('TestLoginExtMarie', [['password' => 'h', 'loginsso_id' => 'x']]);
        $config = (string)file_get_contents($this->config);
        $migration = new class extends \LoginExtensionsBecomeProviders {
            protected function writeConfiguration(ConfigurationService $service, ConfigurationFile $configuration): bool
            {
                return false;
            }
        };
        $migration->setServices($this->getWiki()->services);
        $migration->setDbService($this->db);

        $failure = null;
        try {
            $migration->convertWiki($this->instance, $this->config, new \DateTimeImmutable('2026-11-11 10:00:00'), self::ACCOUNTS);
        } catch (\RuntimeException $thrown) {
            $failure = $thrown;
        }
        $this->assertStringContainsString('could not be written', (string)$failure?->getMessage(), 'a configuration that cannot be written fails the migration, so it runs again');
        $this->assertStringNotContainsString('sso-client-secret-value', (string)$failure?->getMessage());

        $this->assertSame($config, (string)file_get_contents($this->config));
        $body = $this->latest('TestLoginExtMarie');
        $this->assertSame('x', $body['loginsso_id'] ?? null, 'the account keeps what the next run needs');
        $this->assertArrayNotHasKey('sso_provider', $body);
    }

    public function testMd5IsKeptOnACasWiki(): void
    {
        $this->writeConfig(['cas_host' => 'cas.example.org']);
        $this->installTools('logincas');
        $this->account('TestLoginExtMarie', [['password' => self::MD5], ['password' => self::MD5]]);

        $this->migrate();

        $this->assertSame([self::MD5, self::MD5], array_column($this->revisions('TestLoginExtMarie'), 'password'));
        $this->assertTrue($this->readConfig()['sso_providers']['cas']['enabled']);
    }

    public function testMd5IsKeptWhenLdapWasNotInCharge(): void
    {
        $this->writeConfig(['sso_config' => ['providers' => [$this->ssoEntry()]], 'ldap_host' => 'h', 'ldap_port' => '389']);
        $this->installTools('loginsso', 'loginldap');
        $this->account('TestLoginExtMarie', [['password' => self::MD5]]);

        $this->migrate();

        $this->assertSame(self::MD5, $this->latest('TestLoginExtMarie')['password']);
    }

    public function testBcryptIsLeftAlone(): void
    {
        $bcrypt = password_hash('secret', PASSWORD_BCRYPT);
        $this->writeConfig(['ldap_host' => 'h', 'ldap_port' => '389', 'ldap_base' => 'ou=u,dc=x']);
        $this->installTools('loginldap');
        $this->account('TestLoginExtPaul', [['password' => $bcrypt], ['password' => $bcrypt]]);

        $this->migrate();

        $this->assertSame([$bcrypt, $bcrypt], array_column($this->revisions('TestLoginExtPaul'), 'password'));
    }

    public function testTheConfigIsRewritten(): void
    {
        $this->writeConfig(['sso_config' => ['providers' => [$this->ssoEntry()], 'bazar_user_entry_id' => 1000], 'ldap_host' => 'h', 'ldap_port' => '389', 'ldap_base' => 'ou=u,dc=x', 'favorite_theme' => 'margot']);
        $this->installTools('loginsso', 'loginldap', 'tags');

        $migration = $this->migrate();

        $config = $this->readConfig();
        $this->assertSame('test', $config['wakka_name']);
        $this->assertSame('margot', $config['favorite_theme']);
        foreach (['sso_config', 'ldap_host', 'ldap_port', 'ldap_base'] as $key) {
            $this->assertArrayNotHasKey($key, $config);
        }
        $providers = $config['sso_providers'];
        $this->assertSame(['local', 'my-auth-server', 'ldap'], array_keys($providers));
        $this->assertFalse($providers['local']['enabled']);
        $this->assertTrue($providers['my-auth-server']['enabled']);
        $this->assertArrayNotHasKey('legacy_callback', $providers['my-auth-server']['connection']);
        $this->assertSame('sso-client-secret-value', $providers['my-auth-server']['connection']['client_secret']);
        $this->assertFalse($providers['ldap']['enabled']);

        $said = implode("\n", $migration->notes());
        $this->assertStringContainsString('my-auth-server', $said);
        $this->assertStringNotContainsString('sso-client-secret-value', $said);
    }

    public function testToolsStillInToolsAreFoundToo(): void
    {
        $this->writeConfig(['cas_host' => 'cas.example.org']);
        mkdir($this->instance . '/tools/logincas', 0755, true);

        $this->migrate();

        $this->assertTrue($this->readConfig()['sso_providers']['cas']['enabled']);
        $this->assertFalse($this->readConfig()['sso_providers']['local']['enabled']);
    }

    public function testRunningTwiceChangesNothing(): void
    {
        $this->writeConfig(['sso_config' => ['providers' => [$this->ssoEntry()]], 'ldap_host' => 'h', 'ldap_port' => '389']);
        $this->installTools('loginsso', 'loginldap');
        $this->account('TestLoginExtMarie', [['password' => self::MD5, 'loginsso_id' => 'x'], ['password' => 'h', 'loginsso_id' => 'x']]);
        $this->account('TestLoginExtPaul', [['password' => self::MD5]]);

        $this->migrate();
        $config = (string)file_get_contents($this->config);
        $marie = $this->revisions('TestLoginExtMarie');
        $paul = $this->revisions('TestLoginExtPaul');

        $second = $this->migrate();

        $this->assertSame($config, (string)file_get_contents($this->config));
        $this->assertSame($marie, $this->revisions('TestLoginExtMarie'));
        $this->assertSame($paul, $this->revisions('TestLoginExtPaul'));
        $this->assertSame([], $second->notes());
    }

    public function testAWikiWithoutLoginExtensionsIsLeftAlone(): void
    {
        $this->writeConfig(['favorite_theme' => 'margot']);
        $this->installTools('tags', 'bazar');
        $this->account('TestLoginExtMarie', [['password' => self::MD5], ['password' => self::MD5]]);
        $before = (string)file_get_contents($this->config);

        $migration = $this->migrate();

        $this->assertSame($before, (string)file_get_contents($this->config));
        $this->assertArrayNotHasKey('sso_providers', $this->readConfig(), 'no section: local stays on');
        $this->assertSame([self::MD5, self::MD5], array_column($this->revisions('TestLoginExtMarie'), 'password'));
        $this->assertSame([], $migration->notes());
    }

    /** CAS clears no hash and links no account, so run() walks none of the developer's accounts. */
    public function testRunUsesTheConfiguredFile(): void
    {
        $this->writeConfig(['cas_host' => 'cas.example.org']);
        $migration = new \LoginExtensionsBecomeProviders();
        $migration->setServices($this->getWiki()->services);
        $migration->setDbService($this->db);

        $migration->run();

        $config = $this->readConfig();
        $this->assertArrayNotHasKey('cas_host', $config);
        $this->assertSame('cas', $config['sso_providers']['cas']['type'] ?? null);
    }

    public function testBuildBodyKeepsLoginssoId(): void
    {
        $users = $this->getWiki()->services->get(UserManager::class);
        $users->migrateLegacyUser(['name' => self::LEGACY_USER, 'email' => 'legacy-loginsso@example.com', 'password' => 'sso-hash', 'loginsso_id' => 'subject-42']);

        $row = $this->db->loadSingle("SELECT body FROM {$this->pages} WHERE tag = ? AND latest = 'Y'", [self::LEGACY_USER]);
        $this->assertSame('subject-42', PageBody::decode((string)($row['body'] ?? ''))['loginsso_id'] ?? null);

        $users->migrateLegacyUser(['name' => 'TestLoginExtNoSso', 'email' => 'legacy-no-sso@example.com', 'password' => 'h']);
        try {
            $row = $this->db->loadSingle("SELECT body FROM {$this->pages} WHERE tag = ? AND latest = 'Y'", ['TestLoginExtNoSso']);
            $this->assertArrayNotHasKey('loginsso_id', PageBody::decode((string)($row['body'] ?? '')));
        } finally {
            $other = $users->getOneByName('TestLoginExtNoSso');
            if ($other !== null) {
                $users->delete($other);
            }
            $this->getWiki()->services->get(PageManager::class)->deleteOrphaned('TestLoginExtNoSso');
        }
    }

    public function testTheLoginExtensionsAreDoryphoreCore(): void
    {
        foreach (['loginsso', 'loginldap', 'logincas'] as $tool) {
            $this->assertContains($tool, DoryphoreLeftovers::CORE_TOOLS);
        }
    }
}
