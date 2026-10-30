<?php

namespace YesWiki\Core;

use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use YesWiki\Kernel\Service\Queue\QueueHandlerLocator;

/**
 * Builds QueueHandlerLocator's message => service-ids map from the `#[AsMessageHandler]` attributes, mirroring YesWikiPerformableCompilerPass.
 *
 * The convention is the one every handler of the wiki follows: a class carrying the attribute,
 * with a `__invoke(<Message> $message)` method. The message is read off that parameter, so the
 * attribute needs no argument and a handler cannot disagree with its own signature.
 */
class YesWikiQueueCompilerPass implements CompilerPassInterface
{
    public const TAG = 'yeswiki.message_handler';

    /** Turns the attribute into a tag carrying the message type: called by the kernel's build(). */
    public static function autoconfigure(ContainerBuilder $container): void
    {
        $container->registerAttributeForAutoconfiguration(
            AsMessageHandler::class,
            static function (ChildDefinition $definition, AsMessageHandler $attribute, \Reflector $reflector): void {
                if (!$reflector instanceof \ReflectionClass) {
                    throw new \LogicException(sprintf('#[AsMessageHandler] goes on the class, not on "%s": a handler is a class with an __invoke() method.', $reflector instanceof \ReflectionMethod ? $reflector->class . '::' . $reflector->name . '()' : $reflector::class));
                }
                $definition->addTag(self::TAG, ['handles' => $attribute->handles ?? self::messageTypeOf($reflector)]);
            }
        );
    }

    public function process(ContainerBuilder $container): void
    {
        if (!$container->has(QueueHandlerLocator::class)) {
            return;
        }

        $handlers = [];
        foreach ($container->findTaggedServiceIds(self::TAG) as $id => $tags) {
            // The locator fetches the service by id when its message is consumed, so it must be reachable.
            $container->findDefinition($id)->setPublic(true);
            foreach ($tags as $tag) {
                $handlers[$tag['handles']][] = $id;
            }
        }

        $definition = $container->findDefinition(QueueHandlerLocator::class);
        $definition->setArgument('$handlers', $handlers);
        $definition->setArgument('$container', new Reference('service_container'));
    }

    /** @param \ReflectionClass<object> $handler */
    private static function messageTypeOf(\ReflectionClass $handler): string
    {
        if (!$handler->hasMethod('__invoke')) {
            throw new \LogicException(sprintf('"%s" is a message handler and has no __invoke() method.', $handler->name));
        }
        $parameter = $handler->getMethod('__invoke')->getParameters()[0] ?? null;
        $type = $parameter?->getType();
        if (!$type instanceof \ReflectionNamedType || $type->isBuiltin()) {
            throw new \LogicException(sprintf('The first parameter of "%s::__invoke()" must be typed with the message class it handles.', $handler->name));
        }

        return $type->getName();
    }
}
