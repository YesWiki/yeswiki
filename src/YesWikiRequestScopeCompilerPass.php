<?php

namespace YesWiki\Core;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use YesWiki\Kernel\Service\RequestScope;
use YesWiki\Kernel\Service\RequestScopedState;

/**
 * Collects every service that holds request state, so the runtime can start a fresh request.
 *
 * Discovered from the interface rather than from a tag or a list: a class that says it holds
 * request state is registered by saying so, which is the answer to ADR-0024's objection that a
 * reset routine is a list somebody has to remember to extend.
 */
class YesWikiRequestScopeCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->has(RequestScope::class)) {
            return;
        }

        $ids = [];
        foreach ($container->getDefinitions() as $id => $definition) {
            $class = $definition->getClass();
            if ($class !== null && is_subclass_of($class, RequestScopedState::class)) {
                $definition->setPublic(true);
                $ids[] = $id;
            }
        }
        sort($ids);

        $container->findDefinition(RequestScope::class)->setArgument('$serviceIds', $ids);
    }
}
