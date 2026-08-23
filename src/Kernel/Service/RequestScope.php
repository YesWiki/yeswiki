<?php

namespace YesWiki\Kernel\Service;

use Psr\Container\ContainerInterface;

/** Starts a fresh request for every service that holds request state. */
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
