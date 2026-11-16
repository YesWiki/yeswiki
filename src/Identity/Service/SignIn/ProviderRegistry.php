<?php

namespace YesWiki\Identity\Service\SignIn;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use YesWiki\Identity\Service\SignIn\Type\LocalType;
use YesWiki\Identity\SignIn\Contract\ChecksTheWikisOwnAccounts;
use YesWiki\Identity\SignIn\Contract\IdentityProvider;
use YesWiki\Identity\SignIn\Contract\ProviderConfig;
use YesWiki\Identity\SignIn\Contract\ProviderType;
use YesWiki\Kernel\Service\RuntimeConfig;

/**
 * The sign-in providers of `sso_providers`, in the order of the section.
 * The types (a lazy iterator, since the local type depends on the service that depends on this registry) are resolved when first
 * needed, then kept: they cannot change for the container's lifetime. The section is parsed again only when it differs from the one
 * parsed last, so an edit made through ProvidersEditor is seen at once. Providers are never kept: they are built on each call.
 */
class ProviderRegistry
{
    public const SECTION = 'sso_providers';

    /** @var array{0: array<string, ProviderType>, 1: list<string>}|null */
    private ?array $resolvedTypes = null;

    private mixed $parsedSection = null;

    /** @var array{0: array<string, ProviderConfig>, 1: list<string>}|null */
    private ?array $parsed = null;

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

    /**
     * The provider's name as people read it: the provider's own label (local's is translated), or, when the entry gives none, the
     * label of its type. The identifier is used only when the type is not installed.
     */
    public function labelOf(ProviderConfig $config): string
    {
        $types = $this->types();
        $label = $this->build($config, $types)?->label() ?? $config->label;
        if ($label === $config->id && isset($types[$config->type])) {
            $label = $types[$config->type]->label();
        }

        return $label;
    }

    /** labelOf() for an identifier, which is itself the name when no entry has it. */
    public function labelFor(string $id): string
    {
        $config = $this->config($id);

        return $config !== null ? $this->labelOf($config) : $id;
    }

    public function isEnabled(string $id): bool
    {
        $config = $this->config($id);

        return $config !== null && $config->enabled && isset($this->types()[$config->type]);
    }

    /**
     * Whether the type named $type signs in the wiki's own accounts (ChecksTheWikisOwnAccounts); false when it is not installed.
     * Only core's types are believed: an extension adds a protocol and cannot change the account rules (ADR-0031).
     */
    public function checksTheWikisOwnAccounts(string $type): bool
    {
        $found = $this->types()[$type] ?? null;

        return $found instanceof ChecksTheWikisOwnAccounts && $this->isCore($found);
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
            if (isset($types[$config->type]) && is_string($failure = $this->attempt($types[$config->type], $config))) {
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
        $built = $this->attempt($types[$config->type], $config);

        return $built instanceof IdentityProvider ? $built : null;
    }

    /** The provider $type builds for $config, or what went wrong building it. */
    private function attempt(ProviderType $type, ProviderConfig $config): IdentityProvider|string
    {
        try {
            return $type->create($config);
        } catch (\Throwable $failed) {
            return $failed::class . ': ' . mb_substr($failed->getMessage(), 0, 300);
        }
    }

    /** @return array{0: array<string, ProviderType>, 1: list<string>} */
    private function resolveTypes(): array
    {
        if ($this->resolvedTypes !== null) {
            return $this->resolvedTypes;
        }
        $types = [];
        $problems = [];
        foreach ($this->types as $type) {
            $name = $type->name();
            if ($type instanceof ChecksTheWikisOwnAccounts && !$this->isCore($type)) {
                $problems[] = sprintf('the provider type "%s" (%s) says it checks the wiki\'s own accounts, which only core\'s may: it is treated like any other', $name, $type::class);
            }
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

        return $this->resolvedTypes = [$types, $problems];
    }

    private function isCore(ProviderType $type): bool
    {
        return !(new \ReflectionClass($type))->isAnonymous() && str_starts_with($type::class, 'YesWiki\\Identity\\');
    }

    /** @return array{0: array<string, ProviderConfig>, 1: list<string>} */
    private function read(): array
    {
        $section = $this->config->getValue(self::SECTION, []);
        if ($this->parsed !== null && $section === $this->parsedSection) {
            return $this->parsed;
        }
        $this->parsedSection = $section;
        $types = $this->types();
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
            if ($this->checksTheWikisOwnAccounts($type) && $id !== $type) {
                $problems[] = sprintf('%s: the type "%s" is only accepted under the key "%s", not "%s"', self::SECTION, $type, $type, $id);
                continue;
            }
            if (!isset($types[$type])) {
                $problems[] = sprintf('%s: the entry "%s" has the type "%s", which is not installed', self::SECTION, $id, $type);
            }
            $configs[$id] = ProviderConfig::fromArray($id, $raw, isset($types[$type]) ? $types[$type]->defaultAttributes() : []);
        }

        return $this->parsed = [$configs, $problems];
    }
}
