<?php

if (!defined("WIKINI_VERSION"))
{
    die ("acc&egrave;s direct interdit");
}


$template = $this->GetParameter("template");
if (empty($template)) {
    $template = 'liste_accordeon_facette.tpl.html';
}

$id_typeannonce = $this->GetParameter("idtypeannonce");
if (empty($id_typeannonce)) {
    $id_typeannonce = array();
}
else {
    $id_typeannonce=explode(",",$id_typeannonce);
}

$facettejointure = $this->GetParameter("jointure");
if (empty($facettejointure)) {
    $facettejointure = 'OR';
}

/*
* ordre : ordre affichage detail des points
*
*/

$ordre = $this->GetParameter("ordre");
if (empty($ordre)) {
    $ordre = 'alphabetique';
}


$latitude = $this->GetParameter("lat");
if (empty($latitude)) {
    $latitude = BAZ_GOOGLE_CENTRE_LAT;
}

/*
* lon : longitude point central en degres WGS84 (exemple : 3.42313) , sinon parametre par defaut
* @atester
*
*/

$longitude = $this->GetParameter("lon");
if (empty($longitude)) {
    $longitude = BAZ_GOOGLE_CENTRE_LON;
}


$zoom = $this->GetParameter("zoom");
if (empty($zoom)) {
    $zoom = BAZ_GOOGLE_ALTITUDE;
}



$pagination = $this->GetParameter("pagination");


// Interroger tout
// Compter les groupes
// Afficher les groupes
// Afficher le contenu selon le filtrage en cours

$groups = $this->GetParameter("groups"); // parametre groups="bf_ce_titre,bf_ce_pays,etc."
if (empty($groups)) {
    $groups = array();
}
else {
    $groups=explode(",",$groups);
}


$titles = $this->GetParameter("titles"); // parametre titles="bf_ce_titre,bf_ce_pays,etc."

if (empty($titles)) {
    $titles = array();
}
else {
    $titles=explode(",",$titles);
}



// Detection des parametres de type liste
$grouplist=array();
foreach ($groups as $group) {
    if (is_liste($group)) {
        $groupfix=preg_replace('/\*/', '', $group); // liste utilise plusieurs fois
        $grouplist[$groupfix]=liste_to_array($group); // On charge les valeurs de la liste
    }
    else {
        $grouplist[$group]=false;
    }
}

// Parsing parametre GET issue d'une selection precedente
$selections=array(); // Valeur selectionnes (tableau dans l'url)  pour restriction affichage

foreach ($groups as $group) {
    if (isset($_GET[$group])) {
        $selections [$group]= $_GET[$group];
    }
}


// Recuperation de tous les formulaires
$tous_les_formulaires=baz_valeurs_formulaire();

$tabquery=array();
$tableau_resultat=array();
$jointure=array();

foreach ($id_typeannonce as $annonce) {
    $tableau_resultat = array_merge($tableau_resultat, baz_requete_recherche_fiches($tabquery, $ordre, $annonce, $categorie_nature, 1, '', '', true, '', $facettejointure));
 /*   // Detection jointure avec autre fiche
     $val_formulaire = baz_valeurs_formulaire($annonce);
     $tableau = formulaire_valeurs_template_champs($val_formulaire['bn_template']);
     foreach ($tableau as $ligne) {
        if ($ligne[0]=="listefiche") { // jointure
            $jointure[$annonce]=$ligne[1]; // il y a une fiche liee
        }

    }
*/
     // Detection autre fiche contenant une reference à cette fiche.
    foreach ($tous_les_formulaires as $numformulaire => $val_formulaire) {
        $tableau = formulaire_valeurs_template_champs($val_formulaire['bn_template']);
        foreach ($tableau as $ligne) {
           if ($ligne[0]=="listefiche") { // jointure
                if ($ligne[1]==$annonce) {
                   $jointure[$numformulaire]=$ligne[1]; // numero de fiche liee
                }
            }
        }
    }
}

// Recherche de l'ensemble des fiches liee supplementaires

