<?php

namespace YesWiki\Kernel\Performable;

use Psr\Container\ContainerInterface;
use YesWiki\Core\YesWikiPerformable;

/**
 * name => service id, for actions and handlers that have become real services.
 *
 * Populated at compile time by YesWikiPerformableCompilerPass from the `yeswiki.action`
 * and `yeswiki.handler` tags, so nothing is instantiated to build the map.
 *
 * Coexists with Performer's directory scan during ticket 06's migration: Performer asks
 * the registry first and falls back to scanning, so actions convert a few at a time rather
 * than in one 137-file step. When the last one has moved, the scan is deleted along with
 * runFileInBuffer().
 */
class ActionRegistry
{
    /** @var array<string, array<string, string>> type => [name => service id] */
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

        // A tagged class that is not a performable cannot be run; treat it as absent rather
        // than fataling on the first setWiki() call.
        return $service instanceof YesWikiPerformable ? $service : null;
    }

    /** @return list<string> */
    public function names(string $type): array
    {
        return array_keys($this->map[$type] ?? []);
    }
}
