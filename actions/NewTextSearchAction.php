<?php

use YesWiki\Bazar\Service\FormManager;
use YesWiki\Core\Service\DbService;
use YesWiki\Core\Service\TemplateEngine;
use YesWiki\Core\YesWikiAction;

class NewTextSearchAction extends YesWikiAction
{
    public const DEFAULT_TEMPLATE = "newtextsearch.twig";
    public const BY_FORM_TEMPLATE = "newtextsearch-by-form.twig";
    public const DEFAULT_LIMIT = 10;

    protected $dbService;
    protected $formManager;
    protected $templateEngine;

    public function formatArguments($arg)
    {
        $this->templateEngine = $this->getservice(TemplateEngine::class);
        $template = (!empty($arg['template']) &&
            !empty(basename($arg['template'])) &&
            $this->templateEngine->hasTemplate("@core/".basename($arg['template'])))
            ? basename($arg['template'])
            : self::DEFAULT_TEMPLATE;
        return [
            // label à afficher devant la zone de saisie
            'label' => isset($arg['label']) && is_string($arg['label']) ? $arg['label'] : _t('WHAT_YOU_SEARCH')." : ",
            // largeur de la zone de saisie
            'size' => isset($arg['size']) && is_scalar($arg['size']) ? intval($arg['size']) : 40,
            // texte du bouton
            'button' => !empty($arg['button']) && is_string($arg['button']) ? $arg['button'] : _t('SEARCH'),
            // texte à chercher
            'phrase' => isset($arg['phrase']) && is_string($arg['phrase']) ? $arg['phrase'] : '',
            // séparateur entre les éléments trouvés
            'separator' => isset($arg['separator']) && is_string($arg['separator']) ? htmlspecialchars($arg['separator'], ENT_COMPAT, YW_CHARSET) : '',
            'template' =>$template,
            'displaytext' => $this->formatBoolean($arg, $template == self::DEFAULT_TEMPLATE, 'displaytext'),
            'displayorder' => array_map(function ($item) {
                switch ($item) {
                    case 'pages':
                    case 'page':
                        return 'page';
                    case 'logspages':
                    case 'logpages':
                    case 'logspage':
                    case 'logpage':
                        return 'logpage';
                    default:
                        return strval(intval($item)) == strval($item) ? intval($item) : strval($item);
                }
            }, $this->formatArray($arg['displayorder'] ?? [])),
            'limit' => isset($arg['limit']) && intval($arg['limit']) > 0 ? intval($arg['limit']) : self::DEFAULT_LIMIT,
            'titles' => array_map('strval', $this->formatArray($arg['titles'] ?? [])),
            'viewtype' => (empty($arg['viewtype']) || !is_string($arg['viewtype']) || !in_array($arg['viewtype'], ['link','modal','newtab'])) ? 'modal' : $arg['viewtype'],
            'onlytags' => array_filter(array_map('trim', array_map('strval', $this->formatArray($arg['onlytags'] ?? [])))),
            'nbcols' => (
                isset($arg['nbcols']) &&
                is_scalar($arg['nbcols']) &&
                intval($arg['nbcols']) >= 0 &&
                intval($arg['nbcols']) <= 3
            ) ? intval($arg['nbcols']) : 2,
        ];
    }

    public function run()
    {
        // get services
        $this->dbService = $this->getservice(DbService::class);
        $this->formManager = $this->getservice(FormManager::class);

        // récupération de la recherche à partir du paramètre 'phrase'
        $searchText = !empty($this->arguments['phrase']) ? htmlspecialchars($this->arguments['phrase'], ENT_COMPAT, YW_CHARSET) : '';

        // affichage du formulaire si $this->arguments['phrase'] est vide
        $displayForm = empty($searchText);

        if (empty($searchText) && !empty($_GET['phrase'])) {
            $searchText = htmlspecialchars($_GET['phrase'], ENT_COMPAT, YW_CHARSET);
        }

        // define titles
        $formsTitles = [];
        if ($this->arguments['template'] == self::BY_FORM_TEMPLATE) {
            if (!empty($this->arguments['titles'])) {
                for ($i=0; $i < count($this->arguments['titles']) && $i < count($this->arguments['displayorder']); $i++) {
                    if (!empty($this->arguments['titles'][$i])) {
                        $formsTitles[$this->arguments['displayorder'][$i]] = $this->arguments['titles'][$i];
                    } elseif ($this->arguments['displayorder'][$i] == 'page') {
                        $formsTitles['page'] = _t("PAGES");
                    } elseif ($this->arguments['displayorder'][$i] == 'logpage') {
                        $formsTitles['logpage'] = _t("NEWTEXTSEARCH_LOG_PAGES");
                    } elseif (strval($this->arguments['displayorder'][$i]) == strval(intval($this->arguments['displayorder'][$i])) && intval($this->arguments['displayorder'][$i]) > 0) {
                        $form = $this->formManager->getOne(intval($this->arguments['displayorder'][$i]));
                        if (!empty($form)) {
                            $formsTitles[$form['bn_id_nature']] = $form['bn_label_nature'] ?? $form['bn_id_nature'];
                        }
                    }
                }
            }
            if (empty($this->arguments['displayorder'])) {
                $forms = $this->formManager->getAll();
                foreach ($forms as $form) {
                    if (!isset($formsTitles[$form['bn_id_nature']])) {
                        $formsTitles[$form['bn_id_nature']] = $form['bn_label_nature'] ?? $form['bn_id_nature'];
                    }
                }
            }
            if (!isset($formsTitles['page'])) {
                $formsTitles['page'] = _t("PAGES");
            }
            if (in_array('logpage', $this->arguments['displayorder']) && !isset($formsTitles['logpage'])) {
                $formsTitles['logpage'] = _t("NEWTEXTSEARCH_LOG_PAGES");
            }

            foreach ($this->arguments['displayorder'] as $type) {
                if (substr($type, 0, strlen('tag:')) == 'tag:' && strlen($type) > strlen('tag:')) {
                    if (!isset($formsTitles[$type])) {
                        $tag = substr($type, strlen('tag:'));
                        $formsTitles[$type] = $tag;
                    }
                }
            }
        }

        return $this->render("@core/{$this->arguments['template']}", [
            'displayForm' => $displayForm,
            'searchText' => $searchText,
            'args' => $this->arguments,
            'formsTitles' => $formsTitles,
        ]);
    }
}