$tableau_resultat_lie=array();
foreach ($jointure as $cible=>$origine) {
    $tableau_resultat_lie[$origine][$cible]=baz_requete_recherche_fiches($tabquery, $ordre, $cible, '');
}



$fiche_resultat=array();
$comptage_groupe=array();




foreach ($tableau_resultat as $fiche) {


    $valeurs_fiche = json_decode($fiche["body"], true);
    $valeurs_fiche = _convert($valeurs_fiche, 'UTF-8');
    $valeurs_fiche['html'] = baz_voir_fiche(0, $valeurs_fiche);




// Recherche des fiches liees supplementaires :

    if (isset($tableau_resultat_lie[$valeurs_fiche['id_typeannonce']])) {

        //print $valeurs_fiche['listefiche'.$jointure[$valeurs_fiche['id_typeannonce']]]; // clef listefiche+idtypannonce cible

        foreach ($tableau_resultat_lie[$valeurs_fiche['id_typeannonce']] as $formulaire_lies) {

            foreach ($formulaire_lies as $fiche_lies) {

             $valeurs_fiche_liee = json_decode($fiche_lies["body"], true);
             $valeurs_fiche_liee = array_map('utf8_decode', $valeurs_fiche_liee);
             $valeurs_fiche_liee['html'] = baz_voir_fiche(0, $valeurs_fiche_liee);
             //$valeurs_fiche_liee['bf_titre'] = $valeurs_fiche_liee['bf_titre'];



             if ($valeurs_fiche_liee['listefiche'.$valeurs_fiche['id_typeannonce']]==$valeurs_fiche['id_fiche']) { // clef  : listefiche+idtypannonce cible
                    $valeurs_fiche['ficheliees'][$valeurs_fiche_liee['id_fiche']]=$valeurs_fiche_liee;
             }
        }
        }

    }


    $fiche_resultat[]= $valeurs_fiche;



    // Stockage pour comptage des groupes
    foreach ($groups as $group) {
        $group=preg_replace('/\*/', '', $group); // liste utilise plusieurs fois
        if (!$grouplist[$group]) {
            $comptage_groupe[$group][$valeurs_fiche['id_fiche']]=ucfirst(strtolower($valeurs_fiche[$group]));
            if (isset($valeurs_fiche['ficheliees'])) {
                foreach ($valeurs_fiche['ficheliees'] as $valeurs_fiche_liee) {
                    $comptage_groupe[$group][$valeurs_fiche_liee['id_fiche']]=ucfirst(strtolower($valeurs_fiche_liee[$group]));
                }
            }
        }
        else { // C'est une  liste !
            $index_liste=explode(",",$valeurs_fiche['checkbox'.$group]);
            if (empty($index_liste[0])) {
                $index_liste=explode(",",$valeurs_fiche['liste'.$group]);
            }
            foreach ($index_liste as $element_liste) {
                $comptage_groupe[$group][$grouplist[$group][$element_liste]][$valeurs_fiche['id_fiche']]=$grouplist[$group][$element_liste];
            }
            if (isset($valeurs_fiche['ficheliees'])) {
                foreach ($valeurs_fiche['ficheliees'] as $valeurs_fiche_liee) {
                    $index_liste=explode(",",$valeurs_fiche_liee['checkbox'.$group]);
                    if (empty($index_liste[0])) {
                        $index_liste=explode(",",$valeurs_fiche_liee['liste'.$group]);
                    }
                    foreach ($index_liste as $element_liste) {
                            $comptage_groupe[$group][$grouplist[$group][$element_liste]][$valeurs_fiche_liee['id_fiche']]=$grouplist[$group][$element_liste];
                    }
                }

            }
        }
    }
}


// Somme par groupe
$somme_par_groupe=array();
foreach ($groups as $group) {
    $group=preg_replace('/\*/', '', $group); // liste utilise plusieurs fois
    if (!$grouplist[$group]) {
        $somme_par_groupe[$group]=ArrayGroupByCount($comptage_groupe[$group]);
    }
    else { // C'est une liste !
        foreach ($grouplist[$group] as $valeur_grouplist) { // on compte tous les elements de la liste
            $somme_par_groupe[$group][$valeur_grouplist]=ArrayGroupByCount($comptage_groupe[$group][$valeur_grouplist]);
        }

    }
}

