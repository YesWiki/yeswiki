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
    ];

    public function run() :String
    {
        if (!$this->wiki->HasAccess('write') || !$this->wiki->page) {
            return null;
        }
        if (isset($this->wiki->config['supported_langs'])) {
            $langs = $this->wiki->config['supported_langs'];

            $pageManager = $this->getService(PageManager::class);
            $entryManager = $this->getService(EntryManager::class);
            $formManager = $this->getService(FormManager::class);

            $tag = $this->wiki->GetPageTag();
            $this->arguments = [];


            if ($this->getRequest()->getMethod() === 'POST') {
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

            $page = $pageManager->getOne($tag);
            $page = json_decode($page['body'], true);
            $isEntry = $entryManager->isEntry($tag);
            $field_names = [];
            $ignorefields = [];
            $page_lang = $page['lang'] ?? $this->wiki->config['default_language'];
            $extra_langs = array_filter($langs, function($el) use ($page_lang) {
                return $el != $page_lang;
            });
            if ($isEntry) {
                $form = $formManager->getOne($page['id_typeannonce']);
                foreach ($form['prepared'] as $field) {
                    if (!in_array($field->getType(), self::IGNORE_FIELDS_TYPES)) {
                        $field_names[] = [
                        'name' => ($field->getType() == 'image' ? 'image' : '') . $field->getName(),
                        'label' => $field->getLabel(),
                        ];

                        foreach($extra_langs as $lang) {
                            $page['extra_lang'][$lang][$field->getName()] ??= '';
                        }
                    }
                }


        dump($page);
        $output = $this->wiki->Header();
        $output .= $this->render('@bazar/entries/translate.twig', [
            'isEntry' => $isEntry,
            'entry' => $page,
            'langs' => $langs,
            'baseLang' => $page_lang,
            'fieldsNames' => $field_names,
        ]);
        $output .= $this->wiki->Footer();
        return $output;
    }
        } else {
            $output = $this->wiki->Header();
            $output .= $this->render('@templates/alert-message.twig', [
                            'type' => 'danger',
                            'message' => _t('TRANSLATE_MISSING_EXTRA_LANG'),
                        ]);
            $output .= $this->wiki->Footer();
            return $output;
        }
    }
}
