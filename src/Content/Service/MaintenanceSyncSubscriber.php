<?php

namespace YesWiki\Content\Service;

use Psr\Container\ContainerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use YesWiki\Kernel\Entity\Event;

/**
 * Hangs the automatic import of data sources on the wiki's housekeeping (see SyncScheduler
 * for what "due" means and where the work runs).
 *
 * `maintenance.after` rather than `.before`: core's own purges are the cheap, bounded part,
 * and running them first means an import that turns out to be slow delays nothing that
 * matters. It also means the search index has already been drained when newly imported
 * entries start queueing themselves.
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
        // `startedAt` is what tells the scheduler which housekeeping run is asking, so a
        // source already taken by this one is not taken again by a concurrent page view
        $startedAt = $event->getData()['startedAt'] ?? null;
        $this->services->get(SyncScheduler::class)->onMaintenance($startedAt === null ? null : (int)$startedAt);
    }
}
