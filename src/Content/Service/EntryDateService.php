<?php

namespace YesWiki\Content\Service;

use Psr\Container\ContainerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use YesWiki\Kernel\Entity\Event;
use YesWiki\Search\Service\SearchManager;

class EntryDateService implements EventSubscriberInterface
{
    protected ContainerInterface $container;
    protected $entryManager;
    protected $formManager;
    protected $followedIds;

    public static function getSubscribedEvents()
    {
        return [
            'entry.created' => 'followEntryChange',
            'entry.updated' => 'followEntryChange',
            'entry.deleted' => 'followEntryDeletion',
        ];
    }

    public function __construct(
        ContainerInterface $container,
        EntryManager $entryManager,
        FormManager $formManager
    ) {
        $this->container = $container;
        $this->entryManager = $entryManager;
        $this->followedIds = [];
        $this->formManager = $formManager;
    }

    /**
     * check if `$data` is the marker left on a legacy physically-duplicated recurrence child entry (`bf_date_fin_evenement_data` used to be set to this literal string on every entry created by the old repetition mechanism, instead of a normal recurrence-config array).
     */
    public static function isLegacyRecurrenceChild(mixed $data): bool
    {
        return is_string($data) && preg_match('/^\{"recurrentParentId":"[^"]+"}$/', $data) === 1;
    }

    /**
     * @param Event $event
     */
    public function followEntryChange($event)
    {
        $entry = $this->getEntry($event);
        if ($this->shouldFollowEntry($entry)) {
            $this->deleteLinkedEntries($entry);
        }
    }

    /**
     * @param Event $event
     */
    public function followEntryDeletion($event)
    {
        $entryBeforeDeletion = $this->getEntry($event);
        if (!empty($entryBeforeDeletion)) {
            $this->deleteLinkedEntries($entryBeforeDeletion);
        }
    }

    public function followId(string $entryId)
    {
        if (!in_array($entryId, $this->followedIds)) {
            $this->followedIds[] = $entryId;
        }
    }

    /**
     * @return array $entry
     */
    protected function getEntry(Event $event): array
    {
        $data = $event->getData();
        $entry = $data['data'] ?? [];

        return is_array($entry) ? $entry : [];
    }

    protected function shouldFollowEntry(array $entry): bool
    {
        return !empty($entry['tag'])
            && in_array($entry['tag'], $this->followedIds);
    }

    /** remove linked entries. */
    protected function deleteLinkedEntries(array $entry)
    {
        $vSearchManager = $this->container->get(SearchManager::class);

        $entryId = $entry['tag'];
        $formId = $entry['form_id'];
        $hasEndDateField = isset($entry['bf_date_fin_evenement']);

        if ($hasEndDateField && !empty($entryId) && !empty($formId)) {
            $entriesToDelete = $vSearchManager->search(
                [
                    'formsIds' => [$formId],
                    'queries' => [
                        'bf_date_fin_evenement_data' => ".*$entryId.*",
                    ],
                ],
                false,
                false
            );
            if (is_iterable($entriesToDelete)) {
                $entriesToDelete = array_filter(
                    $entriesToDelete,
                    function ($entryToFilter) use ($entryId) {
                        return !empty($entryToFilter['bf_date_fin_evenement_data'])
                            && $entryToFilter['bf_date_fin_evenement_data'] === "{\"recurrentParentId\":\"$entryId\"}";
                    }
                );
                foreach ($entriesToDelete as $entryToDelete) {
                    try {
                        $this->entryManager->delete($entryToDelete['tag'], true);
                    } catch (\Throwable $th) {
                    }
                }
            }
        }
    }

    /** check if associated form is restricted for only one entry by user. */
    public function canRegisterMultipleEntries(?array $entry): bool
    {
        $canRegisterMultipleEntries = true;
        if (!empty($entry['form_id']) && is_scalar($entry['form_id'])) {
            $form = $this->formManager->getOne(strval($entry['form_id']));
            if (!empty($form['only_one_entry'])) {
                $canRegisterMultipleEntries = ($form['only_one_entry'] !== 'Y');
            }
        }

        return $canRegisterMultipleEntries;
    }
}
