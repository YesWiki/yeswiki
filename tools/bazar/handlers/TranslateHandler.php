<?php

use YesWiki\Core\Service\PageManager;
use YesWiki\Core\YesWikiHandler;

class TranslateHandler extends YesWikiHandler
{
    public function run()
    {
        dump('translate handler');
        error_log('translate handler');
        if (!$this->wiki->HasAccess('write') || !$this->wiki->page) {
            return null;
        }

        $pageManager = $this->getService(PageManager::class);

        $tag = $this->wiki->GetPageTag();
        dump('allowed');
        $this->arguments = [];

        dump('is entry');
        $page = $pageManager->getOne($tag);
        dump(json_decode($page['body'], true));
        // return 'le handler translate a été appelé';
        $output = $this->wiki->Header();
        $output .= $this->render('@bazar/entries/translate.twig', [
            'entry' => json_decode($page['body'], true),
            'default_language' => $GLOBALS['default_language'] ?? 'fr',
            'ignore_fields' => ['id_typeannonce', 'id_fiche', 'date_creation_fiche', 'statut_fiche', 'date_maj_fiche'],
            'extra_lang' => $GLOBALS['wiki']->config['extra_lang'],
        ]);
        $output .= $this->wiki->Footer();
        return $output;
    }
}
