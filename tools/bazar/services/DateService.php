<?php

namespace YesWiki\Bazar\Service;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Throwable;
use YesWiki\Core\Entity\Event;
use YesWiki\Wiki;

class DateService implements EventSubscriberInterface
{
    protected $wiki;
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
        Wiki $wiki,
        EntryManager $entryManager,
        FormManager $formManager
    ) {
        $this->wiki = $wiki;
        $this->entryManager = $entryManager;
        $this->followedIds = [];
        $this->formManager = $formManager;
    }

    /**
     * check if `$data` is the marker left on a legacy physically-duplicated recurrence child entry
     * (`bf_date_fin_evenement_data` used to be set to this literal string on every entry created by
     * the old repetition mechanism, instead of a normal recurrence-config array).
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
            // no new repetitions are ever created here: repeated dates are now expanded
            // virtually for display (calendar/ICS). Still clean up stale physical children
            // left by the old repetition mechanism whenever the parent entry is re-saved.
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
        return !empty($entry['id_fiche'])
            && in_array($entry['id_fiche'], $this->followedIds);
    }

    /**
     * remove linked entries.
     */
    protected function deleteLinkedEntries(array $entry)
    {
        $vSearchManager = $this->wiki->services->get(SearchManager::class);

        $entryId = $entry['id_fiche'];
        $formId = $entry['id_typeannonce'];
        $hasEndDateField = isset($entry['bf_date_fin_evenement']);

        if ($hasEndDateField && !empty($entryId) && !empty($formId)) {
            $entriesToDelete = $vSearchManager->search(
                [
                    'formsIds' => [$formId],
                    'queries' => [
                        'bf_date_fin_evenement_data' => ".*$entryId.*",
                    ],
                ],
                false, // filter on read Acl
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
                        $this->entryManager->delete($entryToDelete['id_fiche'], true); // $forceEvenIfNotOwner = true
                    } catch (Throwable $th) {
                        // do nothing
                    }
                }
            }
        }
    }

    /**
     * check if associated form is restricted for only one entry by user.
     */
    public function canRegisterMultipleEntries(?array $entry): bool
    {
        // default true
        $canRegisterMultipleEntries = true;
        if (!empty($entry['id_typeannonce']) && is_scalar($entry['id_typeannonce'])) {
            $form = $this->formManager->getOne(strval($entry['id_typeannonce']));
            if (!empty($form['bn_only_one_entry'])) {
                $canRegisterMultipleEntries = ($form['bn_only_one_entry'] !== 'Y');
            }
        }

        return $canRegisterMultipleEntries;
    }
}