include_once 'tools/libs/squelettephp.class.php';
// On cherche un template personnalise dans le repertoire themes/tools/bazar/templates

$templatetoload='themes/tools/bazar/templates/'.$template;

if (!is_file($templatetoload)) {
    $templatetoload='tools/bazar/presentation/templates/'.$template;
}

$squelcomment = new SquelettePhp($templatetoload);

// Affiche Selecteurs


$facettes=array();
$checkboxes=array();
$titles_html=array();
$facettes['open']=$this->Formopen('','','get');
foreach ($groups as $kg=>$group) {
    $group=preg_replace('/\*/', '', $group); // liste utilise plusieurs fois
    $checkboxes[$group][]=array('title'=>$titles[$kg]);
    if (!$grouplist[$group]) {
        foreach ($somme_par_groupe[$group] as $key_somme_par_groupe=>$valeur_somme_par_groupe) {
            if ($key_somme_par_groupe!='') {
                $facettes['filters'][$group][$key_somme_par_groupe]=$valeur_somme_par_groupe;
                if ((in_arrayi($key_somme_par_groupe,$selections[$group]))) {
                    $checkboxes[$group][]=array('content'=>'<input checked="yes"  name="'.$group.'[]" value="'.$key_somme_par_groupe .'" type="checkbox"/> '.$key_somme_par_groupe." (".$valeur_somme_par_groupe.")".'<br />');
                }
                else {
                    $checkboxes[$group][]=array('content'=>'<input name="'.$group.'[]" value="'.$key_somme_par_groupe .'" type="checkbox"/> '.$key_somme_par_groupe." (".$valeur_somme_par_groupe.")".'<br />');
                }
            }
        }
    }
    else {
        foreach ($grouplist[$group] as $key_group=>$valeur_grouplist) {
            foreach ($somme_par_groupe[$group][$valeur_grouplist] as $key_somme_par_groupe=>$valeur_somme_par_groupe) {
                if ($key_somme_par_groupe!='') {
                    $facettes['filters'][$group][$key_somme_par_groupe]=$valeur_somme_par_groupe;
                    if ((in_arrayi($key_group,$selections[$group]))) {
                        $checkboxes[$group][]=array('content'=>'<input checked="yes" name="'.$group.'[]" value="'.$key_group .'" type="checkbox"/> '.$key_somme_par_groupe." (".$valeur_somme_par_groupe.")".'</>');
                    }
                    else {
                        $checkboxes[$group][]=array('content'=> '<input  name="'.$group.'[]" value="'.$key_group .'" type="checkbox"/> '.$key_somme_par_groupe." (".$valeur_somme_par_groupe.")".'</>');
                    }
                }
            }
        }

    }
}



$facettes['titles']= $titles;
$facettes['groups']= $groups;
$facettes['checkboxes']= $checkboxes;
$facettes['close']=$this->Formclose();

//Resultats

