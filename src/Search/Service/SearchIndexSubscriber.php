<?php

namespace YesWiki\Search\Service;

use Psr\Container\ContainerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use YesWiki\Core\YesWikiKernel;
use YesWiki\Kernel\Entity\Event;
use YesWiki\Kernel\Service\ConsoleService;

/** Keeps the search index in step with the wiki (ticket 18 / ADR-0015). */
class SearchIndexSubscriber implements EventSubscriberInterface
{
    /** Contents reindexed at the end of a request before the rest is left to the queue. */
    private const FLUSH_LIMIT = 200;

    /** Seconds the end-of-request flush may take before it leaves the rest queued. */
    private const FLUSH_BUDGET_SEC = 5;

    private ContainerInterface $container;
    private bool $flushRegistered = false;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'page.created' => ['onContentChanged'],
            'page.updated' => ['onContentChanged'],
            'page.deleted' => ['onContentDeleted'],
            'entry.created' => ['onContentChanged'],
            'entry.updated' => ['onContentChanged'],
            'entry.deleted' => ['onContentDeleted'],
            'comment.created' => ['onContentChanged'],
            'comment.updated' => ['onContentChanged'],
            'comment.deleted' => ['onContentDeleted'],
            'form.updated' => ['onFormChanged'],
            'form.deleted' => ['onFormChanged'],
        ];
    }

    public function onContentChanged(Event $event): void
    {
        $tag = $this->tagOf($event);
        if ($tag === '') {
            return;
        }

        $this->indexer()->enqueue([$tag]);
        $this->flushWhenTheRequestIsOver();
    }

    /** Deleting is safe to do inline: it is one `DELETE ... */
    public function onContentDeleted(Event $event): void
    {
        $tag = $this->tagOf($event);
        if ($tag !== '') {
            $this->indexer()->delete($tag);
        }
    }

    /** Queue the form's entries, then try to drain them out of band. */
    public function onFormChanged(Event $event): void
    {
        $formId = (string)($event->getData()['id'] ?? '');
        if ($formId === '') {
            return;
        }

        if ($this->indexer()->enqueueForm($formId) > 0) {
            $this->spawnReindex();
        }
    }

    /**
     * Drain what this request queued, once it is finished writing.
     *
     * A request only. A command has no reason to leave its work to a shutdown function -- it either
     * drains the queue itself or leaves it to `search:reindex` -- and `migrate` ends by clearing
     * `cache/container`, so a flush running after that would ask a container whose files have just
     * been deleted for a service (ticket 54's hazard, in the CLI).
     */
    private function flushWhenTheRequestIsOver(): void
    {
        if ($this->flushRegistered || YesWikiKernel::isCli()) {
            return;
        }
        $this->flushRegistered = true;

        $container = $this->container;
        register_shutdown_function(static function () use ($container): void {
            try {
                $container->get(SearchIndexer::class)->drain(self::FLUSH_LIMIT, self::FLUSH_BUDGET_SEC);
            } catch (\Throwable $failed) {
            }
        });
    }

    private function spawnReindex(): void
    {
        try {
            $this->container->get(ConsoleService::class)->startConsoleAsync('search:reindex', ['--drain']);
        } catch (\Throwable $unavailable) {
        }
    }

    private function tagOf(Event $event): string
    {
        $data = $event->getData();

        return (string)($data['id'] ?? $data['data']['tag'] ?? '');
    }

    private function indexer(): SearchIndexer
    {
        return $this->container->get(SearchIndexer::class);
    }
}
