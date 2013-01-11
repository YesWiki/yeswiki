<?php

if (!defined("WIKINI_VERSION"))
{
    die ("acc&egrave;s direct interdit");
}


$template = $this->GetParameter("template");
if (empty($template)) {
    $template = 'liste_accordeon.tpl.html';
}

$id_typeannonce = $this->GetParameter("idtypeannonce");
if (empty($id_typeannonce)) {
    $id_typeannonce = 'toutes';
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
        $grouplist[$group]=liste_to_array($group); // On charge les valeurs de la liste
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




// Interrogation de toutes les fiches 
$tabquery=array();
$tableau_resultat1 = baz_requete_recherche_fiches($tabquery, $ordre, $id_typeannonce, $categorie_nature); // institution partner
$fiche_resultat=array();
$comptage_groupe=array();




foreach ($tableau_resultat1 as $fiche) {
    $valeurs_fiche = json_decode($fiche[0], true);
    $valeurs_fiche = array_map('utf8_decode', $valeurs_fiche);
    $valeurs_fiche['html'] = baz_voir_fiche(0, $valeurs_fiche);

    $fiche_resultat[]= $valeurs_fiche;


    // Stockage pour comptage des groupes
    foreach ($groups as $group) {
        if (!$grouplist[$group]) {
            $comptage_groupe[$group][$valeurs_fiche['id_fiche']]=ucfirst(strtolower($valeurs_fiche[$group]));
        }
        else { // C'est une  liste !
            $index_liste=explode(",",$valeurs_fiche['checkbox'.$group]); 
            if (empty($index_liste[0])) {
                $index_liste=explode(",",$valeurs_fiche['liste'.$group]);
            }
            foreach ($index_liste as $element_liste) { 
                $comptage_groupe[$group][$grouplist[$group][$element_liste]][$valeurs_fiche['id_fiche']]=$grouplist[$group][$element_liste];
            }
        }
    }
}

// Somme par groupe
$somme_par_groupe=array();
foreach ($groups as $group) {
    if (!$grouplist[$group]) {
        $somme_par_groupe[$group]=ArrayGroupByCount($comptage_groupe[$group],'asc');
    }
    else { // C'est une liste !
        foreach ($grouplist[$group] as $valeur_grouplist) { // on compte tous les elements de la liste
            $somme_par_groupe[$group][$valeur_grouplist]=ArrayGroupByCount($comptage_groupe[$group][$valeur_grouplist],'asc');
        }

    }
}

include_once('tools/facette/libs/squelettephp.class.php');
$squelcomment = new SquelettePhp('tools/facette/presentation/squelettes/'.$template);

// Affiche Selecteurs


$facettes=array();
$checkboxes=array(); 
$titles_html=array(); 
$facettes['open']=$this->Formopen('','','get');
foreach ($groups as $kg=>$group) {
    $checkboxes[$group][]=array('title'=>$titles[$kg]); 
    if (!$grouplist[$group]) {
        foreach ($somme_par_groupe[$group] as $key_somme_par_groupe=>$valeur_somme_par_groupe) {
            if ($key_somme_par_groupe!='') {
                if ((in_arrayi($key_somme_par_groupe,$selections[$group]))) {
                    $checkboxes[$group][]=array('content'=>'<input checked="yes"  name="'.$group.'[]" value="'.$key_somme_par_groupe .'" type="checkbox"/> '.$key_somme_par_groupe." (".$valeur_somme_par_groupe.")".'</>');
                }
                else {
                    $checkboxes[$group][]=array('content'=>'<input name="'.$group.'[]" value="'.$key_somme_par_groupe .'" type="checkbox"/> '.$key_somme_par_groupe." (".$valeur_somme_par_groupe.")".'</>');
                }
            }
        }
    }
    else {
        foreach ($grouplist[$group] as $key_group=>$valeur_grouplist) {
            foreach ($somme_par_groupe[$group][$valeur_grouplist] as $key_somme_par_groupe=>$valeur_somme_par_groupe) {
                if ($key_somme_par_groupe!='') {
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
                    $valeurs_fiche['lien_voir_titre'] = '<a class="BAZ_lien_voir" href="'. $this->href('', $valeurs_fiche['id_fiche']) .'" title="Voir la fiche">'.$valeurs_fiche['bf_titre'].'</a>'."\n";
                    $valeurs_fiche['lien_voir'] = '<a class="BAZ_lien_voir" href="'. $this->href('', $valeurs_fiche['id_fiche']) .'" title="Voir la fiche"></a>'."\n";
                    $facettes['fiches'][$valeurs_fiche['id_fiche']] = $valeurs_fiche;

                }
            }
            else { // C'est une liste
                $index_liste=explode(",",$valeurs_fiche['checkbox'.$key_selection]);
                if (empty($index_liste[0])) {
                    $index_liste=explode(",",$valeurs_fiche['liste'.$key_selection]);
                }
                foreach ($index_liste as $key_liste=>$element_liste) {
                    if ((in_arrayi($element_liste,$valeur_selection))) {
                        $valeurs_fiche['lien_voir_titre'] = '<a class="BAZ_lien_voir" href="'. $this->href('', $valeurs_fiche['id_fiche']) .'" title="Voir la fiche">'.$valeurs_fiche['bf_titre'].'</a>'."\n";
                        $valeurs_fiche['lien_voir'] = '<a class="BAZ_lien_voir" href="'. $this->href('', $valeurs_fiche['id_fiche']) .'" title="Voir la fiche"></a>'."\n";
                        $facettes['fiches'][$valeurs_fiche['id_fiche']] = $valeurs_fiche;
                        $facettes['html'] = baz_voir_fiche(0, $valeurs_fiche);
                    }

                }

            }
        }
    }
    else { // Pas de selections : on affiche toutes les fiches TODO : pagination
        $valeurs_fiche['lien_voir_titre'] = '<a class="BAZ_lien_voir" href="'. $this->href('', $valeurs_fiche['id_fiche']) .'" title="Voir la fiche">'.$valeurs_fiche['bf_titre'].'</a>'."\n";
        $valeurs_fiche['lien_voir'] = '<a class="BAZ_lien_voir" href="'. $this->href('', $valeurs_fiche['id_fiche']) .'" title="Voir la fiche"></a>'."\n";
        $facettes['fiches'][$valeurs_fiche['id_fiche']] = $valeurs_fiche;
        $facettes['html'] = baz_voir_fiche(0, $valeurs_fiche);

    }
}


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
        //on vérifie que la page en question est bien une page wiki
        if ($GLOBALS['wiki']->GetTripleValue($idliste, 'http://outils-reseaux.org/_vocabulary/type', '', '') == 'liste') {

            $valjson = $GLOBALS['wiki']->LoadPage($idliste);
        $valeurs_fiche = json_decode($valjson["body"], true);
        $valeurs_fiche['label'] = array_map('utf8_decode', $valeurs_fiche['label']);
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


