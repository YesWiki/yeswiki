<?php

namespace YesWiki\Core;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use YesWiki\Kernel\Service\CachesTheDatabase;
use YesWiki\Kernel\Service\RequestScope;

/** Collects every service that holds request state, so the runtime can start a fresh request. */
class YesWikiRequestScopeCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->has(RequestScope::class)) {
            return;
        }

        $ids = array_keys($container->findTaggedServiceIds('yeswiki.request_scoped'));
        foreach ($ids as $id) {
            $container->getDefinition($id)->setPublic(true);
        }
        sort($ids);
        $databaseCaches = array_values(array_filter($ids, static fn (string $id): bool => is_a((string)$container->findDefinition($id)->getClass(), CachesTheDatabase::class, true)));

        $container->findDefinition(RequestScope::class)->setArgument('$serviceIds', $ids)->setArgument('$databaseCacheIds', $databaseCaches);
    }
}
