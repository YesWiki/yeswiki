<?php

use YesWiki\Core\Service\EntryManager;
use YesWiki\Core\Service\FormManager;
use YesWiki\Core\Service\HibernationService;
use YesWiki\Core\Service\PageManager;
use YesWiki\Search\Service\SearchManager;
use YesWiki\Core\YesWikiMigration;

class CleanOldCartoGoogle extends YesWikiMigration
{
    private $entryManager;
    private $formManager;
    private $pageManager;
    private $hibernationService;

    public function run()
    {
        $this->entryManager = $this->wiki->services->get(EntryManager::class);
        $this->formManager = $this->wiki->services->get(FormManager::class);
        $this->pageManager = $this->wiki->services->get(PageManager::class);
        $this->hibernationService = $this->wiki->services->get(HibernationService::class);

        $entries = $this->searchEntriesWithOnlyOldGeoloc();
        if (!empty($entries)) {
            foreach ($entries as $entry) {
                $this->extractOldCarto($entry);
            }
        }
    }

    private function searchEntriesWithOnlyOldGeoloc(): array
    {
        $entries = $this->wiki->services->get(SearchManager::class)->search([
            'queries' => [
                'carte_google!' => '',
            ],
        ]);

        return empty($entries) ? [] : $entries;
    }

    private function extractOldCarto(array $entry): bool
    {
        if (
            empty($entry) || empty($entry['id_fiche']) || empty($entry['id_typeannonce'])
            || strval($entry['id_typeannonce']) != strval(intval($entry['id_typeannonce']))
        ) {
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
                $tab = $field->formatValuesBeforeSaveIfEditable($entry);
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
            $entry['date_maj_fiche'] = empty($entry['date_maj_fiche'])
                ? date('Y-m-d H:i:s', time())
                : (new DateTime($entry['date_maj_fiche']))->add(new DateInterval('PT1S'))->format('Y-m-d H:i:s');
            $this->updateEntry($entry);
        }

        return $updated;
    }

    private function updateEntry($data)
    {
        if ($this->hibernationService->isWikiHibernated()) {
            throw new Exception(_t('WIKI_IN_HIBERNATION'));
        }

        $this->entryManager->validate(array_merge($data, ['antispam' => 1]));

        // on enleve les champs hidden pas necessaires a la fiche
        unset($data['valider']);
        unset($data['MAX_FILE_SIZE']);
        unset($data['antispam']);
        unset($data['mot_de_passe_wikini']);
        unset($data['mot_de_passe_repete_wikini']);
        unset($data['html_data']);
        unset($data['url']);

        // on nettoie le champ owner qui n'est pas sauvegardé (champ owner de la page)
        if (isset($data['owner'])) {
            unset($data['owner']);
        }

        if (isset($data['sendmail'])) {
            unset($data['sendmail']);
        }

        // on encode en utf-8 pour reussir a encoder en json
        if (YW_CHARSET != 'UTF-8') {
            $data = array_map(function ($value) {
                return mb_convert_encoding($value, 'UTF-8', 'ISO-8859-1');
            }, $data);
        }

        $oldPage = $this->pageManager->getOne($data['id_fiche']);
        $owner = $oldPage['owner'] ?? '';
        $user = $oldPage['user'] ?? '';

        // set all other revisions to old
        $this->dbService->query("UPDATE {$this->dbService->prefixTable('pages')} SET latest = 'N' WHERE tag = '{$this->dbService->escape($data['id_fiche'])}'");

        // add new revision
        $userCol = $this->dbService->quoteIdentifier('user');
        $this->dbService->query("INSERT INTO {$this->dbService->prefixTable('pages')} " .
            "(tag, time, owner, $userCol, latest, body, body_r) VALUES (" .
            "'{$this->dbService->escape($data['id_fiche'])}', " .
            "'{$this->dbService->escape($data['date_maj_fiche'])}', " .
            "'{$this->dbService->escape($owner)}', " .
            "'{$this->dbService->escape($user)}', " .
            "'Y', " .
            "'" . $this->dbService->escape(json_encode($data)) . "', " .
            "'')");
    }

    private function getMapFieldValue($field, $entry)
    {
        $value = $entry[$field->getPropertyName()] ?? $field->getDefault();

        $vLatitudeField = is_callable([$field, 'getLatitudeField']) ? $field->getLatitudeField() : 'bf_latitude';
        $vLongitudeField = is_callable([$field, 'getLongitudeField']) ? $field->getLongitudeField() : 'bf_longitude';

        // backward compatibility with former `carte_google` propertyName
        $returnValue = [];
        if (empty($value)) {
            if (!empty($entry['carte_google'])) {
                $value = explode('|', $entry['carte_google']);
                if (!empty($value[0]) && !empty($value[1])) {
                    $returnValue = [
                        $vLatitudeField => $value[0],
                        $vLongitudeField => $value[1],
                    ];
                }
            } elseif (!empty($entry[$field->getLatitudeField()]) && !empty($entry[$field->getLongitudeField()])) {
                $returnValue = [
                    $vLatitudeField => $entry[$vLatitudeField],
                    $vLongitudeField => $entry[$vLongitudeField],
                ];
            }
        }

        return $returnValue;
    }
}
