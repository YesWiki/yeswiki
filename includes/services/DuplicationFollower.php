<?php

namespace YesWiki\Core\Service;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use YesWiki\Core\Entity\Event;
use YesWiki\Wiki;

class DuplicationFollower implements EventSubscriberInterface
{
    protected $wiki;
    
    public static function getSubscribedEvents()
    {
        return [
            'entry.created' => 'followEntryCreation',
        ];
    }

    public function __construct(
        Wiki $wiki
    ) {
        $this->wiki = $wiki;
    }

    /**
     * @param Event $event
     */
    public function followEntryCreation($event)
    {
        $entry = $this->getEntry($event);
        if ($this->shouldFollowEntry($entry)){
            $this->registerFollowInSession($this->wiki->tag,$entry['id_fiche']);
        }
    }

    /**
     * @param Event $event
     * @return array $entry
     */
    protected function getEntry(Event $event): array
    {
        $data = $event->getData();
        $entry = $data['data'] ?? [];
        return is_array($entry) ? $entry : [];
    }

    /**
     * @param array $entry
     * @return bool
     */
    protected function shouldFollowEntry(array $entry): bool
    {
        return (
            !empty($entry['id_fiche'])
            && !empty($this->wiki->tag)
            && !empty($this->wiki->method))
            && in_array($this->wiki->method,['duplicate','duplicateiframe'],true
        );
    }

    /**
     * append association to follow in session
     * @param string $createdEntryId
     * @param string $copiedEntryId
     */
    public function registerFollowInSession(string $copiedEntryId, string $createdEntryId)
    {
        if (empty($_SESSION['duplicateIds'])){
            $_SESSION['duplicateIds'] = [];
        }
        if (!array_key_exists($copiedEntryId,$_SESSION['duplicateIds'])){
            $_SESSION['duplicateIds'][$copiedEntryId] = [];
        }
        if (!in_array($createdEntryId,$_SESSION['duplicateIds'][$copiedEntryId])){
            $_SESSION['duplicateIds'][$copiedEntryId][] = $createdEntryId;
        }
    }

    /**
     * check if the current entry is followed
     * @param string $entryId
     * @param array &$followedEntryIds
     * @return bool
     */
    public function isFollowed(string $entryId, array &$followedEntryIds): bool
    {
        $isFollowed = !empty($_SESSION['duplicateIds'][$entryId]);
        if ($isFollowed){
            $followedEntryIds = $_SESSION['duplicateIds'][$entryId];
            unset($_SESSION['duplicateIds'][$entryId]);
        }
        if (isset($_SESSION['duplicateIds']) && empty($_SESSION['duplicateIds'])){
            unset($_SESSION['duplicateIds']);
        }
        return $isFollowed;
    }
}
