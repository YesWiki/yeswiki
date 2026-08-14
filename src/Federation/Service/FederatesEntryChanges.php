<?php

namespace YesWiki\Federation\Service;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use YesWiki\Kernel\Entity\Event;

/**
 * Publishes an entry's changes to the form's ActivityPub followers.
 *
 * This is `Content`'s former dependency on federation, turned around: three places in
 * `EntryManager` used to read `activityPubService->isEnabled($form)` and then call
 * `notifyFollowers()`. Both the question and the answer belong here (ADR-0019).
 *
 * Subscribing works only because ticket 39 moved the dispatch of `entry.*` from
 * `EntryController` into `EntryManager`. While those events came from the controller they were
 * the *form-submission* events, and federating on them would have quietly stopped announcing
 * anything written by the API, an importer or a migration.
 *
 * ## Two guards, neither incidental
 *
 * - **A form that has not enabled ActivityPub is not federated.** `isEnabled()` is a one-line
 *   check on the form's own metadata, and `Content` had no business asking it.
 * - **An imported entry is not re-published.** A wiki that syndicated a remote actor's posts
 *   would otherwise announce them back out as its own, and two wikis following each other
 *   would trade the same entry forever. `EntryManager` puts `imported` on the event precisely
 *   so this can be decided here.
 *
 * ## A failed announcement no longer fails the save
 *
 * The direct call propagated: an unreachable follower's server threw out of
 * `EntryManager::create()` and the entry was not written. `yesWikiDispatch()` catches instead,
 * so a save now succeeds and the announcement is lost. That is the better trade -- a remote
 * server's availability should not decide whether local content can be saved -- but it is a
 * change, and it is this class's to own.
 */
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

    /** @return array<string, string> */
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
        // an event dispatched from somewhere that predates the `form`/`imported` keys carries
        // neither, and the safe reading of "no form" is "not a form that federates"
        if (!is_array($form) || ($data['imported'] ?? false) || !$this->activityPub->isEnabled($form)) {
            return;
        }

        $this->activityPub->notifyFollowers($form, $data['data'] ?? [], $verb);
    }
}
