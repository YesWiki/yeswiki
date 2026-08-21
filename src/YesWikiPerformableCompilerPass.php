<?php

namespace YesWiki\Core;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use YesWiki\Kernel\Performable\ActionRegistry;
use YesWiki\Kernel\Performable\AliasesPerformable;
use YesWiki\Kernel\Performable\RegisteredPerformable;

/**
 * Builds ActionRegistry's name => service-id map from the yeswiki.action / yeswiki.handler tags, mirroring YesWikiEventCompilerPass.
 */
class YesWikiPerformableCompilerPass implements CompilerPassInterface
{
    private const TAGS = [
        'action' => 'yeswiki.action',
        'handler' => 'yeswiki.handler',
    ];

    public function process(ContainerBuilder $container): void
    {
        if (!$container->has(ActionRegistry::class)) {
            return;
        }

        $map = [];
        $aliases = [];
        foreach (self::TAGS as $type => $tag) {
            $map[$type] = [];
            $aliases[$type] = [];
            foreach (array_keys($container->findTaggedServiceIds($tag)) as $id) {
                $class = $container->getDefinition($id)->getClass();
                if ($class === null || !is_subclass_of($class, RegisteredPerformable::class)) {
                    continue;
                }
                $name = strtolower($class::performableName());
                $map[$type][$name] = $id;

                // A deprecated spelling resolves to the canonical name before the ACL check
                // and before anything is parsed, so an alias cannot drift from what it
                // aliases (ticket 49).
                if (is_subclass_of($class, AliasesPerformable::class)) {
                    foreach ($class::performableAliases() as $alias => $defaults) {
                        $aliases[$type][strtolower($alias)] = ['name' => $name, 'defaults' => $defaults];
                    }
                }
            }
        }

        $container->findDefinition(ActionRegistry::class)->setArgument('$map', $map);
        $container->findDefinition(ActionRegistry::class)->setArgument('$aliases', $aliases);
    }
}
