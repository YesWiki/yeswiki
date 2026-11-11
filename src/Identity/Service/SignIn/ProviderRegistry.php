<?php

namespace YesWiki\Identity\Service\SignIn;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use YesWiki\Identity\Service\SignIn\Type\LocalType;
use YesWiki\Identity\SignIn\Contract\IdentityProvider;
use YesWiki\Identity\SignIn\Contract\ProviderConfig;
use YesWiki\Identity\SignIn\Contract\ProviderType;
use YesWiki\Kernel\Service\RuntimeConfig;

/**
 * The sign-in providers of `sso_providers`, in the order of the section.
 * Nothing is kept between calls: the configuration is read each time, and the types (a lazy iterator, since the local type depends on the service that depends on this registry) are resolved when first needed.
 */
class ProviderRegistry
{
    public const SECTION = 'sso_providers';

    /** @param iterable<ProviderType> $types */
    public function __construct(
        #[AutowireIterator('yeswiki.sign_in_provider_type')] private readonly iterable $types,
        private readonly RuntimeConfig $config,
    ) {
    }

    /**
     * Two types with a same name: one of the core wins, otherwise the first.
     *
     * @return array<string, ProviderType>
     */
    public function types(): array
    {
        return $this->resolveTypes()[0];
    }

    /** @return array<string, ProviderConfig> by identifier, in the order of the section; unavailable types included */
    public function configs(): array
    {
        return $this->read()[0];
    }

    public function config(string $id): ?ProviderConfig
    {
        return $this->configs()[$id] ?? null;
    }

    /** The provider, enabled or not; null when unknown or when its type is not installed. */
    public function get(string $id): ?IdentityProvider
    {
        $config = $this->config($id);

        return $config === null ? null : $this->build($config, $this->types());
    }

    /** @return list<IdentityProvider> */
    public function enabled(): array
    {
        $types = $this->types();
        $providers = [];
        foreach ($this->configs() as $config) {
            if ($config->enabled && ($provider = $this->build($config, $types)) !== null) {
                $providers[] = $provider;
            }
        }

        return $providers;
    }

    public function isEnabled(string $id): bool
    {
        $config = $this->config($id);

        return $config !== null && $config->enabled && isset($this->types()[$config->type]);
    }

    public function localEnabled(): bool
    {
        return $this->isEnabled(LocalType::NAME);
    }

    /** @return list<string> what is wrong with the types, the section and the providers they build, for an administrator */
    public function problems(): array
    {
        $types = $this->types();
        $unbuilt = [];
        foreach ($this->configs() as $config) {
            if (isset($types[$config->type]) && ($failure = $this->buildFailure($config, $types)) !== null) {
                $unbuilt[] = sprintf('%s: the provider "%s" of type "%s" could not be built, and is left out: %s', self::SECTION, $config->id, $config->type, $failure);
            }
        }

        return array_merge($this->resolveTypes()[1], $this->read()[1], $unbuilt);
    }

    /**
     * The provider of $config, or null when its type is not installed or throws while building it: a type an extension supplies
     * must not take the sign-in page down with it, and problems() names it.
     *
     * @param array<string, ProviderType> $types
     */
    private function build(ProviderConfig $config, array $types): ?IdentityProvider
    {
        if (!isset($types[$config->type])) {
            return null;
        }
        try {
            return $types[$config->type]->create($config);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * What went wrong building the provider of $config, or null when it builds.
     *
     * @param array<string, ProviderType> $types
     */
    private function buildFailure(ProviderConfig $config, array $types): ?string
    {
        try {
            $types[$config->type]->create($config);

            return null;
        } catch (\Throwable $failed) {
            return $failed::class . ': ' . mb_substr($failed->getMessage(), 0, 300);
        }
    }

    /** @return array{0: array<string, ProviderType>, 1: list<string>} */
    private function resolveTypes(): array
    {
        $types = [];
        $problems = [];
        foreach ($this->types as $type) {
            $name = $type->name();
            if (!isset($types[$name])) {
                $types[$name] = $type;
                continue;
            }
            if (!$this->isCore($types[$name]) && $this->isCore($type)) {
                $loser = $types[$name];
                $types[$name] = $type;
            } else {
                $loser = $type;
            }
            $problems[] = sprintf('the provider type "%s" is declared twice, %s is ignored', $name, $loser::class);
        }

        return [$types, $problems];
    }

    private function isCore(ProviderType $type): bool
    {
        return !(new \ReflectionClass($type))->isAnonymous() && str_starts_with($type::class, 'YesWiki\\Identity\\');
    }

    /** @return array{0: array<string, ProviderConfig>, 1: list<string>} */
    private function read(): array
    {
        $types = $this->types();
        $section = $this->config->getValue(self::SECTION, []);
        if (!is_array($section) || $section === []) {
            $section = [LocalType::NAME => ['type' => LocalType::NAME]];
        }

        $configs = [];
        $problems = [];
        foreach ($section as $id => $raw) {
            $id = (string)$id;
            if (!is_array($raw)) {
                $problems[] = sprintf('%s: the entry "%s" is not an array', self::SECTION, $id);
                continue;
            }
            $type = $raw['type'] ?? null;
            if (!is_string($type) || $type === '') {
                $problems[] = sprintf('%s: the entry "%s" has no type', self::SECTION, $id);
                continue;
            }
            if ($type === LocalType::NAME && $id !== LocalType::NAME) {
                $problems[] = sprintf('%s: the type "local" is only accepted under the key "local", not "%s"', self::SECTION, $id);
                continue;
            }
            if (!isset($types[$type])) {
                $problems[] = sprintf('%s: the entry "%s" has the type "%s", which is not installed', self::SECTION, $id, $type);
            }
            $configs[$id] = ProviderConfig::fromArray($id, $raw, isset($types[$type]) ? $types[$type]->defaultAttributes() : []);
        }

        return [$configs, $problems];
    }
}
