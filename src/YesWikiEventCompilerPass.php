<?php

/**
 * inspired from https://symfony.com/doc/current/service_container/tags.html#create-a-compiler-pass.
 */

namespace YesWiki\Core;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use YesWiki\Kernel\Service\EventDispatcher;

class YesWikiEventCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->has(EventDispatcher::class)) {
            return;
        }
        $definition = $container->findDefinition(EventDispatcher::class);

        $taggedServices = $container->findTaggedServiceIds('yeswiki.event_subscriber');

        foreach ($taggedServices as $id => $tags) {
            $definition->addMethodCall('addSubscriber', [new Reference($id)]);
        }
    }
}
