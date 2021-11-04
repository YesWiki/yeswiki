<?php
/**
 * Adaptation de l'action textsearch & newtextsearch de wikini pour Yeswiki
 * Copyright (c) 2002, Hendrik Mans <hendrik@mans.de>
 * Copyright 2002, 2003 David DELON
 * Copyright 2002  Patrick PAUL
 * Copyright 2004  Jean Christophe ANDRé
 * Copyright 2004  Nicephore17
 * Copyright 2019  XF75013
 * 08/02/2019 - v1.0 initial release
 * INFORMATION D'UTILISATION
 * Utilisation {{newtextsearch}} en lieu eet place de {{textsearch}}
 **/

 use YesWiki\Bazar\Controller\EntryController;
 use YesWiki\Bazar\Service\EntryManager;
 use YesWiki\Bazar\Service\FormManager;
 use YesWiki\Bazar\Service\SearchManager;

 // On récupére ou initialise toutes le varible comme pour textsearch
// label à afficher devant la zone de saisie
$label = $this->GetParameter('label', _t('WHAT_YOU_SEARCH').'&nbsp;: ');
// largeur de la zone de saisie
$size = $this->GetParameter('size', '40');
// texte du bouton
$button = $this->GetParameter('button', _t('SEARCH'));
// texte à chercher
$phrase = $this->GetParameter('phrase', false);
// séparateur entre les éléments trouvés
$separator = $this->GetParameter('separator', false);
// prefixe des tables pour ce wiki
$prefixe = $this->config['table_prefix'];
// prefixe des tables pour ce wiki
$user = $this->GetUser();
// nombre de pages dont on affiche une partie du contenu
$maxDisplayedPages = 25;


$entryController = $this->services->get(EntryController::class);
$entryManager = $this->services->get(EntryManager::class);

// se souvenir si c'était :
// -- un paramétre de l'action : {{textsearch phrase="Test"}}
// -- ou du CGI http://example.org/wakka.php?wiki=RechercheTexte&phrase=Test
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
    echo $this->FormOpen('', '', 'get');
    echo '<div class="input-prepend input-append input-group input-group-lg">
          <span class="add-on input-group-addon"><i class="fa fa-search icon-search"></i></span>
          <input name="phrase" type="text" class="form-control" placeholder="'.(($label) ? $label : '').'" size="', $size, '" value="', $phrase, '" >
          <span class="input-group-btn">
          <input type="submit" class="btn btn-primary btn-lg" value="', $button, '" />
          </span>
          </div>
          <span class="">
          <small>Un caract&eacute;re inconnu peut &ecirc;tre remplac&eacute; par « ? » plusieurs par « * »</small>
          </span><!-- /input-group --><br>';
    echo "\n", $this->FormClose();
}

if (!function_exists('displayNewSearchResult')) {
    /* fonction nécessaire à l'affichage en contexte */
    function displayNewSearchResult($string, $phrase, $needles = [])
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
                    $string_re .= '<p style="margin-top:0;margin-left:1rem;"><i style="color:silver;">[…]</i>' . $avant . '<b>' . $tab[1] . '</b>' . $apres . '<i style="color:silver;">[…]</i></p> ';
                }
            }
        }
        if (empty($string_re)) {
            for ($i = 0; $i < $num; $i++) {
                $tab[$i] = preg_split("/($qt[$i])/iu", $string, 2, PREG_SPLIT_DELIM_CAPTURE);
                if (count($tab[$i])>1) {
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
    $formManager = $this->services->get(FormManager::class);
    $forms = $formManager->getAll();
    $searchManager = $this->services->get(SearchManager::class);
    $needles = $searchManager->searchWithLists(str_replace(array('*', '?'), array('', '_'), $phrase), $forms);
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
    $phraseFormatted= str_replace(array('*', '?'), array('%', '_'), $phrase);
    $phraseFormatted = $this->services->get(\YesWiki\Core\Service\DbService::class)->escape($phraseFormatted);

    // Blablabla SQL
    $requestfull = 'SELECT body, tag FROM '.$prefixe.'pages
                  WHERE latest = "Y"
                  AND EXISTS( SELECT * FROM '.$prefixe.'acls WHERE tag = page_tag AND privilege = "read"
                  AND ( list IS NULL OR list ="*" '.
                  ($user ? 'OR owner = "'.$user['name'].'" OR list = "+" OR (list NOT LIKE "%!'.$user['name'].'%" AND list LIKE "%'.$user['name'].'")':'').')'.
                  // TODO retrouver la facon d'afficher les commentaires (AFFICHER_COMMENTAIRES ? '':'AND tag NOT LIKE "comment%"').
                  ') AND body LIKE "%' . $phraseFormatted . '%"'.$requeteSQLForList.'
                  ORDER BY tag LIMIT 100';

    // exécution de la requete
    if ($resultat = $this->LoadAll($requestfull)) {
        if ($GLOBALS['js']) {
            $js = $GLOBALS['js'];
        } else {
            $js = '';
        }
        // affichage des resultats

        // affichage des résultats en liste
        if (empty($separator)) {
            echo $this->Format('---- --- **Résultats de la recherche [""'.$phrase.'""] :---**');
            echo('<ol>');
            $counter = 0;
            foreach ($resultat as $i => $page) {
                if ($this->HasAccess("read", $page["tag"])) {
                    $lien = $this->ComposeLinkToPage($page["tag"]);
                    echo '<li><h4 style="margin-bottom:0.2rem;">', $lien, "</h4>";
                    $extract= '';
                    if ($counter < $maxDisplayedPages) {
                        if ($entryManager->isEntry($page["tag"])) {
                            $renderedEntry = $entryController->view($page["tag"], '', false); // without footer
                            $extract = displayNewSearchResult($renderedEntry, $phrase, $needles);
                        }
                        if (empty($extract)) {
                            $extract = displayNewSearchResult($this->Format($page["body"], 'wakka', $page["tag"]), $phrase, $needles);
                        }
                        $counter += 1;
                    }
                    echo $extract."</li>\n";
                }
            }
            echo('</ol>');

        // affichage des résultats en ligne
        } else {
            $separator = htmlspecialchars($separator, ENT_COMPAT, YW_CHARSET);
            echo '<p>'._t('SEARCH_RESULT_OF').' "', htmlspecialchars($phrase, ENT_COMPAT, YW_CHARSET), '"&nbsp;: ';
            foreach ($resultat as $i => $line) {
                if ($this->HasAccess("read", $line["tag"])) {
                    echo((($i>0) ? $separator:'').$this->ComposeLinkToPage($line['tag']));
                }
            }
            echo '</p>', "\n";
        }
        $GLOBALS['js'] = $js;
    } else {
        echo $this->Format('---- --- **Désolé mais il n\'y a aucun de résultat pour votre recherche.**');
    }
}
