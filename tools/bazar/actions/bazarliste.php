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
*/
// +------------------------------------------------------------------------------------------------------+
// |                                            ENTETE du PROGRAMME                                           |
// +------------------------------------------------------------------------------------------------------+
// test de sécurité pour vérifier si on passe par wiki
if (!defined("WIKINI_VERSION")) {
        die ("acc&egrave;s direct interdit");
}
// initialisation de la fonction de tri , inspiré par http://php.net/manual/fr/function.usort.php
if (!function_exists('champ_compare')) {
  // tri par ordre desire
  function champ_compare($a, $b) {
      if ($GLOBALS['ordre'] == 'desc') {
          return strnatcasecmp($b[$GLOBALS['champ']], $a[$GLOBALS['champ']]);
    } else {
         return strnatcasecmp($a[$GLOBALS['champ']], $b[$GLOBALS['champ']]);
    }
          
  }
}
//recuperation des parametres wikini
$categorie_nature = $this->GetParameter("categorienature"); // permet de reuperer la valeur passée en parametres de l'action ici {{bazarliste categorienature="actus"}} va mettre dans la variable $categorie_nature la valeur "actus"
if (empty($categorie_nature)) { // dans le cas ou il n'y a pas de valeur précisée, alors il les prend toutes
    $categorie_nature = 'toutes';
}
$id_typeannonce = $this->GetParameter("idtypeannonce");
if (empty($id_typeannonce)) {
    $id_typeannonce = 'toutes';
}
$GLOBALS['ordre'] = $this->GetParameter("ordre");
if (empty($GLOBALS['ordre'])) {
    $GLOBAL['ordre'] = 'asc';
}
$GLOBALS['champ']   = $this->GetParameter("champ");
if (empty($GLOBALS['champ'])) {
   $GLOBALS['champ'] = 'bf_titre';  // si pas de champ précisé, on triera par le titre
}
$template = $this->GetParameter("template");
if (empty($template)) {
    $template = 'liste_accordeon.tpl.html';
}
$nb = $this->GetParameter("nb");
if (empty($nb)) {
    $nb = '';
}
//on recupere les parameres pour une requete specifique
$query = $this->GetParameter("query");
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
$tableau_resultat = baz_requete_recherche_fiches($tabquery, 'alphabetique', $id_typeannonce, $categorie_nature, 1, '', $nb);
//on recupere le nombre d'entrees avant pagination
$pagination = $this->GetParameter("pagination");
if (!empty($pagination)) {
    $fiches['info_res'] = '<div class="info_box">'.BAZ_IL_Y_A;
    $nb_result = count($tableau_resultat);
    if ($nb_result<=1) {
        $fiches['info_res'] .= $nb_result.' '.BAZ_FICHE.'</div>'."\n";
    } else {
        $fiches['info_res'] .= $nb_result.' '.BAZ_FICHES.'</div>'."\n";
    }
    // Mise en place du Pager
    require_once 'Pager/Pager.php';
    $params = array(
        'mode'       => BAZ_MODE_DIVISION,
        'perPage'    => $pagination,
        'delta'      => BAZ_DELTA,
        'httpMethod' => 'GET',
        'extraVars' => array_merge($_POST, $_GET),
        'altNext' => BAZ_SUIVANT,
        'altPrev' => BAZ_PRECEDENT,
        'nextImg' => BAZ_SUIVANT,
        'prevImg' => BAZ_PRECEDENT,
        'itemData'   => $tableau_resultat
    );
    $pager = & Pager::factory($params);
    $tableau_resultat = $pager->getPageData();
    $fiches['pager_links'] = '<div class="bazar_numero">'.$pager->links.'</div>'."\n";
} else {
    $fiches['info_res'] = '';
    $fiches['pager_links'] = '';
}
$fiches['fiches'] = array();
foreach ($tableau_resultat as $fiche) {
    $valeurs_fiche = json_decode($fiche[0], true);  //json = norme d'ecriture utilisée pour les fiches bazar (en utf8)
    $valeurs_fiche = array_map('utf8_decode', $valeurs_fiche);
    $valeurs_fiche['html'] = baz_voir_fiche(0, $valeurs_fiche);  //permet de voir la fiche
    if (baz_a_le_droit('supp_fiche', $valeurs_fiche['createur'])) {  //lien de suppression visible pour le super admin
        $valeurs_fiche['lien_suppression'] = '<a class="BAZ_lien_supprimer" href="'.$this->href('deletepage', $valeurs_fiche['id_fiche']).'"></a>'."\n";
    }
    if (baz_a_le_droit('modif_fiche', $valeurs_fiche['createur'])) {
        $valeurs_fiche['lien_edition'] = '<a class="BAZ_lien_modifier" href="'.$this->href('edit', $valeurs_fiche['id_fiche']).'"></a>'."\n";
    }
    $valeurs_fiche['lien_voir_titre'] = '<a class="BAZ_lien_voir" href="'. $this->href('', $valeurs_fiche['id_fiche']) .'" title="Voir la fiche">'.$valeurs_fiche['bf_titre'].'</a>'."\n";
    $valeurs_fiche['lien_voir'] = '<a class="BAZ_lien_voir" href="'. $this->href('', $valeurs_fiche['id_fiche']) .'" title="Voir la fiche"></a>'."\n";
    $fiches['fiches'][] = $valeurs_fiche;  //tableau qui contient le contenu de touts les fiches
}
usort($fiches['fiches'], 'champ_compare');
include_once 'tools/bazar/libs/squelettephp.class.php';
$squelcomment = new SquelettePhp('tools/bazar/presentation/templates/'.$template);    //gere les templates
$squelcomment->set($fiches);   //on passe le tableau de fiches en parametres
echo $squelcomment->analyser(); // affiche les résultats
