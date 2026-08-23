<?php

namespace YesWiki\Kernel\Performable;

use Psr\Container\ContainerInterface;
use YesWiki\Core\YesWikiPerformable;

/** name => service id, for actions and handlers that have become real services. */
class ActionRegistry
{
    /**
     * @var array<string, array<string, string>> type => [name => service id]
     */
    private array $map;

    /**
     * @var array<string, array<string, array{name: string, defaults: array<string, string>}>> type => [deprecated name => what it resolves to]
     */
    private array $aliases;

    private ContainerInterface $container;

    /**
     * @param array<string, array<string, string>>                                               $map
     * @param array<string, array<string, array{name: string, defaults: array<string, string>}>> $aliases
     */
    public function __construct(ContainerInterface $container, array $map = [], array $aliases = [])
    {
        $this->container = $container;
        $this->map = $map;
        $this->aliases = $aliases;
    }

    /**
     * The canonical name $name is a spelling of, and the arguments that spelling implies.
     *
     * @return array{0: string, 1: array<string, string>}
     */
    public function resolve(string $type, string $name): array
    {
        $name = strtolower($name);
        $alias = $this->aliases[$type][$name] ?? null;

        return $alias === null ? [$name, []] : [$alias['name'], $alias['defaults']];
    }

    public function has(string $type, string $name): bool
    {
        return isset($this->map[$type][strtolower($name)]);
    }

    /** The service instance for $name, or null when it has not been converted yet. */
    public function get(string $type, string $name): ?YesWikiPerformable
    {
        $id = $this->map[$type][strtolower($name)] ?? null;
        if ($id === null) {
            return null;
        }
        $service = $this->container->get($id);

        return $service instanceof YesWikiPerformable ? $service : null;
    }

    /**
     * @return list<string>
     */
    public function names(string $type): array
    {
        return array_keys($this->map[$type] ?? []);
    }
}
