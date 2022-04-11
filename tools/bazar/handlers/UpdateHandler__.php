<?php

namespace YesWiki\Bazar;

use DateInterval;
use DateTime;
use Throwable;
use YesWiki\Bazar\Field\MapField;
use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Bazar\Service\FormManager;
use YesWiki\Core\YesWikiHandler;
use YesWiki\Core\Service\DbService;
use YesWiki\Core\Service\PageManager;
use YesWiki\Security\Controller\SecurityController;

class UpdateHandler__ extends YesWikiHandler
{
    protected $dbService;
    protected $entryManager;
    protected $formManager;
    protected $pageManager;
    protected $securityController;

    public function run()
    {
        $this->securityController = $this->getService(SecurityController::class);
        if ($this->securityController->isWikiHibernated()) {
            throw new \Exception(_t('WIKI_IN_HIBERNATION'));
        };
        if (!$this->wiki->UserIsAdmin()) {
            return null;
        }

        $this->dbService = $this->getService(DbService::class);
        $this->entryManager = $this->getService(EntryManager::class);
        $this->formManager = $this->getService(FormManager::class);
        $this->pageManager = $this->getService(PageManager::class);

        $output = $this->cleanOldCartoGoogle();

        // set output
        $this->output = str_replace(
            '<!-- end handler /update -->',
            $output.'<!-- end handler /update -->',
            $this->output
        );
        return null;
    }

    private function cleanOldCartoGoogle():string
    {
        $entries = $this->searchEntriesWithOnlyOldGeoloc();
        $updatedEntries = [];
        $entriesWithErrors = [];
        if (!empty($entries)) {
            $startTime = new DateTime();
            $startTimePlus20s = $startTime->add(new DateInterval("PT20S"));
            foreach ($entries as $entry) {
                if ($startTimePlus20s->diff(new DateTime())->invert > 0){
                    // current DateTime below startTimePlus30s
                    try {
                        if ($this->extractOldCarto($entry)) {
                            $updatedEntries[] = $entry['id_fiche'];
                        }
                    } catch (Throwable $th) {
                        $entriesWithErrors[$entry['id_fiche']] = $th->getMessage();
                    }
                } else {
                    $entriesWithErrors[$entry['id_fiche']] = "Not enough time, reload /update to finish !";
                }
            }
        }
        return $this->render('@bazar/handlers/extract-old-geoloc-at-update.twig', [
            'updatedEntries' => $updatedEntries,
            'entriesWithErrors' => $entriesWithErrors,
            'tablePrefix' => $this->params->get('table_prefix'),
        ]);
    }

    private function searchEntriesWithOnlyOldGeoloc(): array
    {
        $entries = $this->entryManager->search([
            'queries' => [
                'carte_google!' => ""
            ]
        ]);

        return empty($entries) ? [] : $entries;
    }

    private function extractOldCarto(array $entry): bool
    {
        if (empty($entry) || empty($entry['id_fiche']) || empty($entry['id_typeannonce']) ||
            strval($entry['id_typeannonce']) != strval(intval($entry['id_typeannonce']))) {
            return false;
        }

        $form = $this->formManager->getOne($entry['id_typeannonce']);
        if (empty($form['prepared'])) {
            return false;
        }
        $updated = false;
        foreach ($form['prepared'] as $field) {
            if ($field instanceof MapField) {
                // update location
                $entry = array_merge($entry, $this->getMapFieldValue($field, $entry));
                $tab = $field->formatValuesBeforeSave($entry);
                if (is_array($tab)) {
                    if (isset($tab['fields-to-remove']) and is_array($tab['fields-to-remove'])) {
                        foreach ($tab['fields-to-remove'] as $fieldName) {
                            if (isset($entry[$fieldName])) {
                                unset($entry[$fieldName]);
                            }
                        }
                        unset($tab['fields-to-remove']);
                    }
                    $entry = array_merge($entry, $tab);
                }
                $updated = true;
            }
        }
        if ($updated) {
            $this->updateEntry($entry);
        }
        return $updated;
    }

    private function updateEntry($data)
    {   
        $this->entryManager->update($data['id_fiche'],array_merge($data, ['antispam' => 1]),false,false,false,new DateInterval("PT1S"));
    }

    private function getMapFieldValue($field, $entry)
    {
        $value = $entry[$field->getPropertyName()] ?? $field->getDefault();

        // backward compatibility with former `carte_google` propertyName
        $returnValue = [];
        if (empty($value)) {
            if (!empty($entry['carte_google'])) {
                $value = explode('|', $entry['carte_google']);
                if (!empty($value[0]) && !empty($value[1])) {
                    $returnValue = [
                        $field->getLatitudeField() => $value[0],
                        $field->getLongitudeField()=> $value[1]
                    ];
                }
            } elseif (!empty($entry[$field->getLatitudeField()]) && !empty($entry[$field->getLongitudeField()])) {
                $returnValue = [
                    $field->getLatitudeField() => $entry[$field->getLatitudeField()],
                    $field->getLongitudeField()=> $entry[$field->getLongitudeField()]
                ];
            }
        }
        return $returnValue;
    }
}
