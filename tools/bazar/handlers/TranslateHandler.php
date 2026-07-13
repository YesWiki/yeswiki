<?php

namespace YesWiki\Bazar;

use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Bazar\Service\FormManager;
use YesWiki\Core\Service\PageManager;
use YesWiki\Core\YesWikiHandler;

class TranslateHandler extends YesWikiHandler
{

    private const IGNORE_FIELDS_TYPES = [
        'checkboxfiche',
        'listefiches',
        'liste',
        'radio',
        'checkbox',
        'listedatedeb',
        'listefiche',
        'map',
        'tabs',
        'labelhtml',
    ];
    private const IGNORE_SPECIFIC_TYPES = [
        'id_typeannonce',
        'id_fiche',
        'date_creation_fiche',
        'statut_fiche',
        'date_maj_fiche',
        'bf_longitude',
        'bf_latitude',
        'geolocation',
        'lang'
    ];

    private function save($entryManager) : string{
        $post = $this->getRequest()->request;
        if ($post->get('entry') != null && $post->get('extra_lang') != null && $post->get('antispam') != null) {
            $entry = json_decode($post->get('entry'), true);
            $entry['extra_lang'] = json_decode($post->get('extra_lang'), true);
            $entry['antispam'] = $post->get('antispam');
            $entryManager->update($entry['id_fiche'], $entry);

            return $this->wiki->redirect($this->wiki->Href(testUrlInIframe(), '', [
            'vue' => 'consulter',
            'action' => 'voir_fiche',
            'id_fiche' => $entry['id_fiche'],
            'message' => 'modif_ok',
            'lang' => $post->get('lang') ?? 'default',
            ], false));
        }
    }

    private function display_form($tag): string {
        $pageManager = $this->getService(PageManager::class);
        $formManager = $this->getService(FormManager::class);

        $page = $pageManager->getOne($tag);
        $page = json_decode($page['body'], true);
        $page_lang = $page['lang'] ?? $this->wiki->config['default_language'];
        $langs = $this->wiki->config['supported_langs'];
        $extra_langs = array_filter($langs, function($el) use ($page_lang) {
            return $el != $page_lang;
        });


        $field_names = [];
        $ignorefields = [];
            $form = $formManager->getOne($page['id_typeannonce']);
            dump($form);
            foreach ($form['fields'] as $field) {
                if (!in_array($field['type'], self::IGNORE_FIELDS_TYPES)) {
                    $type = 'text';
                    if ($field['type'] == 'textarea') {
                        $type = 'textarea';
                    } else if ($field['type'] == 'text' && isset($field['subtype'])) {
                        $type = $field['subtype'];
                    }
                    $field_names[] = [
                    'name' => $field['name'],
                    'label' => $field['label'],
                    'type' => $type,
                    ];

                    foreach($extra_langs as $lang) {
                        $page['extra_lang'][$lang]['name'] ??= '';
                    }
                }
            }
        $output = $this->render('@bazar/entries/translate.twig', [
        'isEntry' => true,
        'entry' => $page,
        'langs' => $langs,
        'baseLang' => $page_lang,
        'fieldsNames' => $field_names,
        ]);
        return $output;
    }

    public function run() :String
    {
        $output = $this->wiki->Header();

        if (!$this->wiki->HasAccess('write') || !$this->wiki->page) {
            $output .= $this->render('@templates/alert-message.twig', [
                            'type' => 'danger',
                            'message' => _t('NOT_ALLOWED'),
                        ]);
        } else {
            if (isset($this->wiki->config['supported_langs'])) {

                $tag = $this->wiki->GetPageTag();
                $this->arguments = [];
                $entryManager = $this->getService(EntryManager::class);
                $isEntry = $entryManager->isEntry($tag);

                if ($isEntry) {
                    if ($this->getRequest()->getMethod() === 'POST') {
                        $output .= $this->save($entryManager);
                    } else {
                        $output .= $this->display_form($tag);
                    }
                } else {
                    $output .= $this->render('@templates/alert-message.twig', [
                                    'type' => 'danger',
                                    'message' => _t('TRANSLATE_ACTION_ONLY_ALLOWED_FOR_ENTRY'),
                                ]);
                }
            } else {
                $output .= $this->render('@templates/alert-message.twig', [
                                'type' => 'danger',
                                'message' => _t('TRANSLATE_MISSING_EXTRA_LANG'),
                            ]);
            }
        }
        $output .= $this->wiki->Footer();
        return $output;
    }
}
