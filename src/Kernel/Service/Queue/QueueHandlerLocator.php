<?php

namespace YesWiki\Kernel\Service\Queue;

use Psr\Container\ContainerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Handler\HandlerDescriptor;
use Symfony\Component\Messenger\Handler\HandlersLocatorInterface;

/** Finds the handlers of a message in the container, from the map YesWikiQueueCompilerPass built; instantiated only when consumed. */
class QueueHandlerLocator implements HandlersLocatorInterface
{
    /**
     * @param array<class-string, list<string>> $handlers
     */
    public function __construct(
        private readonly array $handlers,
        private readonly ContainerInterface $container,
    ) {
    }

    public function getHandlers(Envelope $envelope): iterable
    {
        $message = $envelope->getMessage();
        $seen = [];

        foreach ($this->handlers as $type => $ids) {
            if (!$message instanceof $type) {
                continue;
            }
            foreach ($ids as $id) {
                if (isset($seen[$id])) {
                    continue;
                }
                $seen[$id] = true;

                yield new HandlerDescriptor($this->container->get($id));
            }
        }
    }
}
