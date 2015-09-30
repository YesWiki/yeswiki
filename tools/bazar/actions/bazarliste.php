<?php

/**
 * bazarliste : programme affichant les fiches du bazar sous forme de liste accordeon (ou autre template).
 *
 *
 *
 *@author        Florian SCHMITT <florian@outils-reseaux.org>
 *
 *@version       $Revision: 1.5 $ $Date: 2010/03/04 14:19:03 $
 **/

// test de sécurité pour vérifier si on passe par wiki
if (!defined('WIKINI_VERSION')) {
    die('acc&egrave;s direct interdit');
}

// on compte le nombre de fois que l'action bazarliste est appelée afin de différencier les instances
if (!isset($GLOBALS['nbbazarliste'])) {
    $GLOBALS['nbbazarliste'] = 0;
}
++$GLOBALS['nbbazarliste'];

// Recuperation de tous les parametres
$params = getAllParameters($this);

// Recuperation de tous les formulaires
$allforms = baz_valeurs_tous_les_formulaires();

// tableau des fiches correspondantes aux critères
if (is_array($params['idtypeannonce'])) {
    $tableau_resultat = array();
    foreach ($params['idtypeannonce'] as $formid) {
        $tableau_resultat = array_merge(
            $tableau_resultat,
            baz_requete_recherche_fiches($params['query'], 'alphabetique', $formid, '', 1, '', '', true, '')
        );
    }
} else {
    $tableau_resultat = baz_requete_recherche_fiches($params['query'], 'alphabetique', '', '', 1, '', '', true, '');
}

// tableau des valeurs "facettables" avec leur nombres
$facettevalue = array();

