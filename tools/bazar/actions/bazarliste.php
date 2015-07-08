<?php
/**
* bazarliste : programme affichant les fiches du bazar sous forme de liste accordeon (ou autre template)
*
*
*@package Bazar
*
*@author        Florian SCHMITT <florian@outils-reseaux.org>
*@version       $Revision: 1.5 $ $Date: 2010/03/04 14:19:03 $
*
*
**/

// test de sécurité pour vérifier si on passe par wiki
if (!defined("WIKINI_VERSION")) {
        die ("acc&egrave;s direct interdit");
}

// recuperation des parametres
$categorie_nature = $this->GetParameter("categorienature");
// par exemple ici {{bazarliste categorienature="actus"}} va mettre dans la variable $categorie_nature la valeur "actus"
if (empty($categorie_nature)) { // dans le cas ou il n'y a pas de valeur précisée, alors il les prend toutes
    $categorie_nature = 'toutes';
}

// identifiant du formulaire (plusieures valuers possibles, séparées par des virgules)
$id_typeannonce = $this->GetParameter("idtypeannonce");
if (empty($id_typeannonce)) {
    $id_typeannonce = array();
} else {
    $id_typeannonce = explode(",", $id_typeannonce);
    $id_typeannonce = array_map('trim', $id_typeannonce);
}

//on recupere les parameres pour une requete specifique
if (isset($_GET['query'])) {
    $query = $this->GetParameter("query");
    if (!empty($query)) {
        $query .= '|'.$_GET['query'];
    } else {
        $query = $_GET['query'];
    }
} else {
    $query = $this->GetParameter("query");
}
if (!empty($query)) {
    $tabquery = array();
    $tableau = array();
    $tab = explode('|', $query); //découpe la requete autour des |
    foreach ($tab as $req) {
        $tabdecoup = explode('=', $req, 2);
        $tableau[$tabdecoup[0]] = trim($tabdecoup[1]);
    }
    $tabquery = array_merge($tabquery, $tableau);
} else {
    $tabquery = '';
}

// ordre du tri (asc ou desc)
$GLOBALS['ordre'] = $this->GetParameter("ordre");
if (empty($GLOBALS['ordre'])) {
    $GLOBAL['ordre'] = 'asc';
}

// champ du formulaire utilisé pour le tri
$GLOBALS['champ']   = $this->GetParameter("champ");
if (empty($GLOBALS['champ'])) {
    $GLOBALS['champ'] = 'bf_titre';  // si pas de champ précisé, on triera par le titre
}

// template utilisé pour l'affichage
$template = $this->GetParameter("template");
if (empty($template)) {
    $template = BAZ_TEMPLATE_LISTE_DEFAUT;
}

// nombre maximal de résultats à afficher
$nb = $this->GetParameter("nb");
if (empty($nb)) {
    $nb = '';
}

// facette : identifiants servant de filtres 
//    plusieures valeurs possibles, séparées par des virgules,
//    "all" pour toutes les facette possibles)
//    exemple : {{bazarliste groups="bf_ce_titre,bf_ce_pays,etc."..}}
$groups = $this->GetParameter("groups");
if (empty($groups)) {
    $groups = array();
} else {
    $groups = explode(",", $groups);
    $groups = array_map('trim', $groups);
}

// facette: titres des boite de filtres correspondants au parametre groups
//    plusieures valeurs possibles, séparées par des virgules, le meme nombre que "groups"
//    exemple : {{bazarliste titles="Titre,Pays,etc."..}}
$titles = $this->GetParameter("titles");
if (empty($titles)) {
    $titles = array();
} else {
    $titles = explode(",", $titles);
    $titles = array_map('trim', $titles);
}

// nombre de résultats affichées avant pagination
$pagination = $this->GetParameter("pagination");

// correspondance transfere les valeurs d'un champs vers un autre, afin de correspondre dans un template
$correspondance = $this->GetParameter("correspondance");

// tableau des fiches correspondantes aux critères 
$tableau_resultat = array();
foreach ($id_typeannonce as $annonce) {
    $tableau_resultat = array_merge(
        $tableau_resultat,
        baz_requete_recherche_fiches($tabquery, 'alphabetique', $annonce, '', 1, '', '', true, '')
    );
}

// parametres pour bazarliste avec carto
$param = array();

/*
 * width : largeur de la carte à l'écran en pixels ou pourcentage
 */
$param['width'] = $this->GetParameter("width");
if (empty($param['width'])) {
    $param['width'] = BAZ_GOOGLE_IMAGE_LARGEUR;
}

/*
 * height : hauteur de la carte à l'écran en pixels ou pourcentage
 */
$param['height'] = $this->GetParameter("height");
if (empty($param['height'])) {
    $param['height'] = BAZ_GOOGLE_IMAGE_HAUTEUR;
}

/*
 * lat : latitude point central en degres WGS84 (exemple : 46.22763) , sinon parametre par defaut
 */
$param['latitude'] = $this->GetParameter("lat");
if (empty($param['latitude'])) {
    $param['latitude'] = BAZ_MAP_CENTER_LAT;
}

