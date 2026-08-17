<?php

namespace YesWiki\Import\Service;

use Psr\Container\ContainerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use YesWiki\Kernel\Entity\Event;

/**
 * Hangs the automatic import of data sources on the wiki's housekeeping (see SyncScheduler for what "due" means and where the work runs).
 */
class MaintenanceSyncSubscriber implements EventSubscriberInterface
{
    private ContainerInterface $services;

    public function __construct(ContainerInterface $services)
    {
        $this->services = $services;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'maintenance.after' => 'onMaintenanceDone',
        ];
    }

    public function onMaintenanceDone(Event $event): void
    {
        $startedAt = $event->getData()['startedAt'] ?? null;
        $this->services->get(SyncScheduler::class)->onMaintenance($startedAt === null ? null : (int)$startedAt);
    }
}
