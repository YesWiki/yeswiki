<?php

namespace YesWiki\Search\Action;

use YesWiki\Content\Controller\EntryController;
use YesWiki\Content\Service\EntryManager;
use YesWiki\Content\Service\FormManager;
use YesWiki\Core\YesWikiAction;
use YesWiki\Identity\Service\AclService;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\PerformableArguments;
use YesWiki\Render\Service\LinkRenderer;
use YesWiki\Render\Service\MarkdownFormatterService;
use YesWiki\Search\Service\SearchManager;

/**
 * `{{newtextsearch}}` -- converted from the procedural actions/newtextsearch.php by ticket 06.
 *
 * The body still prints rather than returning, so it runs inside an output buffer in its
 * own method: that is what the old runFileInBuffer() did, and it keeps any early `return;`
 * in the body from discarding output.
 */
class NewtextsearchAction extends YesWikiAction implements RegisteredAction
{
    public static function performableName(): string
    {
        return 'newtextsearch';
    }

    public function run(): string
    {
        ob_start();
        try {
            $this->emit();
        } catch (\Throwable $t) {
            // Several of these bodies end in $this->exit(), which throws. The old
            // runFileInBuffer() accumulated output into a by-reference variable, so a throw
            // did not discard what had already been printed; keep that by flushing into the
            // shared output before rethrowing -- and close the buffer either way.
            $this->output .= (string)ob_get_clean();

            throw $t;
        }

        return (string)ob_get_clean();
    }

