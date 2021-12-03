<?php

namespace YesWiki\Attach;

use YesWiki\Bazar\Field\TextareaField;
use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Bazar\Service\FormManager;
use YesWiki\Core\YesWikiHandler;
use YesWiki\Core\Service\DbService;
use YesWiki\Security\Controller\SecurityController;

class UpdateHandler__ extends YesWikiHandler
{
    public function run()
    {
        if ($this->getService(SecurityController::class)->isWikiHibernated()) {
            throw new \Exception(_t('WIKI_IN_HIBERNATION'));
        };
        if (!$this->wiki->UserIsAdmin()) {
            return null;
        }

        $output = $this->cleanBase64();

        // set output
        $this->output = str_replace(
            '<!-- end handler /update -->',
            $output.'<!-- end handler /update -->',
            $this->output
        );
        return null;
    }

    private function cleanBase64():string
    {
        $pages = $this->searchPagesWithBase64();
        $updatedPages = [];
        if (!empty($pages)) {
            foreach ($pages as $page) {
                if ($this->extractImages($page)) {
                    $updatedPages[] = $page['tag'];
                }
            }
        }
        return $this->render('@attach/extract-base64-at-update.twig', [
            'updatedPages' => $updatedPages,
            'tablePrefix' => $this->params->get('table_prefix'),
        ]);
    }

    private function searchPagesWithBase64(): array
    {
        // get services
        $dbService = $this->wiki->services->get(DbService::class);

        $anchor = '%src=\\\\\\\\\\"data:image\\\\\\\\/%;base64,%';
        $select_entries_filter =
        'SELECT DISTINCT resource FROM '.$dbService->prefixTable('triples').
        'WHERE value = "fiche_bazar" AND property = "http://outils-reseaux.org/_vocabulary/type" ' .
        'ORDER BY resource ASC';

        $sql = "SELECT * FROM ".$dbService->prefixTable('pages')." ".
            "WHERE body LIKE '{$anchor}' ".
            "AND tag IN (" . $select_entries_filter . ")";

        $pages = $dbService->loadAll($sql);

        return empty($pages) ? [] : $pages;
    }

    private function extractImages(array $page): bool
    {
        $entryManager = $this->wiki->services->get(EntryManager::class);
        $entry = $entryManager->getOne($page['tag'], false, $page['time']);
        if (empty($entry)) {
            return false;
        }
        $formId = $entry['id_typeannonce'] ?? null;
        if (empty($formId)) {
            return false;
        }

        $formManager = $this->wiki->services->get(FormManager::class);
        $form = $formManager->getOne($formId);
        if (empty($form)) {
            return false;
        }
        $updated = false;
        foreach ($form['prepared'] as $field) {
            if ($field instanceof TextareaField && !empty($entry[$field->getPropertyName()])) {
                $newValue = $field->formatValuesBeforeSave($entry);
                if (isset($newValue[$field->getPropertyName()])) {
                    $oldValue = json_encode($entry[$field->getPropertyName()]);
                    $newValue = json_encode($newValue[$field->getPropertyName()]);
                    $page['body'] = str_replace($oldValue, $newValue, $page['body']);
                    $updated = true;
                }
            }
        }
        if ($updated) {
            // get services
            $dbService = $this->wiki->services->get(DbService::class);
            
            $dbService->query(
                "UPDATE {$dbService->prefixTable('pages')} ".
                "SET body = '{$dbService->escape(chop($page['body']))}' ".
                "WHERE tag = '{$dbService->escape($page['tag'])}' ".
                "AND time = '{$dbService->escape($page['time'])}'"
            );
        }
        return $updated;
    }
}
