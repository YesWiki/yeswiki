<?php

namespace YesWiki\Federation\Service;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use YesWiki\Kernel\Entity\Event;

/** Publishes an entry's changes to the form's ActivityPub followers. */
class FederatesEntryChanges implements EventSubscriberInterface
{
    /** Event name to ActivityStreams verb. */
    private const VERBS = [
        'entry.created' => 'Create',
        'entry.updated' => 'Update',
        'entry.deleted' => 'Delete',
    ];

    public function __construct(private readonly ActivityPubService $activityPub)
    {
    }

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return array_map(static fn () => 'onEntryChanged', self::VERBS);
    }

    public function onEntryChanged(Event $event, ?string $eventName = null): void
    {
        $verb = self::VERBS[(string)$eventName] ?? null;
        if ($verb === null) {
            return;
        }

        $data = $event->getData();
        $form = $data['form'] ?? [];

        if (!is_array($form) || ($data['imported'] ?? false) || !$this->activityPub->isEnabled($form)) {
            return;
        }

        $this->activityPub->notifyFollowers($form, $data['data'] ?? [], $verb);
    }
}
