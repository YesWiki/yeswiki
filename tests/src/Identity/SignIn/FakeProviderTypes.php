<?php

namespace YesWiki\Test\Identity\SignIn;

use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use YesWiki\Identity\Service\SignIn\ProviderRegistry;
use YesWiki\Identity\Service\SignIn\Type\LocalType;
use YesWiki\Identity\SignIn\Contract\Handshake;
use YesWiki\Identity\SignIn\Contract\IdentityProvider;
use YesWiki\Identity\SignIn\Contract\PasswordProvider;
use YesWiki\Identity\SignIn\Contract\ProviderConfig;
use YesWiki\Identity\SignIn\Contract\ProviderIdentity;
use YesWiki\Identity\SignIn\Contract\ProviderType;
use YesWiki\Identity\SignIn\Contract\RedirectProvider;
use YesWiki\Kernel\Service\RuntimeConfig;

/** Provider types for tests that need a registry with something else than the local type, installed in the wiki's container for the length of a test. */
trait FakeProviderTypes
{
    /** @var array{0: bool, 1: ?object}|null the registry the container held before, when it held one */
    private ?array $registryWas = null;

    /**
     * Puts a registry in the container in place of the real one. The container refuses to replace a service that is already built, and the real registry is built by the time any test runs, so this writes the service table directly: a test-only seam.
     *
     * @param array<string, array<string, mixed>> $section
     * @param ProviderType                        ...$more types a test needs besides the fake ones
     */
    private function installProviders(array $section, ProviderType ...$more): void
    {
        $services = self::getWiki()->services;
        $table = new \ReflectionProperty(Container::class, 'services');
        $current = $table->getValue($services);
        $this->registryWas ??= [array_key_exists(ProviderRegistry::class, $current), $current[ProviderRegistry::class] ?? null];
        $values = ['sso_providers' => $section];
        $config = new RuntimeConfig();
        $config->bind($values);
        $current[ProviderRegistry::class] = new ProviderRegistry([
            $services->get(LocalType::class),
            self::fakeType('fakeredirect', true),
            self::fakeType('fakepw', false),
            ...$more,
        ], $config);
        $table->setValue($services, $current);
    }

    private function restoreProviders(): void
    {
        if ($this->registryWas === null) {
            return;
        }
        $services = self::getWiki()->services;
        $table = new \ReflectionProperty(Container::class, 'services');
        $current = $table->getValue($services);
        unset($current[ProviderRegistry::class]);
        if ($this->registryWas[0]) {
            $current[ProviderRegistry::class] = $this->registryWas[1];
        }
        $table->setValue($services, $current);
        $this->registryWas = null;
    }

    private static function fakeType(string $name, bool $redirect): ProviderType
    {
        return new class($name, $redirect) implements ProviderType {
            public function __construct(private string $name, private bool $redirect)
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
                return $this->redirect ? new class($config) extends FakeIdentity implements RedirectProvider {
                    public function begin(Handshake $handshake): Response
                    {
                        return new RedirectResponse('https://idp.example/');
                    }

                    public function complete(Request $request, Handshake $handshake): ProviderIdentity
                    {
                        return new ProviderIdentity([]);
                    }
                } : new class($config) extends FakeIdentity implements PasswordProvider {
                    public function authenticate(string $login, #[\SensitiveParameter] string $password): ProviderIdentity
                    {
                        return new ProviderIdentity([]);
                    }
                };
            }
        };
    }
}

abstract class FakeIdentity implements IdentityProvider
{
    public function __construct(protected ProviderConfig $config)
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
}