// Affichage resultats
foreach ($fiche_resultat as $valeurs_fiche) {
    // On n'affiche que les valeurs selectionnees
    if (count($selections)>0) {
        foreach ($selections as $key_selection=>$valeur_selection) {
            if (!$grouplist[$key_selection]) {
                if ((in_arrayi($valeurs_fiche[$key_selection],$valeur_selection))) {
                    $valeurs_fiche['lien_voir_titre'] = '<a class="BAZ_lien_modifier" href="'. $this->href('', $valeurs_fiche['id_fiche']) .'" title="Voir la fiche">'.$valeurs_fiche['bf_titre'].'</a>'."\n";
                    $valeurs_fiche['lien_voir'] = '<a class="BAZ_lien_modifier" href="'. $this->href('', $valeurs_fiche['id_fiche']) .'" title="Voir la fiche"></a>'."\n";
                    $facettes['fiches'][$valeurs_fiche['id_fiche']] = $valeurs_fiche;

                }
                if (isset($valeurs_fiche['ficheliees'])) {
                foreach ($valeurs_fiche['ficheliees'] as $valeurs_fiche_liee) {
                    $facettes['fiches'][$valeurs_fiche['id_fiche']]['ficheliees'][$valeurs_fiche_liee['id_fiche']]= $valeurs_fiche_liee;
                }
            }
            }
            else { // C'est une liste
                $index_liste=explode(",",$valeurs_fiche['checkbox'.$key_selection]);
                if (empty($index_liste[0])) {
                    $index_liste=explode(",",$valeurs_fiche['liste'.$key_selection]);
                }
                foreach ($index_liste as $key_liste=>$element_liste) {
                    if ((in_arrayi($element_liste,$valeur_selection))) {
                        $valeurs_fiche['lien_voir_titre'] = '<a class="BAZ_lien_modifier" href="'. $this->href('', $valeurs_fiche['id_fiche']) .'" title="Voir la fiche">'.$valeurs_fiche['bf_titre'].'</a>'."\n";
                        $valeurs_fiche['lien_voir'] = '<a class="BAZ_lien_modifier" href="'. $this->href('', $valeurs_fiche['id_fiche']) .'" title="Voir la fiche"></a>'."\n";
                        $facettes['fiches'][$valeurs_fiche['id_fiche']] = $valeurs_fiche;
                        $facettes['html'] = baz_voir_fiche(0, $valeurs_fiche);
                    }

                }
                if (isset($valeurs_fiche['ficheliees'])) {
                    foreach ($valeurs_fiche['ficheliees'] as $valeurs_fiche_liee) {
                        $index_liste=explode(",",$valeurs_fiche_liee['checkbox'.$key_selection]);
                        if (empty($index_liste[0])) {
                            $index_liste=explode(",",$valeurs_fiche_liee['liste'.$key_selection]);
                        }
                        foreach ($index_liste as $key_liste=>$element_liste) {
                            if ((in_arrayi($element_liste,$valeur_selection))) {
                                $facettes['fiches'][$valeurs_fiche['id_fiche']]['ficheliees'][$valeurs_fiche_liee['id_fiche']] = $valeurs_fiche_liee;
                            }

                        }


                    }
                }


            }
        }
    }
    else { // Pas de selections : on affiche toutes les fiches TODO : pagination
        $valeurs_fiche['lien_voir_titre'] = '<a class="BAZ_lien_modifier" href="'. $this->href('', $valeurs_fiche['id_fiche']) .'" title="Voir la fiche">'.$valeurs_fiche['bf_titre'].'</a>'."\n";
        $valeurs_fiche['lien_voir'] = '<a class="BAZ_lien_modifier" href="'. $this->href('', $valeurs_fiche['id_fiche']) .'" title="Voir la fiche"></a>'."\n";
        $valeurs_fiche['categorie']="";

        // On renseigne les categories pour de l'eventuel Facet Javascript.
        foreach ($groups as $group) {
            $group=preg_replace('/\*/', '', $group); // liste utilise plusieurs fois
            if (!$grouplist[$group]) {
                // string compatible css
                $valeurs_fiche['categorie']=$valeurs_fiche['categorie']." ".trim(preg_replace('/\W+/','',strtolower(strip_tags($valeurs_fiche[$group]))));

                if (isset($valeurs_fiche['ficheliees'])) {
                    foreach ($valeurs_fiche['ficheliees'] as $valeurs_fiche_liee) {
                        $valeurs_fiche['categorie']=$valeurs_fiche['categorie']." ".trim(preg_replace('/\W+/','',strtolower(strip_tags($valeurs_fiche_liee[$group]))));
                    }
                }
            }
            else { // C'est une  liste !
                $index_liste=explode(",",$valeurs_fiche['checkbox'.$group]);
                if (empty($index_liste[0])) {
                    $index_liste=explode(",",$valeurs_fiche['liste'.$group]);
                }
                foreach ($index_liste as $element_liste) {
                    // string compatible css
                    $valeurs_fiche['categorie']=$valeurs_fiche['categorie']." ".trim(preg_replace('/\W+/','',strtolower(strip_tags($grouplist[$group][$element_liste]))));
                }
                if (isset($valeurs_fiche['ficheliees'])) {
                    foreach ($valeurs_fiche['ficheliees'] as $valeurs_fiche_liee) {
                        $index_liste=explode(",",$valeurs_fiche_liee['checkbox'.$group]);
                        if (empty($index_liste[0])) {
                            $index_liste=explode(",",$valeurs_fiche_liee['liste'.$group]);
                        }
                        foreach ($index_liste as $element_liste) {
                            // string compatible css
                            $valeurs_fiche['categorie']=$valeurs_fiche['categorie']." ".trim(preg_replace('/\W+/','',strtolower(strip_tags($grouplist[$group][$element_liste]))));
                        }
                    }
                }


            }
        }

        $valeurs_fiche['categorie']=trim(preg_replace('/[ ][ ]*/', ' ', $valeurs_fiche['categorie']));

        $facettes['fiches'][$valeurs_fiche['id_fiche']] = $valeurs_fiche;
        $facettes['html'] = baz_voir_fiche(0, $valeurs_fiche);


    }
}

