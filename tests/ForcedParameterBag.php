<?php

namespace YesWiki\Test\Core;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * The compiled container's ParameterBag is frozen at runtime (can't be mutated), so tests that need a specific config value use this decorator: it overrides a fixed set of keys and delegates everything else to the real bag.
 */
class ForcedParameterBag implements ParameterBagInterface
{
    /**
     * @param array<string, array<mixed>|bool|string|int|float|\UnitEnum|null> $overrides
     */
    public function __construct(private ParameterBagInterface $real, private array $overrides)
    {
    }

    /**
     * @return array<mixed>|bool|string|int|float|\UnitEnum|null
     */
    public function get(string $name): \UnitEnum|array|string|int|float|bool|null
    {
        return array_key_exists($name, $this->overrides) ? $this->overrides[$name] : $this->real->get($name);
    }

    public function has(string $name): bool
    {
        return array_key_exists($name, $this->overrides) || $this->real->has($name);
    }

    public function clear(): void
    {
        $this->real->clear();
    }

    /**
     * @param array<string, array<mixed>|bool|string|int|float|\UnitEnum|null> $parameters
     */
    public function add(array $parameters): void
    {
        $this->real->add($parameters);
    }

    /**
     * @return array<string, array<mixed>|bool|string|int|float|\UnitEnum|null>
     */
    public function all(): array
    {
        return $this->real->all();
    }

    public function remove(string $name): void
    {
        $this->real->remove($name);
    }

    /**
     * @param array<mixed>|bool|string|int|float|\UnitEnum|null $value
     */
    public function set(string $name, array|bool|string|int|float|\UnitEnum|null $value): void
    {
        $this->real->set($name, $value);
    }

    public function resolve(): void
    {
        $this->real->resolve();
    }

    public function resolveValue(mixed $value): mixed
    {
        return $this->real->resolveValue($value);
    }

    public function escapeValue(mixed $value): mixed
    {
        return $this->real->escapeValue($value);
    }

    public function unescapeValue(mixed $value): mixed
    {
        return $this->real->unescapeValue($value);
    }
}