    private function emit(): void
    {
        /**
         * Adaptation de l'action textsearch & newtextsearch de wikini pour Yeswiki
         * INFORMATION D'UTILISATION
         * Utilisation {{newtextsearch}} en lieu eet place de {{textsearch}}.
         **/

        // On récupére ou initialise toutes le varible comme pour textsearch
        // label à afficher devant la zone de saisie
        $label = $this->getService(PerformableArguments::class)->get('label', _t('WHAT_YOU_SEARCH') . '&nbsp;: ');
        // largeur de la zone de saisie
        $size = $this->getService(PerformableArguments::class)->get('size', '40');
        // texte du bouton
        $button = $this->getService(PerformableArguments::class)->get('button', _t('SEARCH'));
        // texte à chercher
        $phrase = $this->getService(PerformableArguments::class)->get('phrase', false);
        // séparateur entre les éléments trouvés
        $separator = $this->getService(PerformableArguments::class)->get('separator', false);
        // prefixe des tables pour ce wiki
        $prefixe = $this->wiki->config['table_prefix'];
        // prefixe des tables pour ce wiki
        $user = $this->getService(AuthenticationService::class)->getLoggedUser();
        // nombre de pages dont on affiche une partie du contenu
        $maxDisplayedPages = 25;

        $entryController = $this->wiki->services->get(EntryController::class);
        $entryManager = $this->wiki->services->get(EntryManager::class);

        // se souvenir si c'était :
        // -- un paramétre de l'action : {{textsearch phrase="Test"}}
        // -- ou du CGI http://example.org/?wiki=RechercheTexte&phrase=Test
        //
        // récupérer le paramétre de l'action
        $paramPhrase = htmlspecialchars($phrase, ENT_COMPAT, YW_CHARSET);
        // ou, le cas échéant, récupérer le paramétre du CGI
        if (!$phrase && isset($_GET['phrase'])) {
            $phrase = htmlspecialchars($_GET['phrase'], ENT_COMPAT, YW_CHARSET);
        }

        // s'il y a un paramétre d'action "phrase", on affiche uniquement le résultat
        // dans le cas contraire, présenter une zone de saisie
        if (!$paramPhrase) {
            echo $this->wiki->FormOpen('', '', 'get');
            echo '<div class="input-prepend input-append input-group input-group-lg">
                  <span class="add-on input-group-addon"><i class="fa fa-search icon-search"></i></span>
                  <input name="phrase" type="text" class="form-control" placeholder="' . (($label) ? $label : '') . '" size="', $size, '" value="', $phrase, '" >
                  <span class="input-group-btn">
                  <input type="submit" class="btn btn-primary btn-lg" value="', $button, '" />
                  </span>
                  </div>
                  <span class="">
                  <small>' . htmlspecialchars(_t('NEWTEXTSEARCH_HINT')) . '</small>
                  </span><!-- /input-group --><br>';
            echo "\n", $this->wiki->FormClose();
        }

        if (!function_exists('displayNewSearchResult')) {
            /* fonction nécessaire à l'affichage en contexte */
            function displayNewSearchResult($string, $phrase, $needles = [])
            {
                $string = strip_tags($string);
                $query = trim(str_replace(['+', '?', '*'], [' ', ' ', ' '], $phrase));
                $qt = explode(' ', $query);
                $num = count($qt);
                $cc = ceil(154 / $num);
                $string_re = '';
                foreach ($needles as $needle => $result) {
                    if (preg_match('/' . $needle . '/i', $string, $matches)) {
                        $tab = preg_split('/(' . $matches[0] . ')/iu', $string, 2, PREG_SPLIT_DELIM_CAPTURE);
                        if (count($tab) > 1) {
                            $avant = strip_tags(mb_substr($tab[0], -$cc, $cc));
                            $apres = strip_tags(mb_substr($tab[2], 0, $cc));
                            $string_re .= '<p style="margin-top:0;margin-left:1rem;"><i style="color:silver;">[…]</i>' . $avant . '<b>' . $tab[1] . '</b>' . $apres . '<i style="color:silver;">[…]</i></p> ';
                        }
                    }
                }
                if (empty($string_re)) {
                    for ($i = 0; $i < $num; $i++) {
                        $tab[$i] = preg_split("/($qt[$i])/iu", $string, 2, PREG_SPLIT_DELIM_CAPTURE);
                        if (is_array($tab[$i]) && count($tab[$i]) > 1) {
                            $avant[$i] = strip_tags(mb_substr($tab[$i][0], -$cc, $cc));
                            $apres[$i] = strip_tags(mb_substr($tab[$i][2], 0, $cc));
                            $string_re .= '<p style="margin-top:0;margin-left:1rem;"><i style="color:silver;">[…]</i>' . $avant[$i] . '<b>' . $tab[$i][1] . '</b>' . $apres[$i] . '<i style="color:silver;">[…]</i></p> ';
                        }
                    }
                }

                return $string_re;
            }
        }

        // lancement de la recherche
        if ($phrase) {
            // extract needles with values in list
            // find in values for entries
            $formManager = $this->wiki->services->get(FormManager::class);
            $forms = $formManager->getAll();
            $searchManager = $this->wiki->services->get(SearchManager::class);
            $dbService = $this->wiki->services->get(DbService::class);
            $needles = $searchManager->searchWithLists(str_replace(['*', '?'], ['', '_'], $phrase), $forms);
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
                        $requeteSQLForList .= 'body REGEXP \'' . $dbService->escape($needle) . '\'';
                        // add search in list
                        // $results is an array not empty only if list
                        foreach ($results as $result) {
                            $requeteSQLForList .= ' OR ';
                            $escapedPropertyName = $dbService->escape(str_replace('_', '\\_', $result['propertyName']));
                            $escapedKey = $dbService->escape($result['key']);
                            if (!$result['isCheckBox']) {
                                $requeteSQLForList .= ' body LIKE \'%"' . $escapedPropertyName . '":"' . $escapedKey . '"%\'';
                            } else {
                                $requeteSQLForList .= ' body REGEXP \'"' . $escapedPropertyName . '":(' .
                                    '"' . $escapedKey . '"' .
                                    '|"[^"]*,' . $escapedKey . '"' .
                                    '|"' . $escapedKey . ',[^"]*"' .
                                    '|"[^"]*,' . $escapedKey . ',[^"]*"' .
                                    ')\'';
                            }
                        }
                        $requeteSQLForList .= ')';
                    }
                }
            }
            if (!empty($requeteSQLForList)) {
                $requeteSQLForList = ' OR (' . $requeteSQLForList . ') ';
            }

            // get some services
            $aclService = $this->wiki->services->get(AclService::class);

            // Modification de caractère spéciaux
            $phraseFormatted = str_replace(['*', '?'], ['%', '_'], $phrase);
            $phraseFormatted = $dbService->escape($phraseFormatted);

            // Blablabla SQL
            // TODO retrouver la facon d'afficher les commentaires (AFFICHER_COMMENTAIRES ? '':'AND tag NOT LIKE "comment%"').

            $aclRequest = $aclService->updateRequestWithACL();

            $requestfull = "SELECT body, tag FROM {$dbService->prefixTable('pages')} WHERE latest = 'Y' " . (!empty($aclRequest) ? ' AND ' . $aclRequest : '') .
                " AND (body LIKE '%{$phraseFormatted}%'{$requeteSQLForList}) ORDER BY tag LIMIT 100";

            // exécution de la requete
            if ($resultat = $dbService->loadAll($requestfull)) {
                if ($GLOBALS['js']) {
                    $js = $GLOBALS['js'];
                } else {
                    $js = '';
                }
                // affichage des resultats

                // affichage des résultats en liste
                if (empty($separator)) {
                    echo $this->getService(MarkdownFormatterService::class)->format('---- --- **' . _t('SEARCH_RESULTS') . ' [""' . $phrase . '""] :---**');
                    echo '<ol>';
                    $counter = 0;
                    foreach ($resultat as $i => $page) {
                        if ($this->getService(AclService::class)->hasAccess('read', $page['tag'])) {
                            $lien = $this->getService(LinkRenderer::class)->linkToPage($page['tag']);
                            echo '<li><h4 style="margin-bottom:0.2rem;">', $lien, '</h4>';
                            $extract = '';
                            if ($counter < $maxDisplayedPages) {
                                if ($entryManager->isEntry($page['tag'])) {
                                    $renderedEntry = $entryController->view($page['tag'], '', false); // without footer
                                    $extract = displayNewSearchResult($renderedEntry, $phrase, $needles);
                                }
                                if (empty($extract)) {
                                    $extract = displayNewSearchResult($this->getService(MarkdownFormatterService::class)->format($page['body']), $phrase, $needles);
                                }
                                $counter++;
                            }
                            echo $extract . "</li>\n";
                        }
                    }
                    echo '</ol>';

                // affichage des résultats en ligne
                } else {
                    $separator = htmlspecialchars($separator, ENT_COMPAT, YW_CHARSET);
                    echo '<p>' . _t('SEARCH_RESULT_OF') . ' "', htmlspecialchars($phrase, ENT_COMPAT, YW_CHARSET), '"&nbsp;: ';
                    foreach ($resultat as $i => $line) {
                        if ($this->getService(AclService::class)->hasAccess('read', $line['tag'])) {
                            echo (($i > 0) ? $separator : '') . $this->getService(LinkRenderer::class)->linkToPage($line['tag']);
                        }
                    }
                    echo '</p>', "\n";
                }
                $GLOBALS['js'] = $js;
            } else {
                echo $this->getService(MarkdownFormatterService::class)->format('---- --- **' . _t('NO_SEARCH_RESULT') . '.**');
            }
        }
    }
}
