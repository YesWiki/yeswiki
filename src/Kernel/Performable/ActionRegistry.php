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

    private ContainerInterface $container;

    /**
     * @param array<string, array<string, string>> $map
     */
    public function __construct(ContainerInterface $container, array $map = [])
    {
        $this->container = $container;
        $this->map = $map;
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
