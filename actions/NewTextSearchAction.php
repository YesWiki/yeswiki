<?php

/*
 * This file is part of YesWiki.
 *
 * Authors : see README.md file that was distributed with this source code.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use YesWiki\Bazar\Controller\EntryController;
use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Bazar\Service\FormManager;
use YesWiki\Bazar\Service\SearchManager;
use YesWiki\Core\Service\AclService;
use YesWiki\Core\Service\DbService;
use YesWiki\Core\Service\TemplateEngine;
use YesWiki\Core\YesWikiAction;
use YesWiki\Tags\Service\TagsManager;

class NewTextSearchAction extends YesWikiAction
{
    public const DEFAULT_TEMPLATE = "newtextsearch.twig";
    public const BY_FORM_TEMPLATE = "newtextsearch-by-form.twig";
    public const MAX_DISPLAY_PAGES = 25;
    public const DEFAULT_LIMIT = 100;

    protected $aclService;
    protected $dbService;
    protected $entryController;
    protected $entryManager;
    protected $formManager;
    protected $searchManager;
    protected $tagsManager;
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
        $this->aclService = $this->getservice(AclService::class);
        $this->dbService = $this->getservice(DbService::class);
        $this->entryController = $this->getservice(EntryController::class);
        $this->entryManager = $this->getservice(EntryManager::class);
        $this->formManager = $this->getservice(FormManager::class);
        $this->searchManager = $this->getservice(SearchManager::class);
        $this->tagsManager = $this->getservice(TagsManager::class);

        // récupération de la recherche à partir du paramètre 'phrase'
        $searchText = !empty($this->arguments['phrase']) ? htmlspecialchars($this->arguments['phrase'], ENT_COMPAT, YW_CHARSET) : '';

        // affichage du formulaire si $this->arguments['phrase'] est vide
        $displayForm = empty($searchText);

        if (empty($searchText) && !empty($_GET['phrase'])) {
            $searchText = htmlspecialchars($_GET['phrase'], ENT_COMPAT, YW_CHARSET);
        }

        $formsTitles = [];
        if (!empty($this->arguments['titles'])) {
            for ($i=0; $i < count($this->arguments['titles']) && $i < count($this->arguments['displayorder']); $i++) {
                if (!empty($this->arguments['titles'][$i])) {
                    $formsTitles[$this->arguments['displayorder'][$i]] = $this->arguments['titles'][$i];
                }
            }
        }
        if (!empty($searchText)) {
            list('requestfull' => $sqlRequest, 'needles' => $needles) = $this->getSqlRequest($searchText);
            $this->addDisplayOrderRestrictions($sqlRequest);
            $this->addTagsRestrictions($sqlRequest);
            $this->addSQLLimit($sqlRequest);
            $results = $this->dbService->loadAll($sqlRequest);
            if (empty($results)) {
                $results = [];
            } else {
                $counter = 0;
                $filteredResults = [];
                $isActionBuilderPreview = $this->wiki->GetPageTag() == 'root';
                $tagsToFollow = $this->getPagesForTagsToFollow();
                foreach ($results as $key => $page) {
                    if ($this->aclService->hasAccess("read", $page["tag"])) {
                        $data = $page;
                        if ($this->arguments['displaytext'] &&
                            empty($this->arguments['separator']) &&
                            $counter < self::MAX_DISPLAY_PAGES &&
                            $page["tag"] != $this->wiki->tag &&
                            !$isActionBuilderPreview &&
                            !$this->wiki->IsIncludedBy($page["tag"])) {
                            if ($this->entryManager->isEntry($page["tag"])) {
                                $renderedEntry = $this->entryController->view($page["tag"], '', false); // without footer
                                $data['preRendered'] = $this->displayNewSearchResult(
                                    $renderedEntry,
                                    $searchText,
                                    $needles
                                );
                            }

                            if (empty($data['preRendered'])) {
                                $data['preRendered'] = $this->displayNewSearchResult(
                                    $this->wiki->Format($page["body"], 'wakka', $page["tag"]),
                                    $searchText,
                                    $needles
                                );
                            }
                            $counter += 1;
                        }
                        if ($this->arguments['template'] == self::BY_FORM_TEMPLATE) {
                            if ($this->entryManager->isEntry($page["tag"])) {
                                $entry = $this->entryManager->getOne($page["tag"]);
                                if (!empty($entry['id_typeannonce'])) {
                                    $data['form'] =  strval(intval($entry['id_typeannonce']));
                                    if (!isset($formsTitles[$data['form']])) {
                                        $form = $this->formManager->getOne($data['form']);
                                        $formsTitles[$data['form']] = $form['bn_label_nature'] ?? $data['form'];
                                    }
                                }
                            } elseif (substr($page["tag"], 0, strlen('LogDesActionsAdministratives')) == 'LogDesActionsAdministratives') {
                                $data['form'] =  'logpage';
                            } else {
                                $data['form'] =  'page';
                            }
                        }
                        if ($this->entryManager->isEntry($page["tag"])) {
                            if (!empty($entry['bf_titre'])) {
                                $data['title'] = $entry['bf_titre'];
                            }
                        } elseif (function_exists('getTitleFromBody')) {
                            $titleFormPage = getTitleFromBody($page);
                            if (!empty($titleFormPage)) {
                                $data['title'] = $titleFormPage;
                            }
                        }
                        $filteredResults[] = $data;
                        if (!empty($tagsToFollow[$page["tag"]])) {
                            foreach ($tagsToFollow[$page["tag"]] as $tag) {
                                $data['form'] = $tag;
                                $filteredResults[] = $data;
                                if (!isset($formsTitles[$tag])) {
                                    $formsTitles[$tag] = $tag;
                                }
                            }
                        }
                    }
                }
                $results = $filteredResults;
                if (!isset($formsTitles['page'])) {
                    $formsTitles['page'] = _t("PAGES");
                }
                if (in_array('logpage', $this->arguments['displayorder']) && !isset($formsTitles['logpage'])) {
                    $formsTitles['logpage'] = _t("NEWTEXTSEARCH_LOG_PAGES");
                }
            }
        }

        return $this->render("@core/{$this->arguments['template']}", [
            'displayForm' => $displayForm,
            'searchText' => $searchText,
            'args' => $this->arguments,
            'results' => $results ?? [],
            'tag' => $this->params->get('rewrite_mode') ? '' : $this->wiki->tag,
            'formsTitles' => $formsTitles,
        ]);
    }

    private function getSqlRequest(string $searchText): array
    {
        // extract needles with values in list
        // find in values for entries
        $forms = $this->formManager->getAll();
        $needles = $this->searchManager->searchWithLists(str_replace(array('*', '?'), array('', '_'), $searchText), $forms);
        $requeteSQLForList = '';
        if (!empty($needles)) {
            $first = true;
            // generate search
            foreach ($needles as $needle => $results) {
                if (!empty($results)) {
                    if ($first) {
                        $first = false;
                    } else {
                        $requeteSQLForList .= ' AND ';
                    }
                    $requeteSQLForList .= '(';
                    // add regexp standard search
                    $requeteSQLForList .= 'body REGEXP \''.$needle.'\'';
                    // add search in list
                    // $results is an array not empty only if list
                    foreach ($results as $result) {
                        $requeteSQLForList .= ' OR ';
                        if (!$result['isCheckBox']) {
                            $requeteSQLForList .= ' body LIKE \'%"'.str_replace('_', '\\_', $result['propertyName']).'":"'.$result['key'].'"%\'';
                        } else {
                            $requeteSQLForList .= ' body REGEXP \'"'.str_replace('_', '\\_', $result['propertyName']).'":(' .
                                '"'.$result['key'] . '"'.
                                '|"[^"]*,' . $result['key'] . '"'.
                                '|"' . $result['key'] . ',[^"]*"'.
                                '|"[^"]*,' .$result['key'] . ',[^"]*"'.
                                ')\'';
                        }
                    }
                    $requeteSQLForList .= ')';
                }
            }
        }
        if (!empty($requeteSQLForList)) {
            $requeteSQLForList = ' OR ('.$requeteSQLForList.') ';
        }

        // Modification de caractère spéciaux
        $phraseFormatted= str_replace(array('*', '?'), array('%', '_'), $searchText);
        $phraseFormatted = $this->dbService->escape($phraseFormatted);

        // TODO retrouver la facon d'afficher les commentaires (AFFICHER_COMMENTAIRES ? '':'AND tag NOT LIKE "comment%"').
        $requestfull = "SELECT body, tag FROM {$this->dbService->prefixTable('pages')} ".
            "WHERE latest = \"Y\" {$this->aclService->updateRequestWithACL()} ".
            "AND (body LIKE \"%{$phraseFormatted}%\"{$requeteSQLForList})";

        return compact('requestfull', 'needles');
    }

    private function displayNewSearchResult($string, $phrase, $needles = []): string
    {
        $string = strip_tags($string);
        $query = trim(str_replace(array("+","?","*"), array(" "," "," "), $phrase));
        $qt = explode(" ", $query);
        $num = count($qt);
        $cc = ceil(154 / $num);
        $string_re = '';
        foreach ($needles as $needle => $result) {
            if (preg_match('/'.$needle.'/i', $string, $matches)) {
                $tab = preg_split("/(".$matches[0].")/iu", $string, 2, PREG_SPLIT_DELIM_CAPTURE);
                if (count($tab)>1) {
                    $avant = strip_tags(mb_substr($tab[0], -$cc, $cc));
                    $apres = strip_tags(mb_substr($tab[2], 0, $cc));
                    $string_re .= $this->render('@core/_newtextsearch-display_search-text.twig', [
                        'before' => $avant,
                        'content' => $tab[1],
                        'after' => $apres,
                    ]);
                }
            }
        }
        if (empty($string_re)) {
            for ($i = 0; $i < $num; $i++) {
                $tab[$i] = preg_split("/($qt[$i])/iu", $string, 2, PREG_SPLIT_DELIM_CAPTURE);
                if (count($tab[$i])>1) {
                    $avant[$i] = strip_tags(mb_substr($tab[$i][0], -$cc, $cc));
                    $apres[$i] = strip_tags(mb_substr($tab[$i][2], 0, $cc));
                    $string_re .= $this->render('@core/_newtextsearch-display_search-text.twig', [
                        'before' => $avant[$i],
                        'content' => $tab[$i][1],
                        'after' => $apres[$i],
                    ]);
                }
            }
        }
        return $string_re;
    }

    private function addSQLLimit(string &$sql)
    {
        $sql .=  " ORDER BY tag LIMIT {$this->arguments['limit']}";
    }

    private function addDisplayOrderRestrictions(string &$sql)
    {
        $onlyForms = array_filter(
            $this->arguments['displayorder'],
            function ($formId) {
                return !in_array($formId, ['page','logpage']) && (strval($formId) == strval(intval($formId)));
            }
        );
        if (!empty($this->arguments['displayorder']) && (!empty($onlyForms) || in_array('page', $this->arguments['displayorder']))) {
            $sql .= " AND (";
            if (in_array('page', $this->arguments['displayorder'])) {
                $sql .= "`tag` NOT IN (SELECT `resource` FROM {$this->dbService->prefixTable('triples')} ".
                "WHERE `value` = 'fiche_bazar' AND `property` = 'http://outils-reseaux.org/_vocabulary/type')";
                if (count($onlyForms) > 1) {
                    $sql .= " OR ";
                }
            }
            if (count($onlyForms) > 1) {
                $sql .= "(`tag` IN (SELECT `resource` FROM {$this->dbService->prefixTable('triples')} ".
                "WHERE `value` = 'fiche_bazar' AND `property` = 'http://outils-reseaux.org/_vocabulary/type') AND (";
                $sql .= implode(
                    " OR ",
                    array_map(
                        function ($formId) {
                            return " `body` LIKE '%\"id_typeannonce\":\"{$this->dbService->escape(intval($formId))}\"%'";
                        },
                        $onlyForms
                    )
                );
                $sql .= "))";
            }

            $sql .= ")";
        }
    }

    private function addTagsRestrictions(string &$sql)
    {
        if (!empty($this->arguments['onlytags'])) {
            $sql .= " AND `tag` IN (".
                "SELECT `resource` FROM {$this->dbService->prefixTable('triples')} ".
                "WHERE `value` IN (".implode(
                    ',',
                    array_map(
                        function ($tag) {
                            return "\"{$this->dbService->escape($tag)}\"";
                        },
                        $this->arguments['onlytags']
                    )
                ).") ".
                "AND property=\"http://outils-reseaux.org/_vocabulary/tag\" ".
                " )";
        }
    }

    private function getPagesForTagsToFollow(): array
    {
        $tagsToFollow = array_filter($this->arguments['displayorder'], function ($item) {
            return !empty($item) && !in_array($item, ['page','logpage']) && (strval($item) != strval(intval($item)));
        });
        $results = [];
        foreach ($tagsToFollow as $tag) {
            $pagesOrEntries = $this->tagsManager->getPagesByTags($tag);
            foreach ($pagesOrEntries as $page) {
                if (!isset($results[$page['tag']])) {
                    $results[$page['tag']] = [$tag];
                } elseif (!in_array($tag, $results[$page['tag']])) {
                    $results[$page['tag']][] = $tag;
                }
            }
        }
        return $results;
    }
}
