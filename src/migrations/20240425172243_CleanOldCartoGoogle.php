<?php

use YesWiki\Content\Field\MapField;
use YesWiki\Content\Service\EntryManager;
use YesWiki\Content\Service\FormManager;
use YesWiki\Content\Service\PageManager;
use YesWiki\Core\YesWikiMigration;
use YesWiki\Kernel\Service\HibernationService;
use YesWiki\Search\Service\SearchManager;

class CleanOldCartoGoogle extends YesWikiMigration
{
    private EntryManager $entryManager;
    private FormManager $formManager;
    private PageManager $pageManager;
    private HibernationService $hibernationService;

    public function run()
    {
        $this->entryManager = $this->getService(EntryManager::class);
        $this->formManager = $this->getService(FormManager::class);
        $this->pageManager = $this->getService(PageManager::class);
        $this->hibernationService = $this->getService(HibernationService::class);

        $entries = $this->searchEntriesWithOnlyOldGeoloc();
        if (!empty($entries)) {
            foreach ($entries as $entry) {
                $this->extractOldCarto($entry);
            }
        }
    }

    /**
     * @return array<string, array<string, mixed>> the matching entries, keyed by tag
     */
    private function searchEntriesWithOnlyOldGeoloc(): array
    {
        $entries = $this->getService(SearchManager::class)->search([
            'queries' => [
                'carte_google!' => '',
            ],
        ]);

        return empty($entries) ? [] : $entries;
    }

    /**
     * @param array<string, mixed> $entry
     */
    private function extractOldCarto(array $entry): bool
    {
        $tag = $entry['tag'] ?? $entry['id_fiche'] ?? '';
        $formId = $entry['form_id'] ?? $entry['id_typeannonce'] ?? '';
        if (
            empty($tag) || empty($formId)
            || strval($formId) != strval(intval($formId))
        ) {
            return false;
        }

        $form = $this->formManager->getOne($formId);
        if (empty($form['prepared'])) {
            return false;
        }
        $updated = false;
        foreach ($form['prepared'] as $field) {
            if ($field instanceof MapField) {
                $entry = array_merge($entry, $this->getMapFieldValue($field, $entry));
                $tab = $field->formatValuesBeforeSaveIfEditable($entry);
                if (isset($tab['fields-to-remove']) and is_array($tab['fields-to-remove'])) {
                    foreach ($tab['fields-to-remove'] as $fieldName) {
                        if (isset($entry[$fieldName])) {
                            unset($entry[$fieldName]);
                        }
                    }
                    unset($tab['fields-to-remove']);
                }
                $entry = array_merge($entry, $tab);
                $updated = true;
            }
        }
        if ($updated) {
            $updatedAtKey = array_key_exists('updated_at', $entry) ? 'updated_at' : 'date_maj_fiche';
            $entry[$updatedAtKey] = empty($entry[$updatedAtKey])
                ? date('Y-m-d H:i:s', time())
                : (new DateTime((string)$entry[$updatedAtKey]))->add(new DateInterval('PT1S'))->format('Y-m-d H:i:s');
            $this->updateEntry($entry);
        }

        return $updated;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function updateEntry(array $data): void
    {
        if ($this->hibernationService->isWikiHibernated()) {
            throw new Exception(_t('WIKI_IN_HIBERNATION'));
        }

        $this->entryManager->validate(array_merge($data, ['antispam' => 1]));

        unset($data['valider']);
        unset($data['MAX_FILE_SIZE']);
        unset($data['antispam']);
        unset($data['mot_de_passe_wikini']);
        unset($data['mot_de_passe_repete_wikini']);
        unset($data['html_data']);
        unset($data['url']);

        if (isset($data['owner'])) {
            unset($data['owner']);
        }

        if (isset($data['sendmail'])) {
            unset($data['sendmail']);
        }

        if (YW_CHARSET != 'UTF-8') {
            $data = array_map(function ($value) {
                return is_string($value) ? mb_convert_encoding($value, 'UTF-8', 'ISO-8859-1') : $value;
            }, $data);
        }

        $tag = (string)($data['tag'] ?? $data['id_fiche'] ?? '');
        $updatedAt = (string)($data['updated_at'] ?? $data['date_maj_fiche'] ?? '');

        $oldPage = $this->pageManager->getOne($tag);
        $owner = $oldPage['owner'] ?? '';
        $user = $oldPage['user'] ?? '';

        $this->dbService->query("UPDATE {$this->dbService->prefixTable('pages')} SET latest = 'N' WHERE tag = '{$this->dbService->escape($tag)}'");

        $userCol = $this->dbService->quoteIdentifier('user');
        $this->dbService->query("INSERT INTO {$this->dbService->prefixTable('pages')} " .
            "(tag, time, owner, $userCol, latest, body) VALUES (" .
            "'{$this->dbService->escape($tag)}', " .
            "'{$this->dbService->escape($updatedAt)}', " .
            "'{$this->dbService->escape($owner)}', " .
            "'{$this->dbService->escape($user)}', " .
            "'Y', " .
            "'" . $this->dbService->escape(json_encode($data)) . "')");
    }

    /**
     * @param array<string, mixed> $entry
     *
     * @return array<string, mixed> the flat latitude/longitude keys recovered from the old
     *                              `carte_google` value, empty when the field already has one
     */
    private function getMapFieldValue(MapField $field, array $entry): array
    {
        $value = $entry[$field->getPropertyName()] ?? $field->getDefault();

        $vLatitudeField = 'bf_latitude';
        $vLongitudeField = 'bf_longitude';

        $returnValue = [];
        if (empty($value)) {
            if (!empty($entry['carte_google'])) {
                $coordinates = explode('|', (string)$entry['carte_google']);
                if (!empty($coordinates[0]) && !empty($coordinates[1])) {
                    $returnValue = [
                        $vLatitudeField => $coordinates[0],
                        $vLongitudeField => $coordinates[1],
                    ];
                }
            } elseif (!empty($entry[$vLatitudeField]) && !empty($entry[$vLongitudeField])) {
                $returnValue = [
                    $vLatitudeField => $entry[$vLatitudeField],
                    $vLongitudeField => $entry[$vLongitudeField],
                ];
            }
        }

        return $returnValue;
    }
}
