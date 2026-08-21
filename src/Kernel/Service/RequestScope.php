<?php

namespace YesWiki\Kernel\Service;

use Psr\Container\ContainerInterface;

/**
 * Starts a fresh request for every service that holds request state.
 *
 * The runtime calls this once, before serving. Under php-fpm the process dies with the request so
 * it changes nothing; under worker mode (ADR-0024) it is what stops one visitor's counters, flags
 * and stacks from becoming the next visitor's.
 *
 * The list of services comes from the container, built from who implements
 * `RequestScopedState` rather than written down anywhere.
 */
class RequestScope
{
    /**
     * @param list<string> $serviceIds the services that implement RequestScopedState
     */
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly array $serviceIds = []
    ) {
    }

    public function startNewRequest(): void
    {
        foreach ($this->serviceIds as $id) {
            $service = $this->container->get($id);
            if ($service instanceof RequestScopedState) {
                $service->startNewRequest();
            }
        }
    }

    /**
     * What is being reset, for the test that proves the mechanism is wired.
     *
     * @return list<string>
     */
    public function services(): array
    {
        return $this->serviceIds;
    }
}
