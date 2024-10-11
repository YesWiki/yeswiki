<?php

/**
 * inspired from https://symfony.com/doc/current/service_container/tags.html#create-a-compiler-pass.
 */

namespace YesWiki\Core;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use YesWiki\Core\Service\EventDispatcher;

class YesWikiEventCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        // always first check if the primary service is defined
        if (!$container->has(EventDispatcher::class)) {
            return;
        }
        $definition = $container->findDefinition(EventDispatcher::class);

        // find all service IDs with the yeswiki.event_subscriber tag
        $taggedServices = $container->findTaggedServiceIds('yeswiki.event_subscriber');

        foreach ($taggedServices as $id => $tags) {
            // add the service to the EventDispatcher service
            $definition->addMethodCall('addSubscriber', [new Reference($id)]);
        }
    }
}