/*
 * lon : longitude point central en degres WGS84 (exemple : 3.42313) , sinon parametre par defaut
 */
$param['longitude'] = $this->GetParameter("lon");
if (empty($param['longitude'])) {
    $param['longitude'] = BAZ_MAP_CENTER_LON;
}

/*
 * niveau de zoom : de 1 (plus eloigne) a 15 (plus proche) , sinon parametre par defaut 5
 */
$param['zoom'] = $this->GetParameter("zoom");
if (empty($param['zoom'])) {
    $param['zoom'] = BAZ_GOOGLE_ALTITUDE;
}

/*
 * Outil de navigation , sinon parametre par defaut true
 */
$param['navigation'] = $this->GetParameter("navigation"); // true or false
if (empty($param['navigation'])) {
    $param['navigation'] = BAZ_AFFICHER_NAVIGATION;
}

/*
 * Zoom sur molette : true or false, par defaut false
 */
$param['zoom_molette'] = $this->GetParameter("zoommolette");
if (empty($param['zoom_molette'])) {
    $param['zoom_molette'] = BAZ_PERMETTRE_ZOOM_MOLETTE;
}



// Recuperation de tous les formulaires
$allforms = baz_valeurs_tous_les_formulaires();

// tableau des valeurs "facettables" avec leur nombres
$facettevalue = array();

