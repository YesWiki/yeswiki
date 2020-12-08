<?php

/*vim: set expandtab tabstop=4 shiftwidth=4: */

// +------------------------------------------------------------------------------------------------------+
// | PHP version 4.1                                                                                      |
// +------------------------------------------------------------------------------------------------------+
// | Copyright (C) 2004 Tela Botanica (accueil@tela-botanica.org)                                         |
// +------------------------------------------------------------------------------------------------------+
// | This library is free software; you can redistribute it and/or                                        |
// | modify it under the terms of the GNU Lesser General Public                                           |
// | License as published by the Free Software Foundation; either                                         |
// | version 2.1 of the License, or (at your option) any later version.                                   |
// |                                                                                                      |
// | This library is distributed in the hope that it will be useful,                                      |
// | but WITHOUT ANY WARRANTY; without even the implied warranty of                                       |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU                                    |
// | Lesser General Public License for more details.                                                      |
// |                                                                                                      |
// | You should have received a copy of the GNU Lesser General Public                                     |
// | License along with this library; if not, write to the Free Software                                  |
// | Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA                            |
// +------------------------------------------------------------------------------------------------------+
// CVS : $Id: formulaire.fonct.inc.php,v 1.25 2010-12-15 10:45:43 mrflos Exp $


/**
 * Formulaire
 *
 * Les fonctions de mise en page des formulaire
 *
 * @package bazar
 *  Auteur original :
 * @author        Florian SCHMITT <florian@outils-reseaux.org>
 *  Autres auteurs :
 * @author        Aleandre GRANIER <alexandre@tela-botanica.org>
 * @copyright     Tela-Botanica 2000-2004
 * @version       $Revision: 1.25 $ $Date: 2010-12-15 10:45:43 $
 * +------------------------------------------------------------------------------------------------------+
 */


//-------------------FONCTIONS DE MISE EN PAGE DES FORMULAIRES
// pour chaque element du formulaire, le mode saisie, la requete au moment de la saisie dans la base de donnees, le rendu en html pour la consultation

use YesWiki\Bazar\Service\FicheManager;

/** testACLsiSaisir() - test si le mode est saisir et si l'utilisateur a accés à l'écriture de ce champ
 *
 * @param    string  Type d'action pour le formulaire : saisie, modification, vue,... saisie par defaut
 * @param    mixed   Le tableau des valeurs des differentes option pour l'element liste
 * @param    mixed   L'objet contenant les valeurs de la fiche, dans le cas d'une modification
 * @return   boolean 'True' lorsqu'on ne peut pas 'saisir' ce champ
 */
 
function testACLsiSaisir($mode, $tableau_template, $valeurs_fiche)
{
    $acl = empty($tableau_template[12]) ? '' : $tableau_template[12] ; // acl pour l'écriture
        
    if (isset($valeurs_fiche['id_fiche'])) {
        $tag = $valeurs_fiche['id_fiche'] ;
    } else {
        $tag = '' ;
    }
    $mode_creation = '' ;
    if ($tag == '') {
        $mode_creation = 'creation' ;
    }
        
    return $mode == 'saisie' && !empty($acl) && !$GLOBALS['wiki']->CheckACL($acl, null, true, $tag, $mode_creation)  ;
}

/** listefiches() - permet d'aller saisir et modifier un autre type de fiche
 *
 * @param    mixed   L'objet QuickForm du formulaire
 * @param    mixed   Le tableau des valeurs des différentes option pour le texte HTML
 * @param    string  Type d'action pour le formulaire : saisie, modification, vue,... saisie par défaut
 * @param    mixed  Tableau des valeurs par défauts (pour modification)
 *
 * @return   void
 */
function listefiches(&$formtemplate, $tableau_template, $mode, $valeurs_fiche)
{
    if (!isset($tableau_template[1])) {
        return $GLOBALS['wiki']->Format('//Erreur sur listefiches : pas d\'identifiant de type de fiche passé...//');
    }
    if (isset($tableau_template[6]) && $tableau_template[6] == 'checkbox') {
        $typefiche = 'checkboxfiche';
    } else {
        $typefiche = 'listefiche';
    }
    if (isset($tableau_template[2]) && $tableau_template[2] != '') {
        $query = $tableau_template[2] . '|' . $typefiche . $valeurs_fiche['id_typeannonce'] . '=' . $valeurs_fiche['id_fiche'];
    } elseif (isset($valeurs_fiche) && $valeurs_fiche != '') {
        $query = $typefiche . $valeurs_fiche['id_typeannonce'] . '=' . $valeurs_fiche['id_fiche'];
    } else {
        $query= '';
    }
    if (isset($tableau_template[3])) {
        $otherparams = $tableau_template[3];
    } else {
        $otherparams = '';
    }
    if (!empty($tableau_template[4])) {
        $nb = $tableau_template[4];
    } else {
        $nb = '';
    }
    if (isset($tableau_template[5])) {
        $template = $tableau_template[5];
    } else {
        $template = $GLOBALS['wiki']->config['default_bazar_template'];
    }
    $actionbazarliste = '{{bazarliste id="' . $tableau_template[1] . '" query="' . $query . '" nb="' . $nb . '" ' . $otherparams . ' template="' . $template . '"}}';
    if (testACLsiSaisir($mode, $tableau_template, $valeurs_fiche)) {
        // cas où on est en mode saisie et que le champ n'est pas autorisé à la modification, le champ est omis
        return "";
    } elseif (isset($valeurs_fiche['id_fiche']) && $mode == 'saisie') {
        $html = $GLOBALS['wiki']->Format($actionbazarliste);
        return $html;
    } elseif ($mode == 'html') {
        $html = '<span class="BAZ_texte">'.$GLOBALS['wiki']->Format($actionbazarliste).'</span>';
        return $html;
    }
}

// nouvelle appelation pour moins la confondre
function listefichesliees(&$formtemplate, $tableau_template, $mode, $valeurs_fiche)
{
    return listefiches($formtemplate, $tableau_template, $mode, $valeurs_fiche);
}
