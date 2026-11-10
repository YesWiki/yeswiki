<?php

namespace YesWiki\Test\Identity\SignIn;

use YesWiki\Identity\Service\SignIn\AccountLinks;
use YesWiki\Identity\Service\SignIn\ProviderRegistry;
use YesWiki\Identity\Service\SignIn\SignInHealthChecks;
use YesWiki\Identity\SignIn\Contract\IdentityProvider;
use YesWiki\Identity\SignIn\Contract\ProviderConfig;
use YesWiki\Identity\SignIn\Contract\ProviderType;
use YesWiki\Kernel\Health\HealthCheck;
use YesWiki\Kernel\Health\Severity;
use YesWiki\Kernel\Service\RuntimeConfig;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

class SignInHealthChecksTest extends YesWikiTestCase
{
    private function type(string $name): ProviderType
    {
        return new class($name) implements ProviderType {
            public function __construct(private string $name)
            {
            }

            public function name(): string
            {
                return $this->name;
            }

            public function label(): string
            {
                return $this->name;
            }

            public function connectionSettings(): array
            {
                return [];
            }

            public function defaultAttributes(): array
            {
                return [];
            }

            public function create(ProviderConfig $config): IdentityProvider
            {
                return new class($config) implements IdentityProvider {
                    public function __construct(private ProviderConfig $config)
                    {
                    }

                    public function id(): string
                    {
                        return $this->config->id;
                    }

                    public function label(): string
                    {
                        return $this->config->label;
                    }

                    public function type(): string
                    {
                        return $this->config->type;
                    }
                };
            }
        };
    }

    /**
     * @param array<string, mixed> $section
     * @param array<string, int> $counts
     * @param list<ProviderType>|null $types
     */
    private function checks(array $section, array $counts = [], bool $ldapLoaded = true, ?array $types = null): SignInHealthChecks
    {
        self::getWiki(); // loads _t()
        $values = ['sso_providers' => $section];
        $config = new RuntimeConfig();
        $config->bind($values);
        $registry = new ProviderRegistry($types ?? [$this->type('local'), $this->type('oidc'), $this->type('ldap')], $config);
        $links = $this->createMock(AccountLinks::class);
        $links->method('countByProvider')->willReturn($counts);

        return new class($registry, $links, $ldapLoaded) extends SignInHealthChecks {
            public function __construct(ProviderRegistry $registry, AccountLinks $links, private bool $loaded)
            {
                parent::__construct($registry, $links);
            }

            protected function ldapLoaded(): bool
            {
                return $this->loaded;
            }
        };
    }

    private function check(string $id, array $section, array $counts = [], bool $ldapLoaded = true, ?array $types = null): HealthCheck
    {
        foreach ($this->checks($section, $counts, $ldapLoaded, $types)->healthChecks() as $check) {
            if ($check->id() === $id) {
                return $check;
            }
        }
        $this->fail("no check $id");
    }

    public function testNoEnabledProviderIsBroken(): void
    {
        $check = $this->check('sign-in-no-provider', ['local' => ['type' => 'local', 'enabled' => false]]);
        $this->assertNotNull($check->failure());
        $this->assertSame(Severity::Broken, $check->severity());
    }

    public function testASectionOfOnlyBrokenEntriesLeavesNobodyAbleToSignIn(): void
    {
        $this->assertNotNull($this->check('sign-in-no-provider', ['a' => 'nope', 'b' => ['type' => 'gone']])->failure());
    }

    public function testUnknownTypeIsReported(): void
    {
        $this->assertStringContainsString('gone', $this->check('sign-in-config', ['a' => ['type' => 'gone']])->failure());
    }

    public function testATypeNameCollisionIsReported(): void
    {
        $failure = $this->check('sign-in-config', [], [], true, [$this->type('local'), $this->type('local')])->failure();
        $this->assertStringContainsString('declared twice', $failure);
    }

    public function testAccountsOnAVanishedProviderAreReported(): void
    {
        $section = ['local' => ['type' => 'local'], 'corp' => ['type' => 'oidc']];
        $failure = $this->check('sign-in-orphan-accounts', $section, ['corp' => 3, 'old' => 2])->failure();
        $this->assertNotNull($failure);
        $this->assertStringContainsString('old', $failure);
        $this->assertStringNotContainsString('corp', $failure);
    }

    public function testLdapWithoutTheModuleIsReported(): void
    {
        $section = ['local' => ['type' => 'local'], 'dir' => ['type' => 'ldap']];
        $this->assertNotNull($this->check('sign-in-ldap-module', $section, [], false)->failure());
        $this->assertNull($this->check('sign-in-ldap-module', $section, [], true)->failure());
        $disabled = ['local' => ['type' => 'local'], 'dir' => ['type' => 'ldap', 'enabled' => false]];
        $this->assertNull($this->check('sign-in-ldap-module', $disabled, [], false)->failure());
    }

    public function testAHealthyWikiReportsNothing(): void
    {
        $section = ['local' => ['type' => 'local'], 'corp' => ['type' => 'oidc']];
        foreach ($this->checks($section, ['corp' => 4, 'local' => 1])->healthChecks() as $check) {
            $this->assertNull($check->failure(), $check->id());
            $this->assertSame('admin/sign-in', $check->route(), $check->id());
        }
        foreach ($this->checks([])->healthChecks() as $check) {
            $this->assertNull($check->failure(), $check->id());
        }
    }
}