// tableau qui contiendra les fiches
$fiches['fiches'] = array();
foreach ($tableau_resultat as $fiche) {
    $valeurs_fiche = json_decode($fiche['body'], true);
    if (TEMPLATES_DEFAULT_CHARSET != 'UTF-8') {
        $valeurs_fiche = array_map('utf8_decode', $valeurs_fiche);
    }
    $valeurs_fiche['html_data'] = getHtmlDataAttributes($valeurs_fiche);
    $valeurs_fiche['html'] = baz_voir_fiche(0, $valeurs_fiche);  //permet de voir la fiche

    if (!empty($correspondance)) {
        $tabcorrespondance = explode("=", trim($correspondance));
        if (isset($tabcorrespondance[0])) {
            if (isset($tabcorrespondance[1]) && isset($valeurs_fiche[$tabcorrespondance[1]])) {
                $valeurs_fiche[$tabcorrespondance[0]] = $valeurs_fiche[$tabcorrespondance[1]];
            } else {
                $valeurs_fiche[$tabcorrespondance[0]] = '';
            }
        } else {
            exit('<div class="alert alert-danger">action bazarliste : parametre correspondance mal rempli :
             il doit etre de la forme correspondance="identifiant_1=identifiant_2"</div>');
        }
    }
    $valeurs_fiche['datastr'] = getHtmlDataAttributes($valeurs_fiche);

    // on scanne tous les champs qui pourraient faire des filtres pour les facettes
    if (count($groups) > 0) {
        foreach ($valeurs_fiche as $key => $value) {
            if (!empty($value)) {
                $facetteasked = (isset($groups[0]) && $groups[0]=='all') || in_array($key, $groups);
                // champs génériques des métadonnées
                if (in_array($key, array('id_typeannonce', "createur")) && $facetteasked) {
                    if ($key == 'id_typeannonce') {
                        $value = $valeurs_fiche["id_typeannonce"].'|'.
                          $allforms[$valeurs_fiche["categorie_fiche"]][$valeurs_fiche["id_typeannonce"]]['bn_label_nature'];
                    }
                    if (isset($facettevalue[$key][$value])) {
                        $facettevalue[$key][$value]++;
                    } else {
                        $facettevalue[$key][$value] = 1;
                    }
                } else { // champs type liste ou checkbox
                    $templatef = $allforms[$valeurs_fiche["categorie_fiche"]][$valeurs_fiche["id_typeannonce"]]['template'];
                    if (is_array($templatef)) {
                        foreach ($templatef as $id => $val) {
                            if ($val[1] === $key || (isset($val[6]) && $val[0] . $val[1] . $val[6] === $key)) {
                                $islist = in_array(
                                    $templatef[$id][0],
                                    array('checkbox', 'liste', 'checkboxfiche', 'listefiche')
                                );
                                if ($islist && $facetteasked) {
                                    $tabval = explode(',', $value);
                                    foreach ($tabval as $val) {
                                        if (isset($facettevalue[$templatef[$id][1].'|'.$key][$val])) {
                                            $facettevalue[$templatef[$id][1].'|'.$key][$val]++;
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
    $fiches['fiches'][$valeurs_fiche['id_fiche']] = $valeurs_fiche;
}

usort($fiches['fiches'], 'champCompare');

// Limite le nombre de résultat au nombre de fiches demandées
if ($nb != '') {
    $fiches['fiches'] = array_slice($fiches['fiches'], 0, $nb);
}

//on recupere le nombre d'entrees avant pagination
$pagination = $this->GetParameter("pagination");
if (!empty($pagination)) {
    $fiches['info_res'] = '<div class="alert alert-info">'._t('BAZ_IL_Y_A');

    $nb_result = count($fiches['fiches']);

    if ($nb_result<=1) {
        $fiches['info_res'] .= $nb_result.' '._t('BAZ_FICHE').'</div>'."\n";
    } else {
        $fiches['info_res'] .= $nb_result.' '._t('BAZ_FICHES').'</div>'."\n";
    }
    // Mise en place du Pager
    require_once 'Pager/Pager.php';
    $params = array(
        'mode'       => BAZ_MODE_DIVISION,
        'perPage'    => $pagination,
        'delta'      => BAZ_DELTA,
        'httpMethod' => 'GET',
        'extraVars' => array_merge($_POST, $_GET),
        'altNext' => _t('BAZ_SUIVANT'),
        'altPrev' => _t('BAZ_PRECEDENT'),
        'nextImg' => _t('BAZ_SUIVANT'),
        'prevImg' => _t('BAZ_PRECEDENT'),
        'itemData'   => $fiches['fiches']
    );
    $pager = & Pager::factory($params);
    $fiches['fiches'] = $pager->getPageData();
    $fiches['pager_links'] = '<div class="bazar_numero">'.$pager->links.'</div>'."\n";
} else {
    $fiches['info_res'] = '';
    $fiches['pager_links'] = '';
}

// affichage des resultats
include_once 'tools/bazar/libs/squelettephp.class.php';
// On cherche un template personnalise dans le repertoire themes/tools/bazar/templates
$templatetoload = 'themes/tools/bazar/templates/' . $template;
if (!is_file($templatetoload)) {
    $templatetoload = 'tools/bazar/presentation/templates/' . $template;
}
$squelfacette = new SquelettePhp($templatetoload);
$fiches['param'] = $param;
$squelfacette->set($fiches);
$output = $squelfacette->analyser();

// affichage spécifique pour facette
if (count($facettevalue)>0) {
    // affichage des resultats et filtres
    $output = '<div class="facette-container row row-fluid">'."\n".
          '<div class="results col-xs-9 span9">
        <div class="alert alert-info">'._t('BAZ_IL_Y_A').'<span class="nb-results">'.count($fiches['fiches']).
        '</span> '._t('BAZ_FICHES_CORRESPONDANTES_FILTRES').'.</div>'."\n".
        $output."\n".'</div><!-- /.results.col-xs-9 -->';

    // colonne des filtres
    $output .= '<div class="filters col-xs-3 span3">'."\n";
    if (isset($facettevalue['id_typeannonce'])) {
        if (count($facettevalue['id_typeannonce'])>1) {
            $output .=  '<div class="filter-box panel panel-default" data-id="id_typeannonce">'."\n";
            $output .=  '<div class="panel-heading">'._t('BAZ_TYPE_FICHE').'</div>'."\n";
            $output .=  '<div class="panel-body">'."\n";
            foreach ($facettevalue['id_typeannonce'] as $id => $nb) {
                $infotypef = explode('|', $id);
                $output .=  '<div class="checkbox"><label>
                <input class="filter-checkbox" type="checkbox" name="id_typeannonce" 
                value="'.htmlspecialchars($infotypef[0]).'"> '.$infotypef[1].' (<span class="nb">'.$nb.'</span>)
                </label></div>'."\n";
            }
            $output .=  '</div></div><!-- /.filter-box -->'."\n";
        }
        unset($facettevalue['id_typeannonce']);
    }
    $i = 0;
    $first = true;
    foreach ($facettevalue as $key => $value) {
        if (count($facettevalue[$key])>1) {
            $tabkey = explode('|', $key);
            $list = baz_valeurs_liste($tabkey[0]);
            $output .=  '<div class="filter-box panel panel-default '.htmlspecialchars($tabkey[1]).
                '" data-id="'.htmlspecialchars($tabkey[1]).'">'."\n";
            if (isset($titles[$i]) && !empty($titles[$i])) {
                $titlefilterbox = $titles[$i];
            } else {
                $titlefilterbox = $list['titre_liste'];
            }
            $output .=  '<div class="panel-heading';
            if (!$first) {
                $output .= ' collapsed';
            }
            $output .= '" data-toggle="collapse" href="#collapse'.
                htmlspecialchars($tabkey[1]).'" >'.$titlefilterbox.'</div>'."\n";
            $output .= '<div id="collapse'.htmlspecialchars($tabkey[1]).'" class="panel-collapse';
            if ($first) {
                $output .= ' in';
            }
            $output .= ' collapse">'."\n";
            $output .= '<div class="panel-body">'."\n";
            foreach ($value as $val => $nb) {
                if (!empty($val)) {
                    $output .=  '<div class="checkbox"><label>
                    <input class="filter-checkbox" type="checkbox" name="'.htmlspecialchars($tabkey[1]).'" 
                    value="'.htmlspecialchars($val).'"> '.$list['label'][$val].' (<span class="nb">'.$nb.'</span>)
                    </label></div>'."\n";
                }
            }
            $output .=  '</div></div></div><!-- /.filter-box -->'."\n";
        }
        $i++;
        $first = false;
    }
    $output .= '</div><!-- /.filters.col-xs-3 -->'."\n";

    $output .= '</div><!-- /.row -->';
}

// affichage à l'écran
echo $output;
