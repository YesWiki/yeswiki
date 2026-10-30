<?php

namespace YesWiki\Kernel\Service\Queue;

use Psr\Container\ContainerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Handler\HandlerDescriptor;
use Symfony\Component\Messenger\Handler\HandlersLocatorInterface;

/**
 * Finds the handlers of a message in the container, from the map YesWikiQueueCompilerPass built out of the `#[AsMessageHandler]` attributes.
 *
 * A handler is only instantiated when a message it handles is being consumed: the services behind
 * a handler (search, federation, mail) are not built by a page that merely dispatches.
 */
class QueueHandlerLocator implements HandlersLocatorInterface
{
    /**
     * @param array<class-string, list<string>> $handlers message class (or parent class, or interface) => ids of the services handling it
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

    /** @return list<class-string> */
    public function handledTypes(): array
    {
        return array_keys($this->handlers);
    }
}
