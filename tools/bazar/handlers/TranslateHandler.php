<?php

namespace YesWiki\Bazar\Handlers;

use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Bazar\Service\FormManager;
use YesWiki\Core\Service\PageManager;
use YesWiki\Core\YesWikiHandler;

class TranslateHandler extends YesWikiHandler
{
    public function run()
    {
        if (!$this->wiki->HasAccess('write') || !$this->wiki->page) {
            return null;
        }

        $pageManager = $this->getService(PageManager::class);
        $entryManager = $this->getService(EntryManager::class);
        $formManager = $this->getService(FormManager::class);

        $tag = $this->wiki->GetPageTag();
        $this->arguments = [];

        if (isset($_POST['submit'])) {
            if (isset($_POST['entry']) && isset($_POST['extra_lang']) && isset($_POST['antispam'])) {
                $entry = json_decode($_POST['entry'], true);
                $entry['__extra_lang'] = json_decode($_POST['extra_lang'], true);
                $entry['antispam'] = $_POST['antispam'];
                $entryManager->update($entry['id_fiche'], $entry);
                return $this->wiki->redirect($this->wiki->Href(testUrlInIframe(), '', [
                    'vue' => 'consulter',
                    'action' => 'voir_fiche',
                    'id_fiche' => $entry['id_fiche'],
                    'message' => 'modif_ok',
                    'lang' => $_POST['lang'] ?? '',
                ], false));
            }
        }

        $page = $pageManager->getOne($tag);
        $page = json_decode($page['body'], true);
        $isEntry = $entryManager->isEntry($tag);
        $field_names = [];
        if ($isEntry) {
            $form = $formManager->getOne($page['id_typeannonce']);
            Foreach ($form['prepared'] as $field) {
                $field_names[$field->getName()] = $field->getLabel();
            }
        }

        $output = $this->wiki->Header();
        $output .= $this->render('@bazar/entries/translate.twig', [
            'isEntry' => $entryManager->isEntry($tag),
            'entry' => $page,
            'default_language' => $GLOBALS['default_language'] ?? 'fr',
            'ignore_fields' => ['id_typeannonce', 'id_fiche', 'date_creation_fiche', 'statut_fiche', 'date_maj_fiche'],
            'extra_lang' => $GLOBALS['wiki']->config['extra_lang'],
            'fields_names' => $field_names,
        ]);
        $output .= $this->wiki->Footer();
        return $output;
    }
}