//print_r($facettes);

if (!empty($pagination)) {
    $facettes['info_res'] = '<div class="info_box">'.'Resultat : ';

    $nb_result = count($facettes['fiches']);
    if ($nb_result<=1) {
        $facettes['info_res'] .= $nb_result.' fiche '."</div>\n";
    } else {
        $facettes['info_res'] .= $nb_result.' fiches '."</div>\n";
    }

    // Mise en place du Pager
    require_once 'Pager/Pager.php';
    $params = array(
            'mode'       => 'Jumping',
            'perPage'    => $pagination,
            'delta'      => 12,
            'httpMethod' => 'GET',
            'extraVars' => array_merge($_POST, $_GET),
            'altNext' => '>',
            'altPrev' => '<',
            'nextImg' => '>',
            'prevImg' => '<',
            'itemData'   => $facettes['fiches']
            );
    $pager = & Pager::factory($params);
    $facettes['fiches'] = $pager->getPageData();
    $facettes['pager_links'] = '<div class="bazar_numero">'.$pager->links.'</div>'."\n";
} else {
    $facettes['info_res'] = '';
    $facettes['pager_links'] = '';
}


$squelcomment->set($facettes);
echo $squelcomment->analyser();



function ArrayGroupByCount($_array, $sort = false) {
    $count_array = array();

    if (is_array($_array)) {
        foreach (array_unique($_array) as $value) {
            $count = 0;

            foreach ($_array as $element) {
                if ($element == $value)
                    $count++;
            }

            $count_array[$value] = $count;
        }

        if ( $sort == 'desc' )
            arsort($count_array);
        elseif ( $sort == 'asc' )
            asort($count_array);

    }
    return $count_array;
}

// inarray insensitive
function in_arrayi($needle, $haystack) {
    if (is_array($haystack)) {
        return in_array(strtolower($needle), array_map('strtolower', $haystack));
    }
}





function is_liste($idliste = '') {
    if ($idliste != '') {
        list($idliste,$suffixe)=explode( '*', $idliste);


        if ($GLOBALS['wiki']->GetTripleValue($idliste, 'http://outils-reseaux.org/_vocabulary/type', '', '') == 'liste') {
            return true;
    }
        else {
            return false;
        }
    }
    else return false;
}



function liste_to_array($idliste = '') {
    if ($idliste != '') {
        list($idliste,$suffixe)=explode( '*', $idliste);
        //on vérifie que la page en question est bien une page wiki
        if ($GLOBALS['wiki']->GetTripleValue($idliste, 'http://outils-reseaux.org/_vocabulary/type', '', '') == 'liste') {

        $valjson = $GLOBALS['wiki']->LoadPage($idliste);
        $valeurs_fiche = json_decode($valjson["body"], true);
        $valeurs_fiche['label'] = _convert($valeurs_fiche['label'], 'UTF-8');
        return $valeurs_fiche['label'];
    }
        else {
            return false;
        }
    }
    else {
        return false;
    }
}
