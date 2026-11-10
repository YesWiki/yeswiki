<?php

namespace YesWiki\Kernel\Service;

use Psr\Container\ContainerInterface;

/** Starts a fresh request for every service that holds request state. */
class RequestScope
{
    /**
     * @param list<string> $serviceIds       the services that implement RequestScopedState
     * @param list<string> $databaseCacheIds those of them that implement CachesTheDatabase
     */
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly array $serviceIds = [],
        private readonly array $databaseCacheIds = [],
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

    /** Forgets only what services cached of the database, for a consumer between two messages, whose other state belongs to its pass. */
    public function forgetTheDatabase(): void
    {
        foreach ($this->databaseCacheIds as $id) {
            $service = $this->container->get($id);
            if ($service instanceof CachesTheDatabase) {
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
