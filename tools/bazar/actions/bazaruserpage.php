<?php
/**
* bazaruserpage : action affichant la fiche bazar d'un inscrit
*
*
*@package Bazar
//Auteur original :
*@author        Florian SCHMITT <florian@outils-reseaux.org>
*@version       $Revision: 1.10 $ $Date: 2010-12-15 14:23:19 $
// +------------------------------------------------------------------------------------------------------+
*/


// +------------------------------------------------------------------------------------------------------+
// |                                            ENTETE du PROGRAMME                                       |
// +------------------------------------------------------------------------------------------------------+

if (!defined("WIKINI_VERSION")) {
        die ("acc&egrave;s direct interdit");
}

$nomwiki = $GLOBALS['wiki']->getUser();
if ($nomwiki) {
    $requetesql = 'SELECT DISTINCT tag FROM '.BAZ_PREFIXE.'pages WHERE latest="Y" AND comment_on = \'\' AND body LIKE \'%"nomwiki":"'.$nomwiki['name'].'"%\' AND body LIKE \'%"statut_fiche":"1"%\'
     AND tag IN (SELECT DISTINCT resource FROM '.BAZ_PREFIXE.'triples WHERE value = "fiche_bazar" AND property = "http://outils-reseaux.org/_vocabulary/type" ORDER BY resource ASC) ORDER BY time DESC';

    $results = $GLOBALS['wiki']->LoadAll($requetesql);;
    if (count($results)>0) {
        echo baz_voir_fiche(1, $results[0]["tag"]).'<br /><br />';
    } else {
        echo '<div class="alert">'._t('BAZ_PAS_DE_FICHES_UTILISATEUR_TROUVEE').'</div>';
    }

    echo '<h2 class="titre_mes_fiches">'._t('BAZ_VOS_FICHES').'</h2>'."\n";
    $tableau_fiches = baz_requete_recherche_fiches('', '', $GLOBALS['_BAZAR_']['id_typeannonce'], $GLOBALS['_BAZAR_']['categorie_nature'], 1, $nomwiki["name"]);
    if (count($tableau_fiches)>0) {
        echo baz_afficher_liste_resultat($tableau_fiches, false);
    } else {
        echo '<div class="alert">'._t('BAZ_PAS_DE_FICHES_SAUVEES_EN_VOTRE_NOM').'</div>'."\n";
    }
}
