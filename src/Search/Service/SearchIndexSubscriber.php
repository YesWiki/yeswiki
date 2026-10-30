<?php

namespace YesWiki\Search\Service;

use Psr\Container\ContainerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use YesWiki\Kernel\Entity\Event;

/**
 * Keeps the search index in step with the wiki (ticket 18 / ADR-0015).
 *
 * It only queues: SearchIndexer sends one ReindexContent per Content, and the queue's
 * consumer runs them, so a save neither spawns a process nor registers work for the end of the request.
 */
class SearchIndexSubscriber implements EventSubscriberInterface
{
    private ContainerInterface $container;

    /** Ahead of the other listeners, since one throwing stops the rest and the index is local and cheap. */
    private const PRIORITY = 100;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'page.created' => ['onContentChanged', self::PRIORITY],
            'page.updated' => ['onContentChanged', self::PRIORITY],
            'page.deleted' => ['onContentDeleted', self::PRIORITY],
            'entry.created' => ['onContentChanged', self::PRIORITY],
            'entry.updated' => ['onContentChanged', self::PRIORITY],
            'entry.deleted' => ['onContentDeleted', self::PRIORITY],
            'comment.created' => ['onContentChanged', self::PRIORITY],
            'comment.updated' => ['onContentChanged', self::PRIORITY],
            'comment.deleted' => ['onContentDeleted', self::PRIORITY],
            'form.updated' => ['onFormChanged', self::PRIORITY],
            'form.deleted' => ['onFormChanged', self::PRIORITY],
        ];
    }

    public function onContentChanged(Event $event): void
    {
        $tag = $this->tagOf($event);
        if ($tag === '') {
            return;
        }

        $this->indexer()->enqueue([$tag]);
    }

    /** Deleting is safe to do inline: it is one `DELETE ... */
    public function onContentDeleted(Event $event): void
    {
        $tag = $this->tagOf($event);
        if ($tag !== '') {
            $this->indexer()->delete($tag);
        }
    }

    /** Queue the form's entries. */
    public function onFormChanged(Event $event): void
    {
        $formId = (string)($event->getData()['id'] ?? '');
        if ($formId === '') {
            return;
        }

        $this->indexer()->enqueueForm($formId);
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
