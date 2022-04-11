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
            foreach ($entries as $entry) {
                try {
                    if ($this->extractOldCarto($entry)) {
                        $updatedEntries[] = $entry['id_fiche'];
                    }
                } catch (Throwable $th) {
                    $entriesWithErrors[$entry['id_fiche']] = $th->getMessage();
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
            $entry['date_maj_fiche'] = empty($entry['date_maj_fiche'])
                ? date('Y-m-d H:i:s', time())
                : (new DateTime($entry['date_maj_fiche']))->add(new DateInterval("PT1S"))->format('Y-m-d H:i:s');
            $this->updateEntry($entry);
        }
        return $updated;
    }

    private function updateEntry($data)
    {
        if ($this->securityController->isWikiHibernated()) {
            throw new \Exception(_t('WIKI_IN_HIBERNATION'));
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
            $data = array_map('utf8_encode', $data);
        }

        $oldPage = $this->pageManager->getOne($data['id_fiche']);
        $owner = $oldPage['owner'] ?? '';
        $user = $oldPage['user'] ?? '';

        // set all other revisions to old
        $this->dbService->query("UPDATE {$this->dbService->prefixTable('pages')} SET `latest` = 'N' WHERE `tag` = '{$this->dbService->escape($data['id_fiche'])}'");

        // add new revision
        $this->dbService->query("INSERT INTO {$this->dbService->prefixTable('pages')} SET ".
            "`tag` = '{$this->dbService->escape($data['id_fiche'])}', ".
            "`time` = '{$this->dbService->escape($data['date_maj_fiche'])}', ".
            "`owner` = '{$this->dbService->escape($owner)}', ".
            "`user` = '{$this->dbService->escape($user)}', ".
            "`latest` = 'Y', ".
            "`body` = '" . $this->dbService->escape(json_encode($data)) . "', ".
            "`body_r` = ''");
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
