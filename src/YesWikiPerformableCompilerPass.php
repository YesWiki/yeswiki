<?php

namespace YesWiki\Core;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use YesWiki\Kernel\Performable\ActionRegistry;
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
        foreach (self::TAGS as $type => $tag) {
            $map[$type] = [];
            foreach (array_keys($container->findTaggedServiceIds($tag)) as $id) {
                $class = $container->getDefinition($id)->getClass();
                if ($class === null || !is_subclass_of($class, RegisteredPerformable::class)) {
                    continue;
                }
                $map[$type][strtolower($class::performableName())] = $id;
            }
        }

        $container->findDefinition(ActionRegistry::class)->setArgument('$map', $map);
    }
}
