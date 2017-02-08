<?php
/**
* bazarlistecategorie : programme affichant les fiches du bazar catégorisées par les champs liste
* sous forme de liste accordeon (ou autre template)
*
*
*@package Bazar
*
*@author        Florian SCHMITT <florian@outils-reseaux.org>
*@version       $Revision: 1.5 $ $Date: 2010/03/04 14:19:03 $
*
*/
// +------------------------------------------------------------------------------------------------------+
// |                                            ENTETE du PROGRAMME                                       |
// +------------------------------------------------------------------------------------------------------+

// test de sécurité pour vérifier si on passe par wiki
if (!defined("WIKINI_VERSION")) {
        die ("acc&egrave;s direct interdit");
}

// initialisation de la fonction de tri , inspiré par http://php.net/manual/fr/function.usort.php
if (!function_exists('champCompare')) {
    // tri par ordre desire
    function champCompare($a, $b)
    {
        if ($GLOBALS['ordre'] == 'desc') {
            return strnatcasecmp($b[$GLOBALS['champ']], $a[$GLOBALS['champ']]);
        } else {
            return strnatcasecmp($a[$GLOBALS['champ']], $b[$GLOBALS['champ']]);
        }
    }
}

//recuperation des parametres wikini
$categorie_nature = $this->GetParameter("categorienature");

// permet de reuperer la valeur passée en parametres de l'action ici {{bazarliste categorienature="actus"}}
// va mettre dans la variable $categorie_nature la valeur "actus"
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

$template = $this->GetParameter("template");
if (empty($template)) {
    $template = BAZ_TEMPLATE_LISTE_DEFAUT;
}

// identifiant de la base de donnée pour la liste
$id = $this->GetParameter("id");
if (empty($id)) {
    exit('<div class="alert alert-danger">Error action bazarlistecategorie: parameter "id" missing.</div>');
} else {
    $GLOBALS['champ'] = $id;
}

// NomWiki de la liste
$list = $this->GetParameter("list");
if (empty($list)) {
    echo '<div class="alert alert-danger">Error action bazarlistecategorie: parameter "list" missing.</div>';
} else {
    // on recupere les parameres pour une requete specifique
    if (isset($_GET['query'])) {
        $query = $_GET['query'];
    } else {
        $query = '';
    }
    unset($_GET['query']);
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
    $tableau_resultat = baz_requete_recherche_fiches(
        $tabquery,
        'alphabetique',
        $id_typeannonce,
        $categorie_nature,
        1,
        '',
        ''
    );
    $fiches['info_res'] = '';
    $fiches['pager_links'] = '';
    $fiches['fiches'] = array();
    foreach ($tableau_resultat as $fiche) {
        //json = norme d'ecriture utilisée pour les fiches bazar (en utf8)
        $valeurs_fiche = json_decode($fiche['body'], true);
        if (YW_CHARSET != 'UTF-8') {
            $valeurs_fiche = array_map('utf8_decode', $valeurs_fiche);
        }
        // pour les checkbox, on crée une fiche par case cochée pour apparaitre é différents endroits
        $tabcheckbox = explode(',', $valeurs_fiche[$id]);
        foreach ($tabcheckbox as $value) {
            // on sauve les multiples valeurs pour les retablir é l'affichage
            $multiplecheckbox[$valeurs_fiche['id_fiche']] = $valeurs_fiche[$id];
            $valeurs_fiche[$id] = $value;

            // permet de voir la fiche
            $valeurs_fiche['html'] = baz_voir_fiche(0, $valeurs_fiche);
            // lien de suppression visible pour le super admin
            if (baz_a_le_droit('supp_fiche', $valeurs_fiche['createur'])) {
                $valeurs_fiche['lien_suppression'] = '<a class="BAZ_lien_supprimer" href="'.
                    $this->href('deletepage', $valeurs_fiche['id_fiche']).'"></a>'."\n";
            }
            if (baz_a_le_droit('modif_fiche', $valeurs_fiche['createur'])) {
                $valeurs_fiche['lien_edition'] = '<a class="BAZ_lien_modifier" href="'.
                    $this->href('edit', $valeurs_fiche['id_fiche']).'"></a>'."\n";
            }
            $valeurs_fiche['lien_voir_titre'] = '<a class="BAZ_lien_modifier" href="'.
                $this->href('', $valeurs_fiche['id_fiche']) .'">'.$valeurs_fiche['bf_titre'].'</a>'."\n";
            $valeurs_fiche['lien_voir'] = '<a class="BAZ_lien_modifier" href="'.
                $this->href('', $valeurs_fiche['id_fiche']) .'"></a>'."\n";
            $fiches['fiches'][] = $valeurs_fiche;
        }
    }
    // trie par liste choisie
    usort($fiches['fiches'], 'champCompare');

    // preparation du template
    include_once 'tools/libs/squelettephp.class.php';
    // On cherche un template personnalise dans le repertoire themes/tools/bazar/templates
    $templatetoload = 'themes/tools/bazar/templates/'.$template;
    if (!is_file($templatetoload)) {
        $templatetoload = 'tools/bazar/presentation/templates/'.$template;
    }

    $listvalues = baz_valeurs_liste($list);
    $currentlabel = '';
    $fichescat = '';
    $output = '';
    $first = true;
    foreach ($fiches['fiches'] as $fiche) {
        $fiche['multipleid'] = htmlspecialchars(trim(str_replace('/', '', $fiche[$id])).$fiche['id_fiche']);
        if ($currentlabel !== $fiche[$id]) {
            if (!$first) {
                if (is_array($fichescat) && count($fichescat)>0) {
                    $squel = new SquelettePhp($templatetoload);
                    $squel->set($fichescat); // on passe le tableau de fiches en parametres
                    $output .= $squel->analyser(); // affiche les résultats
                }
                // it's not the first time in the loop so we must close previously opened div
                $output .=  '</div>'."\n";
                $fichescat = '';
            } else {
                $first = false;
            }
            $output .=  '<h3 class="collapsed yeswiki-list-category" '
                .'data-target="#collapse_'.htmlspecialchars(trim(str_replace('/', '', $fiche[$id])))
                .'" data-toggle="collapse"><i class="glyphicon glyphicon-chevron-right"></i> '
                .$listvalues['label'][$fiche[$id]].'</h3>
                <div id="collapse_'.htmlspecialchars(trim(str_replace('/', '', $fiche[$id]))).'" class="collapse">';
        }
        $currentlabel = $fiche[$id];
        // on rétablit les valeurs multiples
        if (isset($multiplecheckbox[$fiche['id_fiche']])) {
            $fiche[$id] = $multiplecheckbox[$fiche['id_fiche']];
        }
        $fichescat['fiches'][] = $fiche;
    }
    // last results
    if (is_array($fichescat) && count($fichescat)>0) {
        $squel = new SquelettePhp($templatetoload);
        $squel->set($fichescat); // on passe le tableau de fiches en parametres
        $output .= $squel->analyser(); // affiche les résultats
    }
    // it's not the first time in the loop so we must close previously opened div
    $output .=  '</div>'."\n";
    echo $output;

    $$_GET['query'] = $query;

}
