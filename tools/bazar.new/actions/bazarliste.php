<?php
/**
* bazarliste : programme affichant les fiches du bazar sous forme de liste accordeon
*
*
*@package Bazar
//Auteur original :
*@author        Florian SCHMITT <florian@outils-reseaux.org>
*@version       $Revision: 1.5 $ $Date: 2010/03/04 14:19:03 $
// +------------------------------------------------------------------------------------------------------+
*/


// +------------------------------------------------------------------------------------------------------+
// |                                            ENTETE du PROGRAMME                                       |
// +------------------------------------------------------------------------------------------------------+


if (!defined("WIKINI_VERSION")) {
        die ("acc&egrave;s direct interdit");
}

//récupération des paramètres wikini

$categorie_nature = $this->GetParameter("categorienature");
if (empty($categorie_nature)) {	
	$categorie_nature = 'toutes';
}

$id_typeannonce = $this->GetParameter("idtypeannonce");
if (empty($id_typeannonce)) {
	$id_typeannonce = 'toutes';
}

$ordre = $this->GetParameter("ordre");
if (empty($ordre)) {
	$ordre = 'alphabetique';
}

$template = $this->GetParameter("template");
if (empty($template)) {
	$template = 'liste_accordeon.tpl.html';
}

//on récupère les paramètres pour une requête spécifique
$query = $this->GetParameter("query");
if (!empty($query)) {
	$tabquery = array();
	$tableau = array();
	$tab = explode('|', $query);
	foreach ($tab as $req)
	{
		$tabdecoup = explode('=', $req, 2);
		$tableau[$tabdecoup[0]] = trim($tabdecoup[1]);
	}
	$tabquery = array_merge($tabquery, $tableau);
}
else
{
	$tabquery = '';
}

$tableau_resultat = baz_requete_recherche_fiches($tabquery, $ordre, $id_typeannonce, $categorie_nature);

//on récupère le nombre d'entrées avant pagination
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
	$valeurs_fiche = json_decode($fiche[0], true);
	$valeurs_fiche = array_map('utf8_decode', $valeurs_fiche);
	$valeurs_fiche['html'] = baz_voir_fiche(0, $valeurs_fiche);

	if (baz_a_le_droit('saisir_fiche', $valeurs_fiche['createur'])) {
		$valeurs_fiche['lien_suppression'] = '<a class="BAZ_lien_supprimer" href="'.$this->href('deletepage', $valeurs_fiche['id_fiche']).'"></a>'."\n";
		$valeurs_fiche['lien_edition'] = '<a class="BAZ_lien_modifier" href="'.$this->href('edit', $valeurs_fiche['id_fiche']).'"></a>'."\n";
	}
	$valeurs_fiche['lien_voir_titre'] = '<a class="BAZ_lien_voir" href="'. $this->href('', $valeurs_fiche['id_fiche']) .'" title="Voir la fiche">'.$valeurs_fiche['bf_titre'].'</a>'."\n";
	$valeurs_fiche['lien_voir'] = '<a class="BAZ_lien_voir" href="'. $this->href('', $valeurs_fiche['id_fiche']) .'" title="Voir la fiche"></a>'."\n";
	$fiches['fiches'][] = $valeurs_fiche;

	//réinitialisation de l'url
	$GLOBALS['_BAZAR_']['url']->removeQueryString(BAZ_VARIABLE_VOIR);
	$GLOBALS['_BAZAR_']['url']->removeQueryString(BAZ_VARIABLE_ACTION);
}
include_once('tools/bazar/libs/squelettephp.class.php');
$squelcomment = new SquelettePhp('tools/bazar/presentation/squelettes/'.$template);
$squelcomment->set($fiches);
echo $squelcomment->analyser();

?>