// tableau qui contiendra les fiches
$fiches['fiches'] = array();
foreach ($tableau_resultat as $fiche) {
    $fiche = json_decode($fiche['body'], true);
    if (TEMPLATES_DEFAULT_CHARSET != 'UTF-8') {
        $fiche = array_map('utf8_decode', $fiche);
    }
    $fiche['html_data'] = getHtmlDataAttributes($fiche);
    $fiche['html'] = baz_voir_fiche(0, $fiche);  //permet de voir la fiche

    if (!empty($correspondance)) {
        $tabcorrespondance = explode('=', trim($correspondance));
        if (isset($tabcorrespondance[0])) {
            if (isset($tabcorrespondance[1]) && isset($fiche[$tabcorrespondance[1]])) {
                $fiche[$tabcorrespondance[0]] = $fiche[$tabcorrespondance[1]];
            } else {
                $fiche[$tabcorrespondance[0]] = '';
            }
        } else {
            exit('<div class="alert alert-danger">action bazarliste : parametre correspondance mal rempli :
             il doit etre de la forme correspondance="identifiant_1=identifiant_2"</div>');
        }
    }
    $fiche['datastr'] = getHtmlDataAttributes($fiche);

    // on scanne tous les champs qui pourraient faire des filtres pour les facettes
    if (count($params['groups']) > 0) {
        foreach ($fiche as $key => $value) {
            if (!empty($value)) {
                $facetteasked = (isset($params['groups'][0]) && $params['groups'][0] == 'all')
                    || in_array($key, $params['groups']);
                // champs génériques des métadonnées
                if (in_array($key, array('id_typeannonce', 'createur')) && $facetteasked) {
                    if ($key == 'id_typeannonce') {
                        $value = $fiche['id_typeannonce'].'|'.
                          $allforms[$fiche['categorie_fiche']][$fiche['id_typeannonce']]['bn_label_nature'];
                    }
                    if (isset($facettevalue[$key][$value])) {
                        ++$facettevalue[$key][$value];
                    } else {
                        $facettevalue[$key][$value] = 1;
                    }
                } else { // champs type liste ou checkbox
                    $templatef = $allforms[$fiche['categorie_fiche']][$fiche['id_typeannonce']]['template'];
                    if (is_array($templatef)) {
                        foreach ($templatef as $id => $val) {
                            if ($val[1] === $key || (isset($val[6]) && $val[0].$val[1].$val[6] === $key)) {
                                $islist = in_array(
                                    $templatef[$id][0],
                                    array('checkbox', 'liste', 'scope')
                                );
                                if ($islist && $facetteasked) {
                                    $tabval = explode(',', $value);
                                    foreach ($tabval as $val) {
                                        if (isset($facettevalue[$templatef[$id][1].'|'.$key][$val])) {
                                            ++$facettevalue[$templatef[$id][1].'|'.$key][$val];
                                        } else {
                                            $facettevalue[$templatef[$id][1].'|'.$key][$val] = 1;
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }
    // tableau qui contient le contenu de toutes les fiches
    $fiches['fiches'][$fiche['id_fiche']] = $fiche;
}

// tri des fiches
$GLOBALS['ordre'] = $params['ordre'];
$GLOBALS['champ'] = $params['champ'];
usort($fiches['fiches'], 'champCompare');

// Limite le nombre de résultat au nombre de fiches demandées
if ($params['nb'] != '') {
    $fiches['fiches'] = array_slice($fiches['fiches'], 0, $params['nb']);
}

if (!empty($params['pagination'])) {
    $fiches['info_res'] = '<div class="alert alert-info">'._t('BAZ_IL_Y_A');

    $nb_result = count($fiches['fiches']);

    if ($nb_result <= 1) {
        $fiches['info_res'] .= $nb_result.' '._t('BAZ_FICHE').'</div>'."\n";
    } else {
        $fiches['info_res'] .= $nb_result.' '._t('BAZ_FICHES').'</div>'."\n";
    }
    // Mise en place du Pager
    require_once 'Pager/Pager.php';
    $param = array(
        'mode' => BAZ_MODE_DIVISION,
        'perPage' => $params['pagination'],
        'delta' => BAZ_DELTA,
        'httpMethod' => 'GET',
        'extraVars' => array_merge($_POST, $_GET),
        'altNext' => _t('BAZ_SUIVANT'),
        'altPrev' => _t('BAZ_PRECEDENT'),
        'nextImg' => _t('BAZ_SUIVANT'),
        'prevImg' => _t('BAZ_PRECEDENT'),
        'itemData' => $fiches['fiches'],
    );
    $pager = &Pager::factory($param);
    $fiches['fiches'] = $pager->getPageData();
    $fiches['pager_links'] = '<div class="bazar_numero">'.$pager->links.'</div>'."\n";
} else {
    $fiches['info_res'] = '';
    $fiches['pager_links'] = '';
}

// affichage des resultats
include_once 'tools/bazar/libs/squelettephp.class.php';
// On cherche un template personnalise dans le repertoire themes/tools/bazar/templates
$templatetoload = 'themes/tools/bazar/templates/'.$params['template'];
if (!is_file($templatetoload)) {
    $templatetoload = 'tools/bazar/presentation/templates/'.$params['template'];
}
$squelfacette = new SquelettePhp($templatetoload);
$fiches['param'] = $params;
$squelfacette->set($fiches);
$output = $squelfacette->analyser();

// affichage spécifique pour facette
if (count($facettevalue) > 0) {
    // affichage des resultats et filtres dans une grille
    $outputfacette = '<div class="facette-container row row-fluid">'."\n";

    // calcul de la largeur de la colonne pour les resultats, en fonction de la taille des filtres
    $resultcolsize = 12 - intval($params['filtercolsize']);

    // colonne des resultats
    $outputresult = '<div class="col-xs-'.$resultcolsize.' span'.$resultcolsize.'">'."\n".
        '<div class="results">'."\n".
        '<div class="alert alert-info">'."\n".
        _t('BAZ_IL_Y_A').
        '<span class="nb-results">'.count($fiches['fiches']).'</span> '._t('BAZ_FICHES_CORRESPONDANTES_FILTRES')."\n".
        '.</div>'."\n".
        $output."\n".
        '</div> <!-- /.results -->'."\n".
        '</div> <!-- /.col-xs-'.$resultcolsize.' -->';

    // colonne des filtres
    $outputfilter = '<div class="col-xs-'.$params['filtercolsize'].' span'.$params['filtercolsize'].'">'."\n".
                    '<div class="filters no-dblclick">'."\n";
    if (isset($facettevalue['id_typeannonce'])) {
        if (count($facettevalue['id_typeannonce']) > 1) {
            $outputfilter .=  '<div class="filter-box panel panel-default" data-id="id_typeannonce">'."\n";
            $outputfilter .=  '<div class="panel-heading">'._t('BAZ_TYPE_FICHE').'</div>'."\n";
            $outputfilter .=  '<div class="panel-body">'."\n";
            foreach ($facettevalue['id_typeannonce'] as $id => $nb) {
                $infotypef = explode('|', $id);
                $outputfilter .=  '<div class="checkbox"><label>
                <input class="filter-checkbox" type="checkbox" name="id_typeannonce" 
                value="'.htmlspecialchars($infotypef[0]).'"> '.$infotypef[1].' (<span class="nb">'.$nb.'</span>)
                </label></div>'."\n";
            }
            $outputfilter .=  '</div></div><!-- /.filter-box -->'."\n";
        }
        unset($facettevalue['id_typeannonce']);
    }
    if (isset($facettevalue['createur'])) {
        if (count($facettevalue['createur']) > 1) {
            $outputfilter .=  '<div class="filter-box panel panel-default" data-id="createur">'."\n";
            $outputfilter .=  '<div class="panel-heading">'._t('BAZ_CREATOR').'</div>'."\n";
            $outputfilter .=  '<div class="panel-body">'."\n";
            foreach ($facettevalue['createur'] as $id => $nb) {
                $outputfilter .=  '<div class="checkbox"><label>
                <input class="filter-checkbox" type="checkbox" name="createur" 
                value="'.htmlspecialchars($id).'"> '.$id.' (<span class="nb">'.$nb.'</span>)
                </label></div>'."\n";
            }
            $outputfilter .=  '</div></div><!-- /.filter-box -->'."\n";
        }
        unset($facettevalue['createur']);
    }
    $i = 0;
    $first = true;
    
    foreach ($params['groups'] as $id) {
        $index = preg_replace('/^(liste|checkbox)/U', '', $id).'|'.$id;
        if (count($facettevalue[$index]) > 0) {
            $tabkey = explode('|', $index);
            $list = baz_valeurs_liste($tabkey[0]);
            $idkey = htmlspecialchars($tabkey[1]);
            $outputfilter .=  '<div class="filter-box panel panel-default '.$idkey.'" data-id="'.$idkey.'">'."\n";
            $titlefilterbox = '';
            if (isset($params['groupicons'][$i]) && !empty($params['groupicons'][$i])) {
                $titlefilterbox .= '<i class="'.$params['groupicons'][$i].'"></i> ';
            }
            if (isset($params['titles'][$i]) && !empty($params['titles'][$i])) {
                $titlefilterbox .= $params['titles'][$i];
            } else {
                $titlefilterbox .= $list['titre_liste'];
            }
            $outputfilter .=  '<div class="panel-heading';
            if (!$first) {
                $outputfilter .= ' collapsed';
            }
            $outputfilter .= '" data-toggle="collapse" href="#collapse'.$GLOBALS['nbbazarliste'].'_'.$idkey.'" >'.
                $titlefilterbox.'</div>'."\n";
            $outputfilter .= '<div id="collapse'.$GLOBALS['nbbazarliste'].'_'.$idkey.'" class="panel-collapse';
            if ($first) {
                $outputfilter .= ' in';
            }
            $outputfilter .= ' collapse">'."\n";
            $outputfilter .= '<div class="panel-body">'."\n";
            foreach ($list['label'] as $listkey => $label) {
                if (isset($facettevalue[$index][$listkey]) && !empty($facettevalue[$index][$listkey])) {
                    $outputfilter .=  '<div class="checkbox"><label>
                    <input class="filter-checkbox" type="checkbox" name="'.$idkey.'" 
                    value="'.htmlspecialchars($listkey).'"> '. $label .' (<span class="nb">'.$facettevalue[$index][$listkey].'</span>)
                    </label></div>'."\n";
                }
            }
            $outputfilter .=  '</div></div></div><!-- /.filter-box -->'."\n";
            ++$i;
            $first = false;
        } 
    }
    $outputfilter .= '</div> <!-- /.filters -->'."\n".
        '</div> <!-- /.col-xs-3 -->'."\n";

    // disposition des filtres (gauche ou droite)
    if ($params['filterposition'] == 'right') {
        $outputfacette .= $outputresult.$outputfilter;
    } else {
        $outputfacette .= $outputfilter.$outputresult;
    }

    $output = $outputfacette.'</div><!-- /.facette-container.row -->';
}

// affichage à l'écran
echo $output;
