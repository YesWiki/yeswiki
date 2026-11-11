<?php

namespace YesWiki\Test\Identity\SignIn;

use YesWiki\Identity\Service\SignIn\ProviderRegistry;
use YesWiki\Identity\Service\SignIn\Type\LocalType;
use YesWiki\Identity\Service\SignIn\Type\OidcType;
use YesWiki\Identity\SignIn\Contract\IdentityProvider;
use YesWiki\Identity\SignIn\Contract\ProviderConfig;
use YesWiki\Identity\SignIn\Contract\ProviderType;
use YesWiki\Identity\SignIn\Provider\OidcProvider;
use YesWiki\Kernel\Service\RuntimeConfig;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

class ProviderRegistryTest extends YesWikiTestCase
{
    private function fakeType(string $name): ProviderType
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

    /** @param array<string, mixed> $section */
    private function registry(array $section): ProviderRegistry
    {
        $values = ['sso_providers' => $section];
        $config = new RuntimeConfig();
        $config->bind($values);

        return new ProviderRegistry([$this->fakeType('local'), $this->fakeType('oidc')], $config);
    }

    public function testNoSectionMeansLocalIsOn(): void
    {
        $values = [];
        $config = new RuntimeConfig();
        $config->bind($values);
        $registry = new ProviderRegistry([$this->fakeType('local')], $config);
        $this->assertTrue($registry->localEnabled());
        $this->assertSame(['local'], array_map(fn ($p) => $p->id(), $registry->enabled()));
    }

    public function testOrderOfTheSectionIsKept(): void
    {
        $r = $this->registry(['b' => ['type' => 'oidc'], 'a' => ['type' => 'oidc'], 'local' => ['type' => 'local']]);
        $this->assertSame(['b', 'a', 'local'], array_map(fn ($p) => $p->id(), $r->enabled()));
    }

    public function testASectionWithoutLocalTurnsLocalOff(): void
    {
        $r = $this->registry(['a' => ['type' => 'oidc']]);
        $this->assertFalse($r->localEnabled());
    }

    public function testASwitchedOffProviderIsNotEnabledButIsKnown(): void
    {
        $r = $this->registry(['a' => ['type' => 'oidc', 'enabled' => false]]);
        $this->assertSame([], $r->enabled());
        $this->assertNotNull($r->config('a'));
        $this->assertFalse($r->isEnabled('a'));
    }

    public function testBrokenEntriesAreSkippedAndReported(): void
    {
        $r = $this->registry(['ok' => ['type' => 'oidc'], 'noarray' => 'x', 'notype' => ['label' => 'y'], 'gone' => ['type' => 'saml']]);
        $this->assertSame(['ok'], array_map(fn ($p) => $p->id(), $r->enabled()));
        $this->assertCount(3, $r->problems());
        $this->assertNotNull($r->config('gone'), 'an unknown type stays known, as unavailable');
        $this->assertNull($r->get('gone'));
    }

    /** An extension's type that throws while building its provider leaves that provider out, not the sign-in page. */
    public function testAProviderThatCannotBeBuiltIsLeftOutAndReported(): void
    {
        $broken = new class implements ProviderType {
            public function name(): string
            {
                return 'broken';
            }

            public function label(): string
            {
                return 'Broken';
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
                throw new \TypeError('the extension expected a string');
            }
        };
        $values = ['sso_providers' => ['local' => ['type' => 'local'], 'odd' => ['type' => 'broken'], 'ok' => ['type' => 'oidc']]];
        $config = new RuntimeConfig();
        $config->bind($values);
        $registry = new ProviderRegistry([$this->fakeType('local'), $this->fakeType('oidc'), $broken], $config);

        $this->assertSame(['local', 'ok'], array_map(fn ($p) => $p->id(), $registry->enabled()));
        $this->assertNull($registry->get('odd'));
        $this->assertNotNull($registry->get('ok'));
        $this->assertCount(1, $registry->problems());
        $this->assertStringContainsString('"odd"', $registry->problems()[0]);
        $this->assertStringContainsString('the extension expected a string', $registry->problems()[0]);
    }

    public function testLocalAcceptsOnlyTheKeyLocal(): void
    {
        $r = $this->registry(['mine' => ['type' => 'local']]);
        $this->assertNull($r->config('mine'));
        $this->assertCount(1, $r->problems());
    }

    public function testACoreTypeNameCannotBeTaken(): void
    {
        $values = [];
        $config = new RuntimeConfig();
        $config->bind($values);
        $core = self::getWiki()->services->get(LocalType::class);
        $registry = new ProviderRegistry([$this->fakeType('local'), $core], $config);
        $this->assertSame($core, $registry->types()['local']);
        $this->assertCount(1, $registry->problems());
    }

    public function testTheCoreShipsOidc(): void
    {
        $type = self::getWiki()->services->get(ProviderRegistry::class)->types()['oidc'] ?? null;
        $this->assertInstanceOf(OidcType::class, $type);

        $secrets = array_map(fn ($setting): string => $setting->name(), array_filter($type->connectionSettings(), fn ($setting): bool => $setting->type() === 'secret'));
        $this->assertSame(['client_secret'], array_values($secrets), 'the secret is declared, so the screen never shows it and the Journal scrubs it');
        $this->assertSame(['id' => 'sub', 'email' => 'email', 'first_name' => 'given_name', 'last_name' => 'family_name', 'groups' => 'groups'], $type->defaultAttributes());
        $this->assertInstanceOf(OidcProvider::class, $type->create(ProviderConfig::fromArray('acme', ['type' => 'oidc', 'label' => 'Acme'])));
    }
}
