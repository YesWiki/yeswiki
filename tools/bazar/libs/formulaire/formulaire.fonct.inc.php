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

//comptatibilite avec PHP4...
if (version_compare(phpversion(), '5.0') < 0) {
    eval('
            function clone($object)
            {
            return $object;
            }
            ');
}

/** afficher_image() - genere une image en cache (gestion taille et vignettes) et l'affiche comme il faut
 *
 * @param    string	nom du fichier image
 * @param	string	label pour l'image
 * @param    string	classes html supplementaires
 * @param    int		largeur en pixel de la vignette
 * @param    int		hauteur en pixel de la vignette
 * @param    int		largeur en pixel de l'image redimensionnee
 * @param    int		hauteur en pixel de l'image redimensionnee
 * @return   void
 */
function afficher_image($nom_image, $label, $class, $largeur_vignette, $hauteur_vignette, $largeur_image, $hauteur_image)
{
    //faut il creer la vignette?
    if ($hauteur_vignette!='' && $largeur_vignette!='') {
        //la vignette n'existe pas, on la genere
        if (!file_exists('cache/vignette_'.$nom_image)) {
            $adr_img = redimensionner_image(BAZ_CHEMIN_UPLOAD.$nom_image, 'cache/vignette_'.$nom_image, $largeur_vignette, $hauteur_vignette);
        }
        list($width, $height, $type, $attr) = getimagesize('cache/vignette_'.$nom_image);
        //faut il redimensionner l'image?
        if ($hauteur_image!='' && $largeur_image!='') {
            //l'image redimensionnee n'existe pas, on la genere
            if (!file_exists('cache/image_'.$nom_image)) {
                $adr_img = redimensionner_image(BAZ_CHEMIN_UPLOAD.$nom_image, 'cache/image_'.$nom_image, $largeur_image, $hauteur_image);
            }
            //on renvoit l'image en vignette, avec quand on clique, l'image redimensionnee
            $url_base = str_replace('wakka.php?wiki=','',$GLOBALS['wiki']->config['base_url']);

            return 	'<a class="triggerimage'.' '.$class.'" rel="#overlay-link" href="'.$url_base.'cache/image_'.$nom_image.'">'."\n".
                    '<img alt="'.$nom_image.'"'.' src="'.$url_base.'cache/vignette_'.$nom_image.'" width="'.$width.'" height="'.$height.'" />'."\n".'</a>'."\n";

        } else {
            //on renvoit l'image en vignette, avec quand on clique, l'image originale
            return  '<a class="triggerimage'.' '.$class.'" rel="#overlay-link" href="'.$url_base.BAZ_CHEMIN_UPLOAD.$nom_image.'">'."\n".
                    '<img alt="'.$nom_image.'"'.' src="'.$url_base.'cache/vignette_'.$nom_image.'" width="'.$width.'" height="'.$height.'" rel="'.$url_base.'cache/image_'.$nom_image.'" />'."\n".
                    '</a>'."\n";
        }
    }
    //pas de vignette, mais faut il redimensionner l'image?
    else if ($hauteur_image!='' && $largeur_image!='') {
        //l'image redimensionnee n'existe pas, on la genere
        if (!file_exists('cache/image_'.$nom_image)) {
            $adr_img = redimensionner_image(BAZ_CHEMIN_UPLOAD.$nom_image, 'cache/image_'.$nom_image, $largeur_image, $hauteur_image);
        }
        //on renvoit l'image redimensionnee
        list($width, $height, $type, $attr) = getimagesize('cache/image_'.$nom_image);

        return  '<img class="'.$class.'" alt="'.$nom_image.'"'.' src="cache/image_'.$nom_image.'" width="'.$width.'" height="'.$height.'" />'."\n";

    }
    //on affiche l'image originale sinon
    else {
        list($width, $height, $type, $attr) = getimagesize(BAZ_CHEMIN_UPLOAD.$nom_image);

        return  '<img class="'.$class.'" alt="'.$nom_image.'"'.' src="'.BAZ_CHEMIN_UPLOAD.$nom_image.'" width="'.$width.'" height="'.$height.'" />'."\n";
    }
}

function redimensionner_image($image_src, $image_dest, $largeur, $hauteur)
{
    require_once 'tools'.DIRECTORY_SEPARATOR.'bazar'.DIRECTORY_SEPARATOR.'libs'.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'class.imagetransform.php';
    $imgTrans = new imageTransform();
    $imgTrans->sourceFile = $image_src;
    $imgTrans->targetFile = $image_dest;
    $imgTrans->resizeToWidth = $largeur;
    $imgTrans->resizeToHeight = $hauteur;
    if (!$imgTrans->resize()) {
        // in case of error, show error code
        return $imgTrans->error;
        // if there were no errors
    } else {
        return $imgTrans->targetFile;
    }
}

//-------------------FONCTIONS DE TRAITEMENT DU TEMPLATE DU FORMULAIRE

/** formulaire_valeurs_template_champs() - Decoupe le template et renvoie un tableau structure
 *
 * @param    string  Template du formulaire
 * @param    mixed   Le tableau des valeurs des differentes option pour l'element liste
 * @param    string  Type d'action pour le formulaire : saisie, modification, vue,... saisie par defaut
 * @return   void
 */
function formulaire_valeurs_template_champs($template)
{
    //Parcours du template, pour mettre les champs du formulaire avec leurs valeurs specifiques
    $tableau_template= array();
    $nblignes=0;
    //on traite le template ligne par ligne
    $chaine = explode ("\n", $template);
    foreach ($chaine as $ligne) {
        if ($ligne!='') {
            //on decoupe chaque ligne par le separateur *** (c'est historique)
            $tablignechampsformulaire = array_map("trim", explode ("***", $ligne));
            if (count($tablignechampsformulaire) > 3) {
                $tableau_template[$nblignes] = $tablignechampsformulaire;
                if (!isset($tableau_template[$nblignes][9])) $tableau_template[$nblignes][9] = '';
                if (!isset($tableau_template[$nblignes][10])) $tableau_template[$nblignes][10] = '';
                $nblignes++;
            }
        }
    }

    return $tableau_template;
}



//-------------------FONCTIONS DE MISE EN PAGE DES FORMULAIRES

/** radio() - Ajoute un element de type liste deroulante au formulaire
 *
 * @param    mixed   L'objet QuickForm du formulaire
 * @param    mixed   Le tableau des valeurs des differentes option pour l'element liste
 * @param    string  Type d'action pour le formulaire : saisie, modification, vue,... saisie par daefaut
 * @return   void
 */
function radio(&$formtemplate, $tableau_template, $mode, $valeurs_fiche)
{
    if ($mode == 'saisie') {
        $bulledaide = '';
        if (isset($tableau_template[10]) && $tableau_template[10]!='') {
            $bulledaide .= ' <img class="tooltip_aide" title="'.htmlentities($tableau_template[10]).'" src="tools/bazar/presentation/images/aide.png" width="16" height="16" alt="image aide" />';
        }
        $ob = ''; $optionrequired = '';
        if (isset($tableau_template[8]) && $tableau_template[8]==1) {
            $ob .= '<span class="symbole_obligatoire">*&nbsp;</span>'."\n";
            $optionrequired .= ' radio_required';
        }
        if (isset($valeurs_fiche[$tableau_template[0].$tableau_template[1].$tableau_template[6]]) && $valeurs_fiche[$tableau_template[0].$tableau_template[1].$tableau_template[6]]!='') {
            $def =	$valeurs_fiche[$tableau_template[0].$tableau_template[1].$tableau_template[6]];
        } else {
            $def = $tableau_template[5];
        }


        $radio_html = '<fieldset class="bazar_fieldset'.$optionrequired.'"><legend>'.$ob.$tableau_template[2].$bulledaide.'</legend>';

        $valliste = baz_valeurs_liste($tableau_template[1]);
        if (is_array($valliste['label'])) {
            foreach ($valliste['label'] as $key => $label) {
                $radio_html .= '<div class="bazar_radio">';
                $radio_html .= '<input type="radio" id="'.$tableau_template[0].$tableau_template[1].$tableau_template[6].$key.'" value="'.$key.'" name="'.$tableau_template[0].$tableau_template[1].$tableau_template[6].'" class="element_radio"';
                if ($def != '' && strstr($key, $def)) {
                    $radio_html .= ' checked';
                }
                $radio_html .= ' /><label for="'.$tableau_template[0].$tableau_template[1].$tableau_template[6].$key.'">'.$label.'</label>';
                $radio_html .= '</div>';


            }
        }
        $radio_html .= '</fieldset>';

        $formtemplate->addElement('html', $radio_html) ;


    } elseif ($mode == 'requete') {
    } elseif ($mode == 'formulaire_recherche') {
    } elseif ($mode == 'html') {
        $html = '';
        if (isset($valeurs_fiche[$tableau_template[0].$tableau_template[1].$tableau_template[6]]) && $valeurs_fiche[$tableau_template[0].$tableau_template[1].$tableau_template[6]]!='') {
            $valliste = baz_valeurs_liste($tableau_template[1]);

            $tabresult = explode(',', $valeurs_fiche[$tableau_template[0].$tableau_template[1].$tableau_template[6]]);
            if (is_array($tabresult)) {
                $labels_result = '';
                foreach ($tabresult as $id)
                    if (isset($valliste["label"][$id])) {
                        if ($labels_result == '') $labels_result = $valliste["label"][$id];
                        else $labels_result .= ', '.$valliste["label"][$id];
                    }
            }

            {
                $html = '<div class="BAZ_rubrique">'."\n".
                    '<span class="BAZ_label">'.$tableau_template[2].'&nbsp;:</span>'."\n".
                    '<span class="BAZ_texte">'."\n".
                    $labels_result."\n".
                    '</span>'."\n".
                    '</div>'."\n";
            }
        }

        return $html;
    }
}




/** liste() - Ajoute un élément de type liste déroulante au formulaire
 *
 * @param    mixed   L'objet QuickForm du formulaire
 * @param    mixed   Le tableau des valeurs des différentes option pour l'élément liste
 * @param    string  Type d'action pour le formulaire : saisie, modification, vue,... saisie par défaut
 * @return   void
 */
function liste(&$formtemplate, $tableau_template, $mode, $valeurs_fiche)
{
    if ($mode=='saisie') {
        $valliste = baz_valeurs_liste($tableau_template[1]);
        if ($valliste) {
            $bulledaide = '';
            if (isset($tableau_template[10]) && $tableau_template[10]!='') {
                $bulledaide = ' <img class="tooltip_aide" title="'.htmlentities($tableau_template[10]).'" src="tools/bazar/presentation/images/aide.png" width="16" height="16" alt="image aide" />';
            }

            $select_html = '<div class="control-group">'."\n".'<div class="control-label">'."\n";
            if (isset($tableau_template[8]) && $tableau_template[8]==1) {
                $select_html .= '<span class="symbole_obligatoire">*&nbsp;</span>'."\n";
            }
            $select_html .= $tableau_template[2].$bulledaide.' : </div>'."\n".'<div class="controls">'."\n".'<select';

            $select_attributes = '';

            if ($tableau_template[4] != '' && $tableau_template[4] > 1) {
                $select_attributes .= ' multiple="multiple" size="'.$tableau_template[4].'"';
                $selectnametab = '[]';
            } else {
                $selectnametab = '';
            }

            $select_attributes .= ' class="bazar-select" id="'.$tableau_template[0].$tableau_template[1].$tableau_template[6].'" name="'.$tableau_template[0].$tableau_template[1].$tableau_template[6].$selectnametab.'"';

            if (isset($tableau_template[8]) && $tableau_template[8]==1) {
                $select_attributes .= ' required="required"';
            }
            $select_html .= $select_attributes.'>'."\n";

            if (isset($valeurs_fiche[$tableau_template[0].$tableau_template[1].$tableau_template[6]]) && $valeurs_fiche[$tableau_template[0].$tableau_template[1].$tableau_template[6]]!='') {
                $def =	$valeurs_fiche[$tableau_template[0].$tableau_template[1].$tableau_template[6]];
            } else {
                $def = $tableau_template[5];
            }

            
            if ($def=='' && ($tableau_template[4] == '' || $tableau_template[4] <= 1 ) || $def==0) {
                $select_html .= '<option value="0" selected="selected">'.BAZ_CHOISIR.'</option>'."\n";
            }
            if (is_array($valliste['label'])) {
                foreach ($valliste['label'] as $key => $label) {
                    $select_html .= '<option value="'.$key.'"';
                    if ($def != '' && $key==$def) $select_html .= ' selected="selected"';
                    $select_html .= '>'.$label.'</option>'."\n";
                }

            }

            $select_html .= "</select>\n</div>\n</div>\n";

            $formtemplate->addElement('html', $select_html) ;
        }

    } elseif ($mode == 'requete') {

    } elseif ($mode == 'formulaire_recherche') {
        //on affiche la liste sous forme de liste deroulante
        if ($tableau_template[9]==1) {
            $valliste = baz_valeurs_liste($tableau_template[1]);
            $select[0] = BAZ_INDIFFERENT;
            if (is_array($valliste['label'])) {
                $select = $select + $valliste['label'];
            }

            $option = array('id' => $tableau_template[0].$tableau_template[1].$tableau_template[6]);
            require_once 'HTML/QuickForm/select.php';
            $select= new HTML_QuickForm_select($tableau_template[0].$tableau_template[1].$tableau_template[6], $tableau_template[2], $select, $option);
            if ($tableau_template[4] != '') $select->setSize($tableau_template[4]);
            $select->setMultiple(0);
            $nb = (isset($_REQUEST[$tableau_template[0].$tableau_template[1].$tableau_template[6]])? $_REQUEST[$tableau_template[0].$tableau_template[1].$tableau_template[6]] : 0);
            $select->setValue($nb);
            $formtemplate->addElement($select) ;
        }
        //on affiche la liste sous forme de checkbox
        if ($tableau_template[9]==2) {
            $valliste = baz_valeurs_liste($tableau_template[1]);
            require_once 'HTML/QuickForm/checkbox.php';
            $i=0;
            $optioncheckbox = array('class' => 'checkbox');

            foreach ($valliste['label'] as $id => $label) {
                if ($i==0) $tab_chkbox = $tableau_template[2] ; else $tab_chkbox='&nbsp;';
                $checkbox[$i]= & HTML_QuickForm::createElement('checkbox', $id, $tab_chkbox, $label, $optioncheckbox) ;
                $i++;
            }

            $squelette_checkbox =& $formtemplate->defaultRenderer();
            $squelette_checkbox->setElementTemplate( '<fieldset class="bazar_fieldset">'."\n".'<legend>{label}'.
                                                    '<!-- BEGIN required --><span class="symbole_obligatoire">&nbsp;*</span><!-- END required -->'."\n".
                                                    '</legend>'."\n".'{element}'."\n".'</fieldset> '."\n"."\n", $tableau_template[0].$tableau_template[1].$tableau_template[6]);
            $squelette_checkbox->setGroupElementTemplate( "\n".'<div class="checkbox">'."\n".'{element}'."\n".'</div>'."\n", $tableau_template[0].$tableau_template[1].$tableau_template[6]);

            $formtemplate->addGroup($checkbox, $tableau_template[0].$tableau_template[1].$tableau_template[6], $tableau_template[2], "\n");
        }
    } elseif ($mode == 'requete_recherche') {
        if ($tableau_template[9]==1 && isset($_REQUEST[$tableau_template[0].$tableau_template[1].$tableau_template[6]]) && $_REQUEST[$tableau_template[0].$tableau_template[1].$tableau_template[6]] != 0) {
            /*return ' AND bf_id_fiche IN (SELECT bfvt_ce_fiche FROM '.BAZ_PREFIXE.'fiche_valeur_texte WHERE bfvt_id_element_form="'.$tableau_template[0].$tableau_template[1].$tableau_template[6].'" AND bfvt_texte="'.$_REQUEST[$tableau_template[0].$tableau_template[1].$tableau_template[6]].'") ';*/
        }
    } elseif ($mode == 'html') {
        $html = '';
        if (isset($valeurs_fiche[$tableau_template[0].$tableau_template[1].$tableau_template[6]]) && $valeurs_fiche[$tableau_template[0].$tableau_template[1].$tableau_template[6]]!='') {
            $valliste = baz_valeurs_liste($tableau_template[1]);

            if (isset($valliste["label"][$valeurs_fiche[$tableau_template[0].$tableau_template[1].$tableau_template[6]]])) {
                $html = '<div class="BAZ_rubrique">'."\n".
                        '<span class="BAZ_label">'.$tableau_template[2].'&nbsp;:</span>'."\n".
                        '<span class="BAZ_texte">'."\n".
                        $valliste["label"][$valeurs_fiche[$tableau_template[0].$tableau_template[1].$tableau_template[6]]]."\n".
                        '</span>'."\n".
                        '</div>'."\n";
            }
        }

        return $html;
    }
}

/** checkbox() - Ajoute un element de type case a cocher au formulaire
 *
 * @param    mixed   L'objet QuickForm du formulaire
 * @param    mixed   Le tableau des valeurs des differentes option pour l'element case a cocher
 * @param    string  Type d'action pour le formulaire : saisie, modification, vue,... saisie par defaut
 * @return   void
 */
function checkbox(&$formtemplate, $tableau_template, $mode, $valeurs_fiche)
{
    if ($mode == 'saisie') {
        $bulledaide = '';
        if (isset($tableau_template[10]) && $tableau_template[10]!='') $bulledaide = ' <img class="tooltip_aide" title="'.htmlentities($tableau_template[10]).'" src="tools/bazar/presentation/images/aide.png" width="16" height="16" alt="image aide" />';
        $valliste = baz_valeurs_liste($tableau_template[1]);
        
        if ($valliste) {

            $choixcheckbox = $valliste['label'];

            require_once 'HTML/QuickForm/checkbox.php';
            $i=0;
            $optioncheckbox = array('class' => 'element_checkbox');

            //valeurs par defauts
            if (isset($valeurs_fiche[$tableau_template[0].$tableau_template[1].$tableau_template[6]])) {
                $tab = explode( ',', $valeurs_fiche[$tableau_template[0].$tableau_template[1].$tableau_template[6]] );
            } else {
                $tab = explode( ',', $tableau_template[5] );
            }

            foreach ($choixcheckbox as $id => $label) {
                if ($i==0) {
                    $tab_chkbox = $tableau_template[2] ;
                } else {
                    $tab_chkbox='&nbsp;';
                }

                //teste si la valeur de la liste doit etre cochee par defaut
                if (in_array($id,$tab)) {
                    $defaultValues[$tableau_template[0].$tableau_template[1].$tableau_template[6].'['.$id.']'] = true;
                    //echo 'a cocher '.$tableau_template[0].$tableau_template[1].$tableau_template[6].'['.$id.']'.'<br />';
                } else {
                    $defaultValues[$tableau_template[0].$tableau_template[1].$tableau_template[6].'['.$id.']'] = false;
                }

                $checkbox[$i] = $formtemplate->createElement($tableau_template[0], $id, $tab_chkbox, $label, $optioncheckbox);
                $i++;
            }

            $squelette_checkbox = & $formtemplate->defaultRenderer();
            $classrequired=''; $req = '';
            if (isset($tableau_template[8]) && $tableau_template[8]==1) {
                $classrequired .= ' chk_required';
                $req = '<span class="symbole_obligatoire">&nbsp;*</span> ';
            }
            //$squelette_checkbox->setElementTemplate( '<fieldset class="bazar_fieldset'.$classrequired.'">'."\n".'<legend>'."\n".'{label}'."\n".
            //        '</legend>'."\n".'{element}'."\n".'</fieldset> '."\n"."\n", $tableau_template[0].$tableau_template[1].$tableau_template[6]);
            $squelette_checkbox->setGroupElementTemplate( "\n".'<div class="checkbox">'."\n".'{element}'."\n".'</div>'."\n", $tableau_template[0].$tableau_template[1].$tableau_template[6]);
            $formtemplate->addGroup($checkbox, $tableau_template[0].$tableau_template[1].$tableau_template[6], $req.$tableau_template[2].$bulledaide, "\n");

            $formtemplate->setDefaults($defaultValues);
        }
    } elseif ($mode == 'requete') {
        return array($tableau_template[0].$tableau_template[1].$tableau_template[6] => $valeurs_fiche[$tableau_template[0].$tableau_template[1].$tableau_template[6]]);
    } elseif ($mode == 'formulaire_recherche') {
        if ($tableau_template[9]==1) {
            $valliste = baz_valeurs_liste($tableau_template[1]);
            require_once 'HTML/QuickForm/checkbox.php';
            $i=0;
            $optioncheckbox = array('class' => 'element_checkbox');

            foreach ($valliste['label'] as $id => $label) {
                if ($i==0) $tab_chkbox = $tableau_template[2] ; else $tab_chkbox='&nbsp;';

                if (isset($_REQUEST[$tableau_template[0].$tableau_template[1].$tableau_template[6]]) && array_key_exists($id, $_REQUEST[$tableau_template[0].$tableau_template[1].$tableau_template[6]])) {
                    $optioncheckbox['checked']='checked';
                } else {
                    unset($optioncheckbox['checked']);
                }

                $checkbox[$i]= & HTML_QuickForm::createElement('checkbox', $id, $tab_chkbox, $label, $optioncheckbox) ;

                $i++;
            }

            $squelette_checkbox =& $formtemplate->defaultRenderer();
            $squelette_checkbox->setElementTemplate( '<fieldset class="bazar_fieldset">'."\n".'<legend>{label}'.
                    '<!-- BEGIN required --><span class="symbole_obligatoire">&nbsp;*</span><!-- END required -->'."\n".
                    '</legend>'."\n".'{element}'."\n".'</fieldset> '."\n"."\n", $tableau_template[0].$tableau_template[1].$tableau_template[6]);
            $squelette_checkbox->setGroupElementTemplate( "\n".'<div class="checkbox">'."\n".'{element}'."\n".'</div>'."\n", $tableau_template[0].$tableau_template[1].$tableau_template[6]);

            $formtemplate->addGroup($checkbox, $tableau_template[0].$tableau_template[1].$tableau_template[6], $tableau_template[2], "\n");
        }
    } elseif ($mode == 'html') {
        $html = '';
        if (isset($valeurs_fiche[$tableau_template[0].$tableau_template[1].$tableau_template[6]]) && $valeurs_fiche[$tableau_template[0].$tableau_template[1].$tableau_template[6]]!='') {
            $valliste = baz_valeurs_liste($tableau_template[1]);

            $tabresult = explode(',', $valeurs_fiche[$tableau_template[0].$tableau_template[1].$tableau_template[6]]);
            if (is_array($tabresult)) {
                $labels_result = '';
                foreach ($tabresult as $id)
                    if (isset($valliste["label"][$id])) {
                        if ($labels_result == '') $labels_result = $valliste["label"][$id];
                        else $labels_result .= ', '.$valliste["label"][$id];
                    }
            }

            {
                $html = '<div class="BAZ_rubrique">'."\n".
                    '<span class="BAZ_label">'.$tableau_template[2].'&nbsp;:</span>'."\n".
                    '<span class="BAZ_texte">'."\n".
                    $labels_result."\n".
                    '</span>'."\n".
                    '</div>'."\n";
            }
        }

        return $html;
    }
}

/** jour() - Ajoute un élément de type date au formulaire
 *
 * @param    mixed   L'objet QuickForm du formulaire
 * @param    mixed   Le tableau des valeurs des différentes option pour l'élément date
 * @param    string  Type d'action pour le formulaire : saisie, modification, vue,... saisie par défaut
 * @return   void
 */
function jour(&$formtemplate, $tableau_template, $mode, $valeurs_fiche)
{
    if ($mode == 'saisie') {
        $bulledaide = '';
        if (isset($tableau_template[10]) && $tableau_template[10]!='') {
            $bulledaide = ' <img class="tooltip_aide" title="'.htmlentities($tableau_template[10]).'" src="tools/bazar/presentation/images/aide.png" width="16" height="16" alt="image aide" />';
        }

        $date_html = '<div class="control-group">'."\n".'<div class="control-label">'."\n";
        if (isset($tableau_template[8]) && $tableau_template[8]==1) {
            $date_html .= '<span class="symbole_obligatoire">*&nbsp;</span>'."\n";
        }
        $date_html .= $tableau_template[2].$bulledaide.' : </div>'."\n".'<div class="controls">'."\n".'<input type="date" name="'.$tableau_template[1].'" ';

        $date_html .= ' class="bazar-date" id="'.$tableau_template[1].'"';

        if (isset($tableau_template[8]) && $tableau_template[8]==1) {
            $date_html .= ' required="required"';
        }

        //gestion des valeurs par defaut pour modification
        if (isset($valeurs_fiche[$tableau_template[1]])) {
            $date_html .= ' value="'.$valeurs_fiche[$tableau_template[1]].'" />';
        } else {
            //gestion des valeurs par defaut (date du jour)
            if (isset($tableau_template[5]) && $tableau_template[5]!='') {
                $date_html .= ' value="'.$tableau_template[5].'" />';
            } else {
                $date_html .= ' value="" />';
            }
        }
        $date_html .= '</div>'."\n".'</div>'."\n";

        $formtemplate->addElement('html', $date_html) ;

    } elseif ($mode == 'requete') {
        return array($tableau_template[1] => $_POST[$tableau_template[1]]);
    } elseif ($mode == 'recherche') {

    } elseif ($mode == 'html') {
        $res = '<div class="BAZ_rubrique">'."\n".
                '<span class="BAZ_label">'.$tableau_template[2].'&nbsp;:</span>'."\n";
        $res .= '<span class="BAZ_texte">'.strftime('%d.%m.%Y',strtotime($valeurs_fiche[$tableau_template[1]])).'</span>'."\n".'</div>'."\n";

        return $res;
    }
}

/** listedatedeb() - voir date()
 *
 */
function listedatedeb(&$formtemplate, $tableau_template, $mode, $valeurs_fiche)
{
    return jour($formtemplate, $tableau_template , $mode, $valeurs_fiche);
}

/** listedatefin() - voir date()
 *
 */
function listedatefin(&$formtemplate, $tableau_template, $mode, $valeurs_fiche)
{
    return jour($formtemplate, $tableau_template , $mode, $valeurs_fiche);
}

/** tags() - Ajoute un élément de type mot clés (tags)
 *
 * @param    mixed   L'objet QuickForm du formulaire
 * @param    mixed   Le tableau des valeurs des différentes option pour l'élément texte
 * @param    string  Type d'action pour le formulaire : saisie, modification, vue,... saisie par défaut
 * @param    mixed   valeur par défaut du champs
 * @return   void
 */
function tags(&$formtemplate, $tableau_template, $mode, $valeurs_fiche)
{
    if ($mode == 'saisie') {
        $tags_javascript = '';
        //gestion des mots cles deja entres
        if (isset($valeurs_fiche[$tableau_template[1]])) {
            $tags = explode(",", mysql_escape_string($valeurs_fiche[$tableau_template[1]]));
            if (is_array($tags)) {
                sort($tags);
                foreach ($tags as $tag) {
                    $tags_javascript .= 't.add(\''.$tag.'\');'."\n";
                }
            }
        }

        // on recupere tous les tags du site
        $response = array();
        $tab_tous_les_tags = $GLOBALS['wiki']->GetAllTags();
        if (is_array($tab_tous_les_tags)) {
            foreach ($tab_tous_les_tags as $tab_les_tags) {
                $response[] = $tab_les_tags['value'];
            }
        }
        sort($response);
        $tagsexistants = '\''.implode('\',\'', $response).'\'';

        $GLOBALS['js'] = ((isset($GLOBALS['js'])) ? $GLOBALS['js'] : '').'
            <script src="tools/tags/libs/jquery-ui-1.9.1.custom.min.js"></script>
            <script src="tools/tags/libs/tag-it.js"></script>
            <script>
            $(function(){
                    var tagsexistants = ['.$tagsexistants.'];

                    $(\'.input_tags\').each(function() {
                        $(this).tagit({
availableTags: tagsexistants
});
                        });

                    //bidouille antispam
                    $(".antispam").attr(\'value\', \'1\');
                    });
</script>';

//gestion des valeurs par defaut : d'abord on regarde s'il y a une valeur a modifier,
//puis s'il y a une variable passee en GET,
//enfin on prend la valeur par defaut du formulaire sinon
if (isset($valeurs_fiche[$tableau_template[1]])) {
    $defauts = $valeurs_fiche[$tableau_template[1]];
} elseif (isset($_GET[$tableau_template[1]])) {
    $defauts = stripslashes($_GET[$tableau_template[1]]);
} else {
    $defauts = stripslashes($tableau_template[5]);
}

$option=array('size'=>$tableau_template[3],'maxlength'=>$tableau_template[4], 'id' => $tableau_template[1], 'value' => $defauts, 'class' => 'input_texte input_tags');
$bulledaide = '';
if (isset($tableau_template[10]) && $tableau_template[10]!='') $bulledaide = ' <img class="tooltip_aide" title="'.htmlentities($tableau_template[10]).'" src="tools/bazar/presentation/images/aide.png" width="16" height="16" alt="image aide" />';
$formtemplate->addElement('text', $tableau_template[1], $tableau_template[2].$bulledaide, $option) ;

} elseif ($mode == 'requete') {
    //on supprime les tags existants
    $GLOBALS['wiki']->DeleteTriple($GLOBALS['_BAZAR_']['id_fiche'], 'http://outils-reseaux.org/_vocabulary/tag', NULL, '', '');

    //on découpe les tags pour les mettre dans un tableau
    $tags = explode(",", mysql_escape_string($valeurs_fiche[$tableau_template[1]]));

    //on ajoute les tags postés
    foreach ($tags as $tag) {
        trim($tag);
        if ($tag!='') {
            $GLOBALS['wiki']->InsertTriple($GLOBALS['_BAZAR_']['id_fiche'], 'http://outils-reseaux.org/_vocabulary/tag', $tag, '', '');
        }
    }
    //on copie tout de meme dans les metadonnees
    //return formulaire_insertion_texte($tableau_template[1], $valeurs_fiche[$tableau_template[1]]);
    return array($tableau_template[1] => $valeurs_fiche[$tableau_template[1]]);
} elseif ($mode == 'html') {
    $html = '';
    if (isset($valeurs_fiche[$tableau_template[1]]) && $valeurs_fiche[$tableau_template[1]]!='') {
        $html = '<div class="BAZ_rubrique">'."\n".
            '<span class="BAZ_label">'.$tableau_template[2].'&nbsp;:</span>'."\n";
        $html .= '<div class="BAZ_texte"> ';
        $tabtagsexistants = explode(',',htmlentities($valeurs_fiche[$tableau_template[1]]));

        if (count($tabtagsexistants)>0) {
            sort($tabtagsexistants);
            $tagsexistants = '<ul class="tagit ui-widget ui-widget-content ui-corner-all show">'."\n";
            foreach ($tabtagsexistants as $tag) {
                $tagsexistants .= '<li class="tagit-tag ui-widget-content ui-state-default ui-corner-all">
                    <a href="'.$GLOBALS['wiki']->href('listpages',$GLOBALS['wiki']->GetPageTag(),'tags='.$tag).'" title="Voir toutes les pages contenant ce mot cl&eacute;">'.$tag.'</a>
                    </li>'."\n";
            }
            $tagsexistants .= '</ul>'."\n";
            $html .= $tagsexistants."\n";
        }

        $html .= '</div>'."\n".'</div>'."\n";
    }

    return $html;
}
}




/** texte() - Ajoute un element de type texte au formulaire
 *
 * @param    mixed   L'objet QuickForm du formulaire
 * @param    mixed   Le tableau des valeurs des différentes option pour l'élément texte
 * @param    string  Type d'action pour le formulaire : saisie, modification, vue,... saisie par défaut
 * @return   void
 */
function texte(&$formtemplate, $tableau_template, $mode, $valeurs_fiche,$protege=0)
{
    list($type, $identifiant, $label, $nb_min_car, $nb_max_car, $valeur_par_defaut, $regexp, $type_input , $obligatoire, , $bulle_d_aide) = $tableau_template;
    if ($mode == 'saisie') {
        // on prepare le html de la bulle d'aide, si elle existe
        if ($bulle_d_aide != '') {
            if ($protege==1 && isset($valeurs_fiche[$identifiant])) {
                   $bulle_d_aide.=" (Champ masqu&eacute;)";
            }
            $bulledaide = '<img class="tooltip_aide" title="'.htmlentities($bulle_d_aide).'" src="tools/bazar/presentation/images/aide.png" width="16" height="16" alt="image aide" />';
        } else {
            if ($protege==1 && isset($valeurs_fiche[$identifiant])) {
                   $bulle_d_aide="Champ masqu&eacute;";
                   $bulledaide = '<img class="tooltip_aide" title="'.htmlentities($bulle_d_aide).'" src="tools/bazar/presentation/images/aide.png" width="16" height="16" alt="image aide" />';
            }
            else {
                $bulledaide = '';
            }
        }

        //gestion des valeurs par defaut : d'abord on regarde s'il y a une valeur a modifier,
        //puis s'il y a une variable passee en GET,
        //enfin on prend la valeur par defaut du formulaire sinon
        if (isset($valeurs_fiche[$identifiant])) {
            if ($protege==0) { // Pas d'affichage des valeurs en place si fiche deja presente / mais modifiable par tous
                $defauts = $valeurs_fiche[$identifiant];
            }
        } elseif (isset($_GET[$identifiant])) {
            $defauts = stripslashes($_GET[$identifiant]);
        } else {
            $defauts = stripslashes($valeur_par_defaut);
        }


        //si la valeur de nb_max_car est vide, on la mets au maximum
        if ($nb_max_car == '') $nb_max_car = 255;

        //par defaut il s'agit d'input html de type "text" (on precise si cela n'a pas ete entre)
        if ($type_input == '') $type_input = 'text';

        $input_html  = '<div class="control-group">'."\n".'<div class="control-label">';
        $input_html .= ($obligatoire == 1) ? '<span class="symbole_obligatoire">*&nbsp;</span>' : '';
        $input_html .= $label.$bulledaide.' : </div>'."\n";
        $input_html .= '<div class="controls">'."\n";
        $input_html .= '<input type="'.$type_input.'"';
        $input_html .= ($defauts != '') ? ' value="'.$defauts.'"' : '';
        $input_html .= ' name="'.$identifiant.'" class="input_texte" id="'.$identifiant.'"';
        $input_html .= ' maxlength="'.$nb_max_car.'" size="'.$nb_max_car.'"';
        $input_html .= ($type_input == 'number' && $nb_min_car != '') ? ' min="'.$nb_min_car.'"' : '';
        $input_html .= ($type_input == 'number') ? ' max="'.$nb_max_car.'"' : '';
        $input_html .= ($regexp != '') ? ' pattern="'.$regexp.'"' : '';
        $input_html .= ($obligatoire == 1) ? ' required="required"' : '';
        $input_html .= '>'."\n".'</div>'."\n".'</div>'."\n";

        $formtemplate->addElement('html', $input_html) ;

    } elseif ($mode == 'requete') {
    // TODO tester
        if (($protege==1) && (baz_a_le_droit('voir_champ', (isset($valeurs_fiche['createur']) ? $valeurs_fiche['createur'] : ''))) || ($protege==0)) { // admin uniquement
            return array($tableau_template[1] => $valeurs_fiche[$tableau_template[1]]);
        }
    } elseif ($mode == 'html') {
    // TODO tester
        if (($protege==1) && (baz_a_le_droit('voir_champ', (isset($valeurs_fiche['createur']) ? $valeurs_fiche['createur'] : ''))) || ($protege==0)) { // admin uniquement
            $html = '';
            if (isset($valeurs_fiche[$tableau_template[1]]) && $valeurs_fiche[$tableau_template[1]]!='') {
                if ($tableau_template[1] == 'bf_titre') {
                    // Le titre
                    $html .= '<h1 class="BAZ_fiche_titre">'.htmlentities($valeurs_fiche[$tableau_template[1]]).'</h1>'."\n";
                } else {
                    $html = '<div class="BAZ_rubrique">'."\n".
                            '<span class="BAZ_label">'.$tableau_template[2].'&nbsp;:</span>'."\n";
                    $html .= '<span class="BAZ_texte"> ';
                    $html .= htmlentities($valeurs_fiche[$tableau_template[1]]).'</span>'."\n".'</div>'."\n";
                }
            }
        }
        //else
        //{
        //	$html = '<div class="BAZ_rubrique  BAZ_rubrique_'.$GLOBALS['_BAZAR_']['class'].'">'."\n".
        //				'<span class="BAZ_label '.$tableau_template[2].'_rubrique">'.$tableau_template[2].'&nbsp;:</span>'."\n";
        //	$html .= '<span class="BAZ_texte BAZ_texte_'.$GLOBALS['_BAZAR_']['class'].' '.$tableau_template[2].'_description"> ';
        //	$html .= NON_RENSEIGNE.'</span>'."\n".'</div>'."\n";
        //}
        return $html;
    }
}


/** texte_protege() - Ajoute un element de type texte au formulaire
 *
 * @param    mixed   L'objet QuickForm du formulaire
 * @param    mixed   Le tableau des valeurs des différentes option pour l'élément texte
 * @param    string  Type d'action pour le formulaire : saisie, modification, vue,... saisie par défaut
 * @return   void
 */
function texte_protege(&$formtemplate, $tableau_template, $mode, $valeurs_fiche)
{
    $protege=1;
    return texte($formtemplate, $tableau_template, $mode, $valeurs_fiche,$protege);
 
}


/** utilisateur_wikini() - Ajoute un élément de type texte pour créer un utilisateur wikini au formulaire
 *
 * @param    mixed   L'objet QuickForm du formulaire
 * @param    mixed   Le tableau des valeurs des différentes option pour l'élément texte
 * @param    string  Type d'action pour le formulaire : saisie, modification, vue,... saisie par défaut
 * @return   void
 */
function utilisateur_wikini(&$formtemplate, $tableau_template, $mode, $valeurs_fiche)
{
    if ($mode == 'saisie') {
        $option=array('size'=>$tableau_template[3],'maxlength'=>$tableau_template[4], 'id' => 'nomwiki');
        if (!isset($valeurs_fiche['nomwiki'])) {
                //mot de passe
                $bulledaide = '';
                if (isset($tableau_template[10]) && $tableau_template[10]!='') $bulledaide = ' <img class="tooltip_aide" title="'.htmlentities($tableau_template[10]).'" src="tools/bazar/presentation/images/aide.png" width="16" height="16" alt="image aide" />';
                $option = array('size' => $tableau_template[3], 'class' => 'input_texte');
                $formtemplate->addElement('password', 'mot_de_passe_wikini', BAZ_MOT_DE_PASSE.$bulledaide, $option) ;
                $formtemplate->addElement('password', 'mot_de_passe_repete_wikini', BAZ_MOT_DE_PASSE.' ('.BAZ_VERIFICATION.')', $option) ;
        } 
        else {
            $formtemplate->addElement('hidden', 'nomwiki', $valeurs_fiche['nomwiki']) ;
        }
    } elseif ($mode == 'requete') {
        if (!isset($valeurs_fiche['nomwiki'])) {
            if ($GLOBALS['wiki']->IsWikiName($valeurs_fiche[$tableau_template[1]])) {
                $nomwiki = $valeurs_fiche[$tableau_template[1]];
            } else {
                $nomwiki = genere_nom_wiki($valeurs_fiche[$tableau_template[1]]);
            }
            $requeteinsertionuserwikini = 'INSERT INTO '.$GLOBALS['wiki']->config["table_prefix"]."users SET ".
            "signuptime = now(), ".
            "name = '".mysql_escape_string($nomwiki)."', ".
            "email = '".mysql_escape_string($valeurs_fiche[$tableau_template[2]])."', ".
            "password = md5('".mysql_escape_string($valeurs_fiche['mot_de_passe_wikini'])."')";
            $resultat = $GLOBALS['_BAZAR_']['db']->query($requeteinsertionuserwikini) ;
            if (DB::isError($resultat)) {
                echo ($resultat->getMessage().$resultat->getDebugInfo()) ;
            }

            //envoi mail nouveau mot de passe
            $lien = str_replace("/wakka.php?wiki=","",$GLOBALS['wiki']->config["base_url"]);
            $objetmail = '['.str_replace("http://","",$lien).'] Vos nouveaux identifiants sur le site '.$GLOBALS['wiki']->config["wakka_name"];
            $messagemail = "Bonjour!\n\nVotre inscription sur le site a ete finalisee, dorenavant vous pouvez vous identifier avec les informations suivantes :\n\nVotre identifiant NomWiki : ".$nomwiki."\n\nVotre mot de passe : ". $valeurs_fiche['mot_de_passe_wikini'] . "\n\nA tres bientot ! \n\n";
            $headers =   'From: '.BAZ_ADRESSE_MAIL_ADMIN . "\r\n" .
                'Reply-To: '.BAZ_ADRESSE_MAIL_ADMIN . "\r\n" .
                'X-Mailer: PHP/' . phpversion();
            mail($valeurs_fiche['bf_mail'], remove_accents($objetmail), $messagemail, $headers);

            // ajout dans la liste de mail
            if (isset($valeurs_fiche[$tableau_template[5]]) && $valeurs_fiche[$tableau_template[5]]!='') {
                $headers =   'From: '.$valeurs_fiche['bf_mail'] . "\r\n" .
                'Reply-To: '. $valeurs_fiche['bf_mail'] . "\r\n" .
                'X-Mailer: PHP/' . phpversion();
                mail($valeurs_fiche[$tableau_template[5]], 'inscription a la liste de discussion', 'inscription', $headers);
            }
            return array('nomwiki' => $nomwiki);
        } else {
            return array('nomwiki' => $valeurs_fiche['nomwiki']);
        }
    } elseif ($mode == 'recherche') {

    } elseif ($mode == 'html') {

    }
}


/** inscriptionliste() - Permet de s'isncrire à une liste
 *
 * @param    mixed   L'objet QuickForm du formulaire
 * @param    mixed   Le tableau des valeurs des différentes option pour l'élément texte
 * @param    string  Type d'action pour le formulaire : saisie, modification, vue,... saisie par défaut
 * @return   void
 */
function inscriptionliste(&$formtemplate, $tableau_template, $mode, $valeurs_fiche)
{
    $id = str_replace(array('@','.'), array('',''),$tableau_template[1]);
    if ($mode == 'saisie') {

        $input_html = '<div class="control-group">
                    <div class="controls"> 
                        <div class="checkbox">
                          <input id="'.$id.'" type="checkbox"'.(isset($valeurs_fiche[$tableau_template[1]]) ? ' checked="checked"' : '').' value="'.$tableau_template[1].'" name="'.$id.'" class="element_checkbox">
                          <label for="'.$id.'">'.$tableau_template[2].'</label>
                        </div>
                    </div>
                </div>';
        $formtemplate->addElement('html', $input_html) ;   
    } elseif ($mode == 'requete') {
        //var_dump($_POST);
        //var_dump($valeurs_fiche);
        //break;
        include_once 'tools/contact/libs/contact.functions.php';
        if (isset($_POST[$id])) {
            send_mail($valeurs_fiche[$tableau_template[3]], $valeurs_fiche['bf_titre'], str_replace('@','-subscribe@',$tableau_template[1]), 'subscribe', 'subscribe', 'subscribe');
            $valeurs_fiche[$tableau_template[1]] = $tableau_template[1];
            return array($tableau_template[1] => $valeurs_fiche[$tableau_template[1]]);
        } 
        else {
            send_mail($valeurs_fiche[$tableau_template[3]], $valeurs_fiche['bf_titre'], str_replace('@','-unsubscribe@',$tableau_template[1]), 'unsubscribe', 'unsubscribe', 'unsubscribe');
            unset($valeurs_fiche[$tableau_template[1]]);
            return;
        }
    } elseif ($mode == 'recherche') {

    } elseif ($mode == 'html') {

    }
}



/** champs_cache() - Ajoute un élément caché au formulaire
 *
 * @param    mixed   L'objet QuickForm du formulaire
 * @param    mixed   Le tableau des valeurs des différentes option pour l'élément caché
 * @param    string  Type d'action pour le formulaire : saisie, modification, vue,... saisie par défaut
 * @param    mixed   Le tableau des valeurs de la fiche
 *
 * @return   void
 */
function champs_cache(&$formtemplate, $tableau_template, $mode, $valeurs_fiche)
{
    if ($mode == 'saisie') {
        $formtemplate->addElement('hidden', $tableau_template[1], $tableau_template[2], array ('id' => $tableau_template[1])) ;
        //gestion des valeurs par défaut
        $defs=array($tableau_template[1]=>$tableau_template[5]);
        $formtemplate->setDefaults($defs);
    } elseif ($mode == 'requete') {
        return array($tableau_template[1] => $valeurs_fiche[$tableau_template[1]]);
    } elseif ($mode == 'recherche') {

    } elseif ($mode == 'html') {

    }
}

/** champs_mail() - Ajoute un élément texte formaté comme un mail au formulaire
 *
 * @param    mixed   L'objet QuickForm du formulaire
 * @param    mixed   Le tableau des valeurs des différentes option pour l'élément texte
 * @param    string  Type d'action pour le formulaire : saisie, modification, vue,... saisie par défaut
 * @return   void
 */
function champs_mail(&$formtemplate, $tableau_template, $mode, $valeurs_fiche)
{
    list($type, $identifiant, $label, $nb_min_car, $nb_max_car, $valeur_par_defaut, $regexp, $type_input , $obligatoire, $sendmail, $bulle_d_aide) = $tableau_template;
    if ($mode == 'saisie') {
                // on prepare le html de la bulle d'aide, si elle existe
        if ($bulle_d_aide != '') {
            $bulledaide = '<img class="tooltip_aide" title="'.htmlentities($bulle_d_aide).'" src="tools/bazar/presentation/images/aide.png" width="16" height="16" alt="image aide" />';
        } else {
            $bulledaide = '';
        }

        //gestion des valeurs par defaut : d'abord on regarde s'il y a une valeur a modifier,
        //puis s'il y a une variable passee en GET,
        //enfin on prend la valeur par defaut du formulaire sinon
        if (isset($valeurs_fiche[$identifiant])) {
            $defauts = $valeurs_fiche[$identifiant];
        } elseif (isset($_GET[$identifiant])) {
            $defauts = stripslashes($_GET[$identifiant]);
        } else {
            $defauts = stripslashes($valeur_par_defaut);
        }

        //si la valeur de nb_max_car est vide, on la mets au maximum
        if ($nb_max_car == '') $nb_max_car = 255;

        //par defaut il s'agit d'input html de type "text" (on precise si cela n'a pas ete entre)
        if ($type_input == '') $type_input = 'email';

        $input_html  = '<div class="control-group">'."\n".'<div class="control-label">';
        $input_html .= ($obligatoire == 1) ? '<span class="symbole_obligatoire">*&nbsp;</span>' : '';
        $input_html .= $label.$bulledaide.' : </div>'."\n";
        $input_html .= '<div class="controls">'."\n";
        $input_html .= '<input type="'.$type_input.'"';
        $input_html .= ($defauts != '') ? ' value="'.$defauts.'"' : '';
        $input_html .= ' name="'.$identifiant.'" class="input_texte" id="'.$identifiant.'"';
        $input_html .= ' maxlength="'.$nb_max_car.'" size="'.$nb_max_car.'"';
        $input_html .= ($obligatoire == 1) ? ' required="required"' : '';
        $input_html .= '>'."\n".'</div>'."\n".'</div>'."\n";
        if ($sendmail == 1) {
            $formtemplate->addElement('hidden', 'sendmail', $identifiant);
        }
        $formtemplate->addElement('html', $input_html) ;
    } elseif ($mode == 'requete') {
        return array($tableau_template[1] => $valeurs_fiche[$tableau_template[1]]);

    } elseif ($mode == 'recherche') {

    } elseif ($mode == 'html') {
        $html = '';
        if (isset($valeurs_fiche[$tableau_template[1]]) && $valeurs_fiche[$tableau_template[1]]!='') {
            $html = '<div class="BAZ_rubrique">'."\n".
                    '<span class="BAZ_label">'.$tableau_template[2].'&nbsp;:</span>'."\n";
            $html .= '<span class="BAZ_texte"><a href="mailto:'.$valeurs_fiche[$tableau_template[1]].'" class="BAZ_lien_mail">';
            $html .= $valeurs_fiche[$tableau_template[1]].'</a></span>'."\n".'</div>'."\n";
        }

        return $html;
    }
}

/** mot_de_passe() - Ajoute un element de type mot de passe au formulaire
 *
 * @param    mixed   L'objet QuickForm du formulaire
 * @param    mixed   Le tableau des valeurs des différentes option pour l'élément mot de passe
 * @param    string  Type d'action pour le formulaire : saisie, modification, vue,... saisie par défaut
 * @return   void
 */
function mot_de_passe(&$formtemplate, $tableau_template, $mode, $valeurs_fiche)
{
    if ($mode == 'saisie') {
        $formtemplate->addElement('password', 'mot_de_passe', $tableau_template[2], array('size' => $tableau_template[3])) ;
        $formtemplate->addElement('password', 'mot_de_passe_repete', $tableau_template[7], array('size' => $tableau_template[3])) ;
        /*$formtemplate->addRule('mot_de_passe', $tableau_template[5], 'required', '', 'client') ;
          $formtemplate->addRule('mot_de_passe_repete', $tableau_template[5], 'required', '', 'client') ;
          $formtemplate->addRule(array ('mot_de_passe', 'mot_de_passe_repete'), $tableau_template[5], 'compare', '', 'client') ;*/
    } elseif ($mode == 'requete') {
        return array($tableau_template[1] => md5($valeurs_fiche['mot_de_passe'])) ;
    } elseif ($mode == 'recherche') {

    } elseif ($mode == 'html') {

    }
}


/** textelong() - Ajoute un élément de type texte long (textarea) au formulaire
 *
 * @param    mixed   L'objet QuickForm du formulaire
 * @param    mixed   Le tableau des valeurs des différentes option pour l'élément texte long
 * @param    string  Type d'action pour le formulaire : saisie, modification, vue,... saisie par défaut
 * @return   void
 */
function textelong(&$formtemplate, $tableau_template, $mode, $valeurs_fiche)
{
    list($type, $identifiant, $label, $nb_colonnes, $nb_lignes, $valeur_par_defaut, $longueurmax, $formatage , $obligatoire, $apparait_recherche, $bulle_d_aide) = $tableau_template;
    if ($mode == 'saisie') {
        $longueurmaxlabel = ($longueurmax ? ' (<span class="charsRemaining">'.$longueurmax.'</span> caract&egrave;res restants)' : '' );
        $bulledaide = '';
        if ($bulle_d_aide!='') $bulledaide = ' <img class="tooltip_aide" title="'.htmlentities($bulle_d_aide).'" src="tools/bazar/presentation/images/aide.png" width="16" height="16" alt="image aide" />';

        $options = array('id' => $identifiant, 'class' => 'input_textarea'.($formatage == 'wiki' ? ' wiki-textarea' : ''));
        if ($longueurmax != '') $options['maxlength'] = $longueurmax;
        //gestion du champs obligatoire
        $symb = '';
        if (isset($obligatoire) && $obligatoire==1) {
            $options['required'] = 'required' ;
            $symb .= '<span class="symbole_obligatoire">*&nbsp;</span>';
        }

        $formtexte= new HTML_QuickForm_textarea($identifiant, $symb.$label.$longueurmaxlabel.$bulledaide, $options);
        $formtexte->setCols($nb_colonnes);
        $formtexte->setRows($nb_lignes);
        $formtemplate->addElement($formtexte) ;

        //gestion des valeurs par defaut : d'abord on regarde s'il y a une valeur a modifier,
        //puis s'il y a une variable passee en GET,
        //enfin on prend la valeur par defaut du formulaire sinon
        if (isset($valeurs_fiche[$identifiant])) {
            $defauts = array( $identifiant => $valeurs_fiche[$identifiant] );
        } elseif (isset($_GET[$identifiant])) {
            $defauts = array( $identifiant => stripslashes($_GET[$identifiant]) );
        } else {
            $defauts = array( $identifiant => stripslashes($tableau_template[5]) );
        }
        $formtemplate->setDefaults($defauts);

        //$formtemplate->applyFilter($identifiant, 'addslashes') ;

        //gestion du champs obligatoire
        if (isset($obligatoire) && $obligatoire==1) {
            /*$formtemplate->addRule($identifiant,  $label.' obligatoire', 'required', '', 'client') ;*/
        }
    } elseif ($mode == 'requete') {
        return array($identifiant => $valeurs_fiche[$identifiant]);
    } elseif ($mode == 'html') {
        $html = '';
        if (isset($valeurs_fiche[$identifiant]) && $valeurs_fiche[$identifiant]!='') {
            $html = '<div class="BAZ_rubrique">'."\n".
                    '<span class="BAZ_label '.$identifiant.'_rubrique">'.$label.'&nbsp;:</span>'."\n";
            $html .= '<span class="BAZ_texte '.$identifiant.'_description"> ';
            if ($formatage == 'wiki') {
                $html .= $GLOBALS['wiki']->Format($valeurs_fiche[$identifiant]);
            } elseif ($formatage == 'nohtml') {
                $html .= htmlentities($valeurs_fiche[$identifiant]);
            } else {
                $html .= nl2br($valeurs_fiche[$identifiant]);
            }
            $html .= '</span>'."\n".'</div>'."\n";
        }

        return $html;
    }
}



/** url() - Ajoute un élément de type url internet au formulaire
 *
 * @param    mixed   L'objet QuickForm du formulaire
 * @param    mixed   Le tableau des valeurs des différentes option pour l'élément url internet
 * @param    string  Type d'action pour le formulaire : saisie, modification, vue,... saisie par défaut
 * @return   void
 */

/** lien_internet() - Ajoute un élément de type texte contenant une URL au formulaire
 *
 * @param    mixed   L'objet QuickForm du formulaire
 * @param    mixed   Le tableau des valeurs des différentes option pour l'élément texte url
 * @param    string  Type d'action pour le formulaire : saisie, modification, vue,... saisie par défaut
 * @return   void
 */
function lien_internet(&$formtemplate, $tableau_template, $mode, $valeurs_fiche)
{

    list($type, $identifiant, $label, $nb_min_car, $nb_max_car, $valeur_par_defaut, $regexp, $type_input , $obligatoire, , $bulle_d_aide) = $tableau_template;
    if ($mode == 'saisie') {
                // on prepare le html de la bulle d'aide, si elle existe
        if ($bulle_d_aide != '') {
            $bulledaide = '<img class="tooltip_aide" title="'.htmlentities($bulle_d_aide).'" src="tools/bazar/presentation/images/aide.png" width="16" height="16" alt="image aide" />';
        } else {
            $bulledaide = '';
        }

        //gestion des valeurs par defaut : d'abord on regarde s'il y a une valeur a modifier,
        //puis s'il y a une variable passee en GET,
        //enfin on prend la valeur par defaut du formulaire sinon
        if (isset($valeurs_fiche[$identifiant])) {
            $defauts = $valeurs_fiche[$identifiant];
        } elseif (isset($_GET[$identifiant])) {
            $defauts = stripslashes($_GET[$identifiant]);
        } else {
            $defauts = stripslashes($valeur_par_defaut);
        }

        //si la valeur de nb_max_car est vide, on la mets au maximum
        if ($nb_max_car == '') $nb_max_car = 255;

        //par defaut il s'agit d'input html de type "text" (on precise si cela n'a pas ete entre)
        if ($type_input == '') $type_input = 'url';

        $input_html  = '<div class="control-group">'."\n".'<div class="control-label">';
        $input_html .= ($obligatoire == 1) ? '<span class="symbole_obligatoire">*&nbsp;</span>' : '';
        $input_html .= $label.$bulledaide.' : </div>'."\n";
        $input_html .= '<div class="controls">'."\n";
        $input_html .= '<input type="'.$type_input.'"';
        $input_html .= ($defauts != 'http://') ? ' value="'.$defauts.'"' : ' placeholder="'.$defauts.'"';
        $input_html .= ' name="'.$identifiant.'" class="input_texte" id="'.$identifiant.'"';
        $input_html .= ($obligatoire == 1) ? ' required="required"' : '';
        $input_html .= '>'."\n".'</div>'."\n".'</div>'."\n";

        $formtemplate->addElement('html', $input_html) ;
    } elseif ($mode == 'requete') {
        //on supprime la valeur, si elle est restée par défaut
        if ($valeurs_fiche[$tableau_template[1]]!='http://') return array($tableau_template[1] => $valeurs_fiche[$tableau_template[1]]);
        else return;
    } elseif ($mode == 'html') {
        $html = '';
        if (isset($valeurs_fiche[$tableau_template[1]]) && $valeurs_fiche[$tableau_template[1]]!='') {
            $html .= '<div class="BAZ_rubrique">'."\n".
                     '<span class="BAZ_label">'.$tableau_template[2].'&nbsp;:</span>'."\n";
            $html .= '<span class="BAZ_texte">'."\n".
                     '<a href="'.$valeurs_fiche[$tableau_template[1]].'" class="BAZ_lien" target="_blank">';
            $html .= $valeurs_fiche[$tableau_template[1]].'</a></span>'."\n".'</div>'."\n";
        }

        return $html;
    }
}

/** fichier() - Ajoute un element de type fichier au formulaire
 *
 * @param    mixed   L'objet QuickForm du formulaire
 * @param    mixed   Le tableau des valeurs des différentes option pour l'élément fichier
 * @param    string  Type d'action pour le formulaire : saisie, modification, vue,... saisie par défaut
 * @return   void
 */
function fichier(&$formtemplate, $tableau_template, $mode, $valeurs_fiche)
{
    list($type, $identifiant, $label, $taille_maxi, $taille_maxi2, $hauteur, $largeur, $alignement, $obligatoire, $apparait_recherche, $bulle_d_aide) = $tableau_template;
    $option = array();
    if ($mode == 'saisie') {
        $label = ($obligatoire == 1) ? '<span class="symbole_obligatoire">*&nbsp;</span>'.$label : $label;
        if (isset($valeurs_fiche[$type.$identifiant]) && $valeurs_fiche[$type.$identifiant] != '') {
            if (isset($_GET['delete_file']) && $_GET['delete_file'] == $valeurs_fiche[$type.$identifiant] ) {
                if (baz_a_le_droit('supp_fiche', (isset($valeurs_fiche['createur']) ? $valeurs_fiche['createur'] : ''))) {
                    if (file_exists(BAZ_CHEMIN_UPLOAD.$valeurs_fiche[$type.$identifiant])) {
                        unlink(BAZ_CHEMIN_UPLOAD.$valeurs_fiche[$type.$identifiant]);
                    }
                } else {
                    $info = '<div class="alert alert-info">'.BAZ_DROIT_INSUFFISANT.'</div>'."\n";
                    require_once BAZ_CHEMIN.'libs'.DIRECTORY_SEPARATOR.'HTML/QuickForm/html.php';
                    $formtemplate->addElement(new HTML_QuickForm_html("\n".$info."\n")) ;
                }
            }
            if (file_exists(BAZ_CHEMIN_UPLOAD.$valeurs_fiche[$type.$identifiant])) {
                $lien_supprimer = $GLOBALS['wiki']->href( 'edit', $GLOBALS['wiki']->GetPageTag() );
                $lien_supprimer .= '&delete_file='.$valeurs_fiche[$type.$identifiant];

                $html = '<div class="control-group">
                    <div class="control-label">'.$label.' : </div>
                    <div class="controls">
                    <a href="'.BAZ_CHEMIN_UPLOAD.$valeurs_fiche[$type.$identifiant].'" target="_blank">'.$valeurs_fiche[$type.$identifiant].'</a>'."\n".
                    '<a href="'.str_replace('&', '&amp;', $lien_supprimer).'" onclick="javascript:return confirm(\''.BAZ_CONFIRMATION_SUPPRESSION_FICHIER.'\');" >'.BAZ_SUPPRIMER.'</a><br />
                    </div>
                    </div>';
                $formtemplate->addElement('html', $html) ;
                $formtemplate->addElement('hidden', $type.$identifiant, $valeurs_fiche[$type.$identifiant]);
            } else {
                if ($bulle_d_aide!='') $label = $label.' <img class="tooltip_aide" title="'.htmlentities($bulle_d_aide).'" src="tools/bazar/presentation/images/aide.png" width="16" height="16" alt="image aide" />';

                //gestion du champs obligatoire
                if (isset($obligatoire) && $obligatoire==1) {
                    $option = array('required' =>'required') ;
                }

                $formtemplate->addElement('file', $type.$identifiant, $label, $option) ;
            }
        } else {
            if ($bulle_d_aide!='') $label = $label.' <img class="tooltip_aide" title="'.htmlentities($bulle_d_aide).'" src="tools/bazar/presentation/images/aide.png" width="16" height="16" alt="image aide" />';

            //gestion du champs obligatoire
            if (isset($obligatoire) && $obligatoire==1) {
                $option = array('required' =>'required') ;
            }

            $formtemplate->addElement('file', $type.$identifiant, $label, $option) ;
        }
    } elseif ($mode == 'requete') {
        if (isset($_FILES[$type.$identifiant]['name']) && $_FILES[$type.$identifiant]['name']!='') {
            //on enleve les accents sur les noms de fichiers, et les espaces
            $nomfichier = preg_replace("/&([a-z])[a-z]+;/i","$1", htmlentities($identifiant.'_'.$_FILES[$type.$identifiant]['name']));
            $nomfichier = str_replace(' ', '_', $nomfichier);
            $chemin_destination=BAZ_CHEMIN_UPLOAD.$nomfichier;
            //verification de la presence de ce fichier
            $extension=obtenir_extension($nomfichier);
            if ($extension!='' && extension_autorisee($extension)==true) {
                if (!file_exists($chemin_destination)) {
                    move_uploaded_file($_FILES[$type.$identifiant]['tmp_name'], $chemin_destination);
                    chmod ($chemin_destination, 0755);
                } else echo 'fichier déja existant<br />';
            } else {
                echo 'fichier non autorise<br />';

                return array($type.$identifiant => '');
            }

            return array($type.$identifiant => $nomfichier);
        } elseif (isset($_POST[$type.$identifiant]) && file_exists(BAZ_CHEMIN_UPLOAD.$_POST[$type.$identifiant]) ) {
            return array($type.$identifiant => $_POST[$type.$identifiant]);
        } else {
            return array($type.$identifiant => '');
        }





    } elseif ($mode == 'recherche') {

    } elseif ($mode == 'html') {
        $html = '';
        if (isset($valeurs_fiche[$type.$identifiant]) && $valeurs_fiche[$type.$identifiant]!='') {
            $html = '<div class="BAZ_fichier">T&eacute;l&eacute;charger le fichier : <a href="'.BAZ_CHEMIN_UPLOAD.$valeurs_fiche[$type.$identifiant].'" target="_blank">'.$valeurs_fiche[$type.$identifiant].'</a>'."\n";
        }
        if ($html!='') $html .= '</div>'."\n";

        return $html;
    }
}


/** image() - Ajoute un element de type image au formulaire
 *
 * @param    mixed   L'objet QuickForm du formulaire
 * @param    mixed   Le tableau des valeurs des differentes option pour l'element image
 * @param    string  Type d'action pour le formulaire : saisie, modification, vue,... saisie par défaut
 * @return   void
 */
function image(&$formtemplate, $tableau_template, $mode, $valeurs_fiche)
{
    list($type, $identifiant, $label, $hauteur_vignette, $largeur_vignette, $hauteur_image, $largeur_image, $class, $obligatoire, $apparait_recherche, $bulle_d_aide) = $tableau_template;

    if ($mode == 'saisie') {
        $label = ($obligatoire == 1) ? '<span class="symbole_obligatoire">*&nbsp;</span>'.$label : $label;
        //on verifie qu'il ne faut supprimer l'image
        if (isset($_GET['suppr_image']) && isset($valeurs_fiche[$type.$identifiant]) && $valeurs_fiche[$type.$identifiant]==$_GET['suppr_image']) {
            if (baz_a_le_droit('supp_fiche', (isset($valeurs_fiche['createur']) ? $valeurs_fiche['createur'] : ''))) {
                //on efface le fichier s'il existe
                if (file_exists(BAZ_CHEMIN_UPLOAD.$valeurs_fiche[$type.$identifiant])) {
                    unlink(BAZ_CHEMIN_UPLOAD.$valeurs_fiche[$type.$identifiant]);
                }
                $nomimg = $valeurs_fiche[$type.$identifiant];
                //on efface une entrée de la base de données
                unset($valeurs_fiche[$type.$identifiant]);
                $valeur = $valeurs_fiche;
                $valeur['date_maj_fiche'] = date( 'Y-m-d H:i:s', time() );
                $valeur['id_fiche'] = $GLOBALS['_BAZAR_']['id_fiche'];
                $valeur = json_encode(array_map("utf8_encode", $valeur));
                //on sauve les valeurs d'une fiche dans une PageWiki, pour garder l'historique
                $GLOBALS["wiki"]->SavePage($GLOBALS['_BAZAR_']['id_fiche'], $valeur);

                //on affiche les infos sur l'effacement du fichier, et on reinitialise la variable pour le fichier pour faire apparaitre le formulaire d'ajout par la suite
                $info = '<div class="alert alert-info">'.BAZ_FICHIER.$nomimg.BAZ_A_ETE_EFFACE.'</div>'."\n";
                require_once BAZ_CHEMIN.'libs'.DIRECTORY_SEPARATOR.'vendor/HTML/QuickForm/html.php';
                $formtemplate->addElement(new HTML_QuickForm_html("\n".$info."\n")) ;
                $valeurs_fiche[$type.$identifiant] = '';
            } else {
                $info = '<div class="alert">'.BAZ_DROIT_INSUFFISANT.'</div>'."\n";
                require_once BAZ_CHEMIN.'libs'.DIRECTORY_SEPARATOR.'vendor/HTML/QuickForm/html.php';
                $formtemplate->addElement(new HTML_QuickForm_html("\n".$info."\n")) ;
            }
        }

        if ($bulle_d_aide!='') $label = $label.' <img class="tooltip_aide" title="'.htmlentities($bulle_d_aide).'" src="tools/bazar/presentation/images/aide.png" width="16" height="16" alt="image aide" />';

        //cas ou il y a une image dans la base de donnees
        if (isset($valeurs_fiche[$type.$identifiant]) && $valeurs_fiche[$type.$identifiant] != '') {

            //il y a bien le fichier image, on affiche l'image, avec possibilite de la supprimer ou de la modifier
            if (file_exists(BAZ_CHEMIN_UPLOAD.$valeurs_fiche[$type.$identifiant])) {

                require_once BAZ_CHEMIN.'libs'.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'HTML/QuickForm/html.php';
                $formtemplate->addElement(new HTML_QuickForm_html("\n".'<fieldset class="bazar_fieldset">'."\n".'<legend>'.$label.'</legend>'."\n")) ;

                $lien_supprimer = $GLOBALS['wiki']->href( 'edit', $GLOBALS['wiki']->GetPageTag() );
                $lien_supprimer .= '&suppr_image='.$valeurs_fiche[$type.$identifiant];

                $html_image = afficher_image($valeurs_fiche[$type.$identifiant], $label, '', $largeur_vignette, $hauteur_vignette, $largeur_image, $hauteur_image);
                $lien_supprimer_image = '<a class="btn btn-danger btn-mini" href="'.str_replace('&', '&amp;', $lien_supprimer).'" onclick="javascript:return confirm(\''.
                    BAZ_CONFIRMATION_SUPPRESSION_IMAGE.'\');" ><i class="icon-trash icon-white"></i>&nbsp;'.BAZ_SUPPRIMER_IMAGE.'</a>'."\n";
                if ($html_image!='') $formtemplate->addElement('html', $html_image) ;
                //gestion du champs obligatoire
                $option = '';
                $formtemplate->addElement('file', $type.$identifiant, $lien_supprimer_image.BAZ_MODIFIER_IMAGE, $option) ;
                $formtemplate->addElement('hidden', 'oldimage_'.$type.$identifiant, $valeurs_fiche[$type.$identifiant]) ;
                $formtemplate->addElement(new HTML_QuickForm_html("\n".'</fieldset>'."\n")) ;
            }

            //le fichier image n'existe pas, du coup on efface l'entree dans la base de donnees
            else {
                echo '<div class="alert alert-danger">'.BAZ_FICHIER.$valeurs_fiche[$type.$identifiant].BAZ_FICHIER_IMAGE_INEXISTANT.'</div>'."\n";
                //on efface une entrée de la base de données
                unset($valeurs_fiche[$type.$identifiant]);
                $valeur = $valeurs_fiche;
                $valeur['date_maj_fiche'] = date( 'Y-m-d H:i:s', time() );
                $valeur['id_fiche'] = $GLOBALS['_BAZAR_']['id_fiche'];
                $valeur = json_encode(array_map("utf8_encode", $valeur));
                //on sauve les valeurs d'une fiche dans une PageWiki, pour garder l'historique
                $GLOBALS["wiki"]->SavePage($GLOBALS['_BAZAR_']['id_fiche'], $valeur);
            }
        }
        //cas ou il n'y a pas d'image dans la base de donnees, on affiche le formulaire d'envoi d'image
        else {
            //gestion du champs obligatoire
            $option = '';
            if (isset($obligatoire) && $obligatoire==1) {
                $option = array('required' =>'required') ;
            }
            $formtemplate->addElement('file', $type.$identifiant, $label, $option) ;
            //gestion du champs obligatoire
            if (isset($obligatoire) && $obligatoire==1) {
                /*$formtemplate->addRule('image', IMAGE_VALIDE_REQUIS, 'required', '', 'client') ;*/
            }

            //TODO: la verification du type de fichier ne marche pas
            $tabmime = array ('gif' => 'image/gif', 'jpg' => 'image/jpeg', 'png' => 'image/png');
            /*$formtemplate->addRule($type.$identifiant, 'Vous devez choisir une fichier de type image gif, jpg ou png', 'mimetype', $tabmime );*/
        }
    } elseif ($mode == 'requete') {
        if (isset($_FILES[$type.$identifiant]['name']) && $_FILES[$type.$identifiant]['name']!='') {

            //on enleve les accents sur les noms de fichiers, et les espaces
            $nomimage = preg_replace("/&([a-z])[a-z]+;/i","$1", htmlentities($identifiant.$_FILES[$type.$identifiant]['name']));
            $nomimage = str_replace(' ', '_', $nomimage);
            if (preg_match("/(gif|jpeg|png|jpg)$/i",$nomimage)) {
                $chemin_destination = BAZ_CHEMIN_UPLOAD.$nomimage;
                //verification de la presence de ce fichier
                if (!file_exists($chemin_destination)) {
                    move_uploaded_file($_FILES[$type.$identifiant]['tmp_name'], $chemin_destination);
                    chmod ($chemin_destination, 0755);
                    //generation des vignettes
                    if ($hauteur_vignette!='' && $largeur_vignette!='' && !file_exists('cache/vignette_'.$nomimage)) {
                        $adr_img = redimensionner_image($chemin_destination, 'cache/vignette_'.$nomimage, $largeur_vignette, $hauteur_vignette);
                    }
                    //generation des images
                    if ($hauteur_image!='' && $largeur_image!='' && !file_exists('cache/image_'.'_'.$nomimage)) {
                        $adr_img = redimensionner_image($chemin_destination, 'cache/image_'.$nomimage, $largeur_image, $hauteur_image);
                    }
                } else {
                    echo '<div class="alert alert-danger">L\'image '.$nomimage.' existait d&eacute;ja, elle n\'a pas &eacute;t&eacute; remplac&eacute;e.</div>';
                }
            } else {
                echo '<div class="alert alert-danger">Fichier non autoris&eacute;.</div>';
            }

            return array($type.$identifiant => $nomimage);
        } 
        elseif (isset($valeurs_fiche['oldimage_'.$type.$identifiant]) && $valeurs_fiche['oldimage_'.$type.$identifiant] != '') {
            return array($type.$identifiant => $valeurs_fiche['oldimage_'.$type.$identifiant]);
        } 
    } elseif ($mode == 'recherche') {

    } elseif ($mode == 'html') {
        if (isset($valeurs_fiche[$type.$identifiant]) && $valeurs_fiche[$type.$identifiant]!='' && file_exists(BAZ_CHEMIN_UPLOAD.$valeurs_fiche[$type.$identifiant]) ) {
            return afficher_image($valeurs_fiche[$type.$identifiant], $label, $class, $largeur_vignette, $hauteur_vignette, $largeur_image, $hauteur_image);
        }
    }
}

/** labelhtml() - Ajoute du texte HTML au formulaire
 *
 * @param    mixed   L'objet QuickForm du formulaire
 * @param    mixed   Le tableau des valeurs des différentes option pour le texte HTML
 * @param    string  Type d'action pour le formulaire : saisie, modification, vue,... saisie par défaut
 * @return   void
 */
function labelhtml(&$formtemplate, $tableau_template, $mode, $valeurs_fiche)
{
    list($type, $texte_saisie, $texte_recherche, $texte_fiche) = $tableau_template;

    if ($mode == 'saisie') {
        require_once BAZ_CHEMIN.'libs'.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'HTML/QuickForm/html.php';
        $formtemplate->addElement(new HTML_QuickForm_html("\n".$texte_saisie."\n")) ;
    } elseif ($mode == 'requete') {
        return;
    } elseif ($mode == 'formulaire_recherche') {
        $formtemplate->addElement('html', $texte_recherche);
    } elseif ($mode == 'html') {
        return $texte_fiche."\n";
    }
}

/** titre() - Action qui camouffle le titre et le génére a  partir d'autres champs au formulaire
 *
 * @param    mixed   L'objet QuickForm du formulaire
 * @param    mixed   Le tableau des valeurs des différentes option pour le texte HTML
 * @param    string  Type d'action pour le formulaire : saisie, modification, vue,... saisie par défaut
 * @return   void
 */
function titre(&$formtemplate, $tableau_template, $mode, $valeurs_fiche)
{
    list($type, $template) = $tableau_template;

    if ($mode == 'saisie') {
        $formtemplate->addElement('hidden', 'bf_titre', $template, array ('id' => 'bf_titre')) ;
    } elseif ($mode == 'requete') {
        preg_match_all  ('#{{(.*)}}#U'  , $_POST['bf_titre']  , $matches);
        $tab = array();
        foreach ($matches[1] as $var) {
            if (isset($_POST[$var])) {
                //pour une listefiche ou une checkboxfiche on cherche le titre de la fiche
                if ( preg_match('#^listefiche#',$var)!=false || preg_match('#^checkboxfiche#',$var)!=false ) {
                    $tab_fiche = baz_valeurs_fiche($_POST[$var]);
                    $_POST['bf_titre'] = str_replace('{{'.$var.'}}', ($tab_fiche['bf_titre']!=null) ? $tab_fiche['bf_titre'] : '', $_POST['bf_titre']);
                }
                //sinon on prend le label de la liste
                elseif ( preg_match('#^liste#',$var)!=false || preg_match('#^checkbox#',$var)!=false ) {
                    //on récupere le premier chiffre (l'identifiant de la liste)
                    preg_match_all('/[0-9]{1,4}/', $var, $matches);
                    $req = 'SELECT blv_label FROM '.BAZ_PREFIXE.'liste_valeurs WHERE blv_ce_liste='.$matches[0][0].' AND blv_valeur='.$_POST[$var].' AND blv_ce_i18n="fr-FR"';
                    $resultat = $GLOBALS['_BAZAR_']['db']->query($req) ;
                    $label = $resultat->fetchRow();
                    $_POST['bf_titre'] = str_replace('{{'.$var.'}}', ($label[0]!=null) ? $label[0] : '', $_POST['bf_titre']);
                } else {
                    $_POST['bf_titre'] = str_replace('{{'.$var.'}}', $_POST[$var], $_POST['bf_titre']);
                }
            }
        }
        $GLOBALS['_BAZAR_']['id_fiche'] = (isset($valeurs_fiche['id_fiche']) ? $valeurs_fiche['id_fiche'] : genere_nom_wiki($_POST['bf_titre']));
        return array('bf_titre' => $_POST['bf_titre'], 'id_fiche' => $GLOBALS['_BAZAR_']['id_fiche']);
    } elseif ($mode == 'html') {
        // Le titre
        return '<h1 class="BAZ_fiche_titre">'.htmlentities($valeurs_fiche['bf_titre']).'</h1>'."\n";
    } elseif ($mode == 'formulaire_recherche') {
        return;
    }
}

/** carte_google() - Ajoute un élément de carte google au formulaire
 *
 * @param    mixed   L'objet QuickForm du formulaire
 * @param    mixed   Le tableau des valeurs des différentes option pour la carte google
 * @param    string  Type d'action pour le formulaire : saisie, modification, vue,... saisie par défaut
 * @return   void
 */
function carte_google(&$formtemplate, $tableau_template, $mode, $valeurs_fiche)
{
    list($type, $lat, $lon, $classe, $obligatoire) = $tableau_template;

    if ($mode == 'saisie') {
        $scriptgoogle = '
//-----------------------------------------------------------------------------------------------------------
//--------------------TODO : ATTENTION CODE FACTORISABLE-----------------------------------------------------
//-----------------------------------------------------------------------------------------------------------
var geocoder;
var map;
var marker;
var infowindow;

function initialize()
{
    geocoder = new google.maps.Geocoder();
    var myLatlng = new google.maps.LatLng('.BAZ_GOOGLE_CENTRE_LAT.', '.BAZ_GOOGLE_CENTRE_LON.');
    var myOptions = {
      zoom: '.BAZ_GOOGLE_ALTITUDE.',
      center: myLatlng,
      mapTypeId: google.maps.MapTypeId.'.BAZ_TYPE_CARTO.',
      navigationControl: '.BAZ_AFFICHER_NAVIGATION.',
      navigationControlOptions: {style: google.maps.NavigationControlStyle.'.BAZ_STYLE_NAVIGATION.'},
      mapTypeControl: '.BAZ_AFFICHER_CHOIX_CARTE.',
      mapTypeControlOptions: {style: google.maps.MapTypeControlStyle.'.BAZ_STYLE_CHOIX_CARTE.'},
      scaleControl: '.BAZ_AFFICHER_ECHELLE.' ,
      scrollwheel: '.BAZ_PERMETTRE_ZOOM_MOLETTE.'
    }
    map = new google.maps.Map(document.getElementById("map"), myOptions);

    //on pose un point si les coordonnées existent déja (cas d\'une modification de fiche)
    if (document.getElementById("latitude") && document.getElementById("latitude").value != \'\' &&
        document.getElementById("longitude") && document.getElementById("longitude").value != \'\' ) {
        var lat = document.getElementById("latitude").value;
        var lon = document.getElementById("longitude").value;
        latlngclient = new google.maps.LatLng(lat,lon);
        map.setCenter(latlngclient);
        infowindow = new google.maps.InfoWindow({
        content: "<h4>Votre emplacement<\/h4>'.TEXTE_POINT_DEPLACABLE.'",
        maxWidth: 250
        });
        //image du marqueur
        var image = new google.maps.MarkerImage(\''.BAZ_IMAGE_MARQUEUR.'\',
        //taille, point d\'origine, point d\'arrivee de l\'image
        new google.maps.Size('.BAZ_DIMENSIONS_IMAGE_MARQUEUR.'),
        new google.maps.Point('.BAZ_COORD_ORIGINE_IMAGE_MARQUEUR.'),
        new google.maps.Point('.BAZ_COORD_ARRIVEE_IMAGE_MARQUEUR.'));

        //ombre du marqueur
        var shadow = new google.maps.MarkerImage(\''.BAZ_IMAGE_OMBRE_MARQUEUR.'\',
        // taille, point d\'origine, point d\'arrivee de l\'image de l\'ombre
        new google.maps.Size('.BAZ_DIMENSIONS_IMAGE_OMBRE_MARQUEUR.'),
        new google.maps.Point('.BAZ_COORD_ORIGINE_IMAGE_OMBRE_MARQUEUR.'),
        new google.maps.Point('.BAZ_COORD_ARRIVEE_IMAGE_OMBRE_MARQUEUR.'));

    marker = new google.maps.Marker({
    position: latlngclient,
    map: map,
    icon: image,
    shadow: shadow,
    title: \'Votre emplacement\',
    draggable: true
    });
    infowindow.open(map,marker);
    google.maps.event.addListener(marker, \'click\', function() {
            infowindow.open(map,marker);
            });
    google.maps.event.addListener(marker, "dragend", function () {
            var lat = document.getElementById("latitude");lat.value = marker.getPosition().lat();
            var lon = document.getElementById("longitude");lon.value = marker.getPosition().lng();
            map.setCenter(marker.getPosition());
            });
    }
};

function showClientAddress()
{
    // If ClientLocation was filled in by the loader, use that info instead
    if (google.loader.ClientLocation) {
        latlngclient = new google.maps.LatLng(google.loader.ClientLocation.latitude, google.loader.ClientLocation.longitude);
        if (infowindow) {
            infowindow.close();
        }
        if (marker) {
            marker.setMap(null);
        }
        map.setCenter(latlngclient);
        var lat = document.getElementById("latitude");lat.value = map.getCenter().lat();
        var lon = document.getElementById("longitude");lon.value = map.getCenter().lng();

        infowindow = new google.maps.InfoWindow({
content: "<h4>Votre emplacement<\/h4>'.TEXTE_POINT_DEPLACABLE.'",
maxWidth: 250
});
//image du marqueur
var image = new google.maps.MarkerImage(\''.BAZ_IMAGE_MARQUEUR.'\',
        //taille, point d\'origine, point d\'arrivee de l\'image
        new google.maps.Size('.BAZ_DIMENSIONS_IMAGE_MARQUEUR.'),
        new google.maps.Point('.BAZ_COORD_ORIGINE_IMAGE_MARQUEUR.'),
        new google.maps.Point('.BAZ_COORD_ARRIVEE_IMAGE_MARQUEUR.'));

//ombre du marqueur
var shadow = new google.maps.MarkerImage(\''.BAZ_IMAGE_OMBRE_MARQUEUR.'\',
        // taille, point d\'origine, point d\'arrivee de l\'image de l\'ombre
        new google.maps.Size('.BAZ_DIMENSIONS_IMAGE_OMBRE_MARQUEUR.'),
        new google.maps.Point('.BAZ_COORD_ORIGINE_IMAGE_OMBRE_MARQUEUR.'),
        new google.maps.Point('.BAZ_COORD_ARRIVEE_IMAGE_OMBRE_MARQUEUR.'));

marker = new google.maps.Marker({
position: latlngclient,
map: map,
icon: image,
shadow: shadow,
title: \'Votre emplacement\',
draggable: true
});
infowindow.open(map,marker);
google.maps.event.addListener(marker, \'click\', function() {
        infowindow.open(map,marker);
        });
google.maps.event.addListener(marker, "dragend", function () {
        var lat = document.getElementById("latitude");lat.value = marker.getPosition().lat();
        var lon = document.getElementById("longitude");lon.value = marker.getPosition().lng();
        map.setCenter(marker.getPosition());
        });
} else {alert("Localisation par votre acces Internet impossible...");}
};

function showAddress()
{
    if (document.getElementById("bf_adresse1")) 	var adress_1 = document.getElementById("bf_adresse1").value ; else var adress_1 = "";
    if (document.getElementById("bf_adresse2")) 	var adress_2 = document.getElementById("bf_adresse2").value ; else var adress_2 = "";
    if (document.getElementById("bf_ville")) 	var ville = document.getElementById("bf_ville").value ; else var ville = "";
    if (document.getElementById("bf_code_postal")) var cp = document.getElementById("bf_code_postal").value ; else var cp = "";
    if (document.getElementById("listeListePays")) var pays = document.getElementById("listeListePays").value ; else
        if (document.getElementById("liste3")) {
            var selectIndex=document.getElementById("liste3").selectedIndex;
            var pays = document.getElementById("liste3").options[selectIndex].text ;
        } else {
            var pays = "";
        };



    var address = adress_1 + \' \' + adress_2 + \' \'  + cp + \' \' + ville + \' \' +pays ;
    address = address.replace(/\\("|\'|\\)/g, " ");
    if (geocoder) {
        geocoder.geocode( { \'address\': address}, function(results, status) {
                if (status == google.maps.GeocoderStatus.OK) {
                if (status != google.maps.GeocoderStatus.ZERO_RESULTS) {
                if (infowindow) {
                infowindow.close();
                }
                if (marker) {
                marker.setMap(null);
                }
                map.setCenter(results[0].geometry.location);
                var lat = document.getElementById("latitude");lat.value = map.getCenter().lat();
                var lon = document.getElementById("longitude");lon.value = map.getCenter().lng();

                infowindow = new google.maps.InfoWindow({
                    content: "<h4>Votre emplacement<\/h4>'.TEXTE_POINT_DEPLACABLE.'",
                    maxWidth: 250
                });
                //image du marqueur
                var image = new google.maps.MarkerImage(\''.BAZ_IMAGE_MARQUEUR.'\',
                    //taille, point d\'origine, point d\'arrivee de l\'image
                    new google.maps.Size('.BAZ_DIMENSIONS_IMAGE_MARQUEUR.'),
                    new google.maps.Point('.BAZ_COORD_ORIGINE_IMAGE_MARQUEUR.'),
                    new google.maps.Point('.BAZ_COORD_ARRIVEE_IMAGE_MARQUEUR.'));

        //ombre du marqueur
        var shadow = new google.maps.MarkerImage(\''.BAZ_IMAGE_OMBRE_MARQUEUR.'\',
        // taille, point d\'origine, point d\'arrivee de l\'image de l\'ombre
        new google.maps.Size('.BAZ_DIMENSIONS_IMAGE_OMBRE_MARQUEUR.'),
        new google.maps.Point('.BAZ_COORD_ORIGINE_IMAGE_OMBRE_MARQUEUR.'),
        new google.maps.Point('.BAZ_COORD_ARRIVEE_IMAGE_OMBRE_MARQUEUR.'));

        marker = new google.maps.Marker({
            position: results[0].geometry.location,
            map: map,
            icon: image,
            shadow: shadow,
            title: \'Votre emplacement\',
            draggable: true
        });
        infowindow.open(map,marker);
        google.maps.event.addListener(marker, \'click\', function() {
            infowindow.open(map,marker);
        });
        google.maps.event.addListener(marker, "dragend", function () {
            var lat = document.getElementById("latitude");lat.value = marker.getPosition().lat();
            var lon = document.getElementById("longitude");lon.value = marker.getPosition().lng();
            map.setCenter(marker.getPosition());
        });
} else {
    alert("Pas de resultats pour cette adresse: " + address);
}
} else {
    alert("Pas de resultats pour la raison suivante: " + status + ", rechargez la page.");
}
});
}
};';
if ( defined('BAZ_JS_INIT_MAP') && BAZ_JS_INIT_MAP != '' && file_exists(BAZ_JS_INIT_MAP) ) {
    $handle = fopen(BAZ_JS_INIT_MAP, "r");
    $scriptgoogle .= fread($handle, filesize(BAZ_JS_INIT_MAP));
    fclose($handle);
    $scriptgoogle .= 'var poly = createPolygon( Coords, "#002F0F");
    poly.setMap(map);

    ';
};
$GLOBALS['js'] = (isset($GLOBALS['js']) ? $GLOBALS['js'] : '').'<script src="http://maps.google.com/maps/api/js?sensor=false"></script>'."\n".
'<script src="http://www.google.com/jsapi"></script>'."\n".
'<script>
//<![CDATA[
'.$scriptgoogle.'
//]]>
</script>';
    $deflat = ''; $deflon = '';
    if (isset($valeurs_fiche['carte_google'])) {
        $tab = explode('|', $valeurs_fiche['carte_google']);
        if (count($tab)>1) {
            $deflat = ' value="'.$tab[0].'"';
            $deflon = ' value="'.$tab[1].'"';
        }
    }
    $required = (($obligatoire == 1) ? ' required="required"' : '' );
    $symbole_obligatoire = ($obligatoire == 1) ? '<span class="symbole_obligatoire">*&nbsp;</span>' : '';

    $formtemplate->addElement('html', 
        $symbole_obligatoire.'
        <input class="btn btn-primary btn_adresse" onclick="showAddress();" name="chercher_sur_carte" value="'.VERIFIER_MON_ADRESSE.'" type="button" />
        <input class="btn btn_client" onclick="showClientAddress();" name="chercher_client" value="'.VERIFIER_MON_ADRESSE_CLIENT.'" type="button" />
        <div class="form-inline pull-right">'."\n".
            'Lat : <input type="text" name="'.$lat.'" class="input-mini" id="latitude"'.$deflat.$required.' />'."\n".
            'Lon : <input type="text" name="'.$lon.'" class="input-mini" id="longitude"'.$deflon.$required.' />'."\n".
        '</div>'."\n".
        '<div id="map" style="clear:right; margin-top:8px; width: '.BAZ_GOOGLE_IMAGE_LARGEUR.'; height: '.BAZ_GOOGLE_IMAGE_HAUTEUR.';"></div>');

    } elseif ($mode == 'requete') {
        return array('carte_google' => $valeurs_fiche[$lat].'|'.$valeurs_fiche[$lon]);
    } elseif ($mode == 'recherche') {

    } elseif ($mode == 'html') {

    }

}

/** listefiche() - Ajoute un element de type liste deroulante correspondant a un autre type de fiche au formulaire
 *
 * @param    mixed   L'objet QuickForm du formulaire
 * @param    mixed   Le tableau des valeurs des différentes option pour l'element liste
 * @param    string  Type d'action pour le formulaire : saisie, modification, vue,... saisie par defaut
 * @return   void
 */
function listefiche(&$formtemplate, $tableau_template, $mode, $valeurs_fiche)
{
    if ($mode=='saisie') {
        $bulledaide = '';
        if (isset($tableau_template[10]) && $tableau_template[10]!='') {
            $bulledaide = ' <img class="tooltip_aide" title="'.htmlentities($tableau_template[10]).'" src="tools/bazar/presentation/images/aide.png" width="16" height="16" alt="image aide" />';
        }

        $select_html = '<div class="control-group">'."\n".'<div class="control-label">'."\n";
        if (isset($tableau_template[8]) && $tableau_template[8]==1) {
            $select_html .= '<span class="symbole_obligatoire">*&nbsp;</span>'."\n";
        }
        $select_html .= $tableau_template[2].$bulledaide.' : </div>'."\n".'<div class="controls">'."\n".'<select';

        $select_attributes = '';

        if ($tableau_template[4] != '' && $tableau_template[4] > 1) {
            $select_attributes .= ' multiple="multiple" size="'.$tableau_template[4].'"';
            $selectnametab = '[]';
        } else {
            $selectnametab = '';
        }

        $select_attributes .= ' class="bazar-select" id="'.$tableau_template[0].$tableau_template[1].$tableau_template[6].'" name="'.$tableau_template[0].$tableau_template[1].$tableau_template[6].$selectnametab.'"';


        if (isset($tableau_template[8]) && $tableau_template[8]==1) {
            $select_attributes .= ' required="required"';
        }
        $select_html .= $select_attributes.'>'."\n";

        if (isset($valeurs_fiche[$tableau_template[0].$tableau_template[1].$tableau_template[6]]) && $valeurs_fiche[$tableau_template[0].$tableau_template[1].$tableau_template[6]]!='') {
            $def =	$valeurs_fiche[$tableau_template[0].$tableau_template[1].$tableau_template[6]];
        } else {
            $def = $tableau_template[5];
        }

        /*$valliste = baz_valeurs_liste($tableau_template[1]);*/
        if ($def=='' && ($tableau_template[4] == '' || $tableau_template[4] <= 1 ) || $def==0) {
            $select_html .= '<option value="0" selected="selected">'.BAZ_CHOISIR.'</option>'."\n";
        }
        $val_type = baz_valeurs_type_de_fiche($tableau_template[1]);
        $tab_result = baz_requete_recherche_fiches('', 'alphabetique', $tableau_template[1], $val_type["bn_type_fiche"]);
        $select = '';
        foreach ($tab_result as $fiche) {
            $valeurs_fiche_liste = json_decode($fiche[0], true);
            $valeurs_fiche_liste = array_map('utf8_decode', $valeurs_fiche_liste);
            $select[$valeurs_fiche_liste['id_fiche']] = $valeurs_fiche_liste['bf_titre'] ;
        }
        if (is_array($select)) {
            foreach ($select as $key => $label) {
                $select_html .= '<option value="'.$key.'"';
                if ($def != '' && strstr($key, $def)) $select_html .= ' selected="selected"';
                $select_html .= '>'.$label.'</option>'."\n";
            }

        }

        $select_html .= "</select>\n</div>\n</div>\n";

        $formtemplate->addElement('html', $select_html) ;
    } elseif ($mode == 'requete') {
        if (isset($valeurs_fiche[$tableau_template[0].$tableau_template[1].$tableau_template[6]]) && ($valeurs_fiche[$tableau_template[0].$tableau_template[1].$tableau_template[6]]!=0)) {
            return array($tableau_template[0].$tableau_template[1].$tableau_template[6] => $valeurs_fiche[$tableau_template[0].$tableau_template[1].$tableau_template[6]]);
        }
    } elseif ($mode == 'formulaire_recherche') {
        if ($tableau_template[9]==1) {
            $tab_result = baz_requete_recherche_fiches('', $tri = 'alphabetique', $tableau_template[1], '');
            $select[0] = BAZ_INDIFFERENT;
            foreach ($tab_result as $fiche) {
                $valeurs_fiche = json_decode($fiche[0], true);
                $valeurs_fiche = array_map('utf8_decode', $valeurs_fiche);
                $select[$valeurs_fiche['id_fiche']] = $valeurs_fiche['bf_titre'] ;
            }
            $option = array('id' => $tableau_template[0].$tableau_template[1].$tableau_template[6]);
            require_once 'HTML/QuickForm/select.php';
            $select= new HTML_QuickForm_select($tableau_template[0].$tableau_template[1].$tableau_template[6], $tableau_template[2], $select, $option);
            if ($tableau_template[4] != '') $select->setSize($tableau_template[4]);
            $select->setMultiple(0);
            $formtemplate->addElement($select) ;
        }
    } elseif ($mode == 'html') {
        $html = '';
        if (isset($valeurs_fiche[$tableau_template[0].$tableau_template[1].$tableau_template[6]]) && $valeurs_fiche[$tableau_template[0].$tableau_template[1].$tableau_template[6]]!='') {

            if ($tableau_template[3] == 'fiche') {
                $html = baz_voir_fiche(0, $valeurs_fiche[$tableau_template[0].$tableau_template[1].$tableau_template[6]]);
            } else {
                $html = '<div class="BAZ_rubrique  BAZ_rubrique_'.$GLOBALS['_BAZAR_']['class'].'">'."\n".
                        '<span class="BAZ_label '.$tableau_template[2].'_rubrique">'.$tableau_template[2].'&nbsp;:</span>'."\n";
                $html .= '<span class="BAZ_texte BAZ_texte_'.$GLOBALS['_BAZAR_']['class'].' '.$tableau_template[2].'_description">';
                $val_fiche = baz_valeurs_fiche($valeurs_fiche[$tableau_template[0].$tableau_template[1].$tableau_template[6]]);
                $html .= '<a href="'.str_replace('&', '&amp;', $GLOBALS['wiki']->href('', $valeurs_fiche[$tableau_template[0].$tableau_template[1].$tableau_template[6]])).'" class="voir_fiche ouvrir_overlay" title="Voir la fiche '.
                        $val_fiche['bf_titre'].'" rel="#overlay-link">'.
                        $val_fiche['bf_titre'].'</a></span>'."\n".
                        '</div>'."\n";

            }
        }

        return $html;
    }
} //fin listefiche()


/** checkboxfiche() - permet d'aller saisir et modifier un autre type de fiche
 *
 * @param    mixed   L'objet QuickForm du formulaire
 * @param    mixed   Le tableau des valeurs des différentes option pour le texte HTML
 * @param    string  Type d'action pour le formulaire : saisie, modification, vue,... saisie par défaut
 * @param    mixed	Tableau des valeurs par défauts (pour modification)
 *
 * @return   void
 */
function checkboxfiche(&$formtemplate, $tableau_template, $mode, $valeurs_fiche)
{
    if ($mode == 'saisie') {
        if (isset($GLOBALS['_BAZAR_']['id_fiche']) && $GLOBALS['_BAZAR_']['id_fiche']!='') {
            $html  = '';
            $bulledaide = '';
            if (isset($tableau_template[10]) && $tableau_template[10]!='') $bulledaide = ' <img class="tooltip_aide" title="'.htmlentities($tableau_template[10]).'" src="tools/bazar/presentation/images/aide.png" width="16" height="16" alt="image aide" />';
            //TODO: gestion multilinguisme
            $requete  = 'SELECT bf_id_fiche, bf_titre FROM '.BAZ_PREFIXE.'fiche WHERE bf_ce_nature='.$tableau_template[1];

            //on affiche que les fiches saisie par un utilisateur donné
            if (isset($tableau_template[7]) && $tableau_template[7]==1) $requete .= ' AND bf_ce_utilisateur="'.$GLOBALS['_BAZAR_']['nomwiki']['name'].'"';

            //on classe par ordre alphabetique
            $requete .= ' ORDER BY bf_titre';

            $resultat = $GLOBALS['_BAZAR_']['db']->query($requete) ;
            if (DB::isError ($resultat)) {
                return ($resultat->getMessage().$resultat->getDebugInfo()) ;
            }
            require_once 'HTML/QuickForm/checkbox.php';
            $i=0;
            $optioncheckbox = array('class' => 'element_checkbox');

            //valeurs par défauts
            if (isset($valeurs_fiche[$tableau_template[0].$tableau_template[1].$tableau_template[6]])) $tab = explode( ', ', $valeurs_fiche[$tableau_template[0].$tableau_template[1].$tableau_template[6]] );
            else $tab = explode( ', ', $tableau_template[5] );

            while ($ligne = $resultat->fetchRow()) {
                if ($i==0) $tab_chkbox=$tableau_template[2] ; else $tab_chkbox='&nbsp;';
                $url_checkboxfiche = clone($GLOBALS['_BAZAR_']['url']);
                $url_checkboxfiche->addQueryString(BAZ_VARIABLE_VOIR, BAZ_VOIR_CONSULTER);
                $url_checkboxfiche->addQueryString(BAZ_VARIABLE_ACTION, BAZ_VOIR_FICHE);
                $url_checkboxfiche->addQueryString('id_fiche', $ligne[0] );
                $url_checkboxfiche->addQueryString('wiki', $_GET['wiki'].'/iframe');
                $checkbox[$i]= & HTML_QuickForm::createElement('checkbox', $ligne[0], $tab_chkbox, '<a class="voir_fiche ouvrir_overlay" rel="#overlay-link" href="'.str_replace('&','&amp;',$url_checkboxfiche->getURL()).'">'.$ligne[1].'</a>', $optioncheckbox) ;
                $url_checkboxfiche->removeQueryString(BAZ_VARIABLE_VOIR);
                $url_checkboxfiche->removeQueryString(BAZ_VARIABLE_ACTION);
                $url_checkboxfiche->removeQueryString('id_fiche');
                $url_checkboxfiche->removeQueryString('wiki');
                if (in_array($ligne[0],$tab)) {
                    $defaultValues[$tableau_template[0].$tableau_template[1].$tableau_template[6].'['.$ligne[0].']']=true;
                } else $defaultValues[$tableau_template[0].$tableau_template[1].$tableau_template[6].'['.$ligne[0].']']=false;
                $i++;
            }

            if (is_array($checkbox)) {
                $formtemplate->addGroup($checkbox, $tableau_template[0].$tableau_template[1].$tableau_template[6], $tableau_template[4], "\n");
                if (isset($tableau_template[8]) && $tableau_template[8]==1) {
                    /*$formtemplate->addGroupRule($tableau_template[0].$tableau_template[1].$tableau_template[6], $tableau_template[4].' obligatoire', 'required', null, 1, 'client');*/
                }
                $formtemplate->setDefaults($defaultValues);
            }
            //ajout lien nouvelle saisie
            $url_checkboxfiche = clone($GLOBALS['_BAZAR_']['url']);
            $url_checkboxfiche->removeQueryString('id_fiche');
            $url_checkboxfiche->addQueryString('vue', BAZ_VOIR_SAISIR);
            $url_checkboxfiche->addQueryString('action', BAZ_ACTION_NOUVEAU);
            $url_checkboxfiche->addQueryString('wiki', $_GET['wiki'].'/iframe');
            $url_checkboxfiche->addQueryString('id_typeannonce', $tableau_template[1]);
            $url_checkboxfiche->addQueryString('ce_fiche_liee', $_GET['id_fiche']);
            $html .= '<a class="ajout_fiche ouvrir_overlay" href="'.str_replace('&', '&amp;', $url_checkboxfiche->getUrl()).'" rel="#overlay-link" title="'.htmlentities($tableau_template[2]).'">'.$tableau_template[2].'</a>'."\n";
            $formtemplate->addElement('html', $html);
        } else {
            $formtemplate->addElement('html', '<div class="alert alert-info">'.$tableau_template[3].'</div>');
        }
    } elseif ($mode == 'requete') {
        //on supprime les anciennes valeurs de la table '.BAZ_PREFIXE.'fiche_valeur_texte
        $requetesuppression='DELETE FROM '.BAZ_PREFIXE.'fiche_valeur_texte WHERE bfvt_ce_fiche="'.$GLOBALS['_BAZAR_']['id_fiche'].'" AND bfvt_id_element_form="'.$tableau_template[0].$tableau_template[1].$tableau_template[6].'"';
        $resultat = $GLOBALS['_BAZAR_']['db']->query($requetesuppression) ;
        if (DB::isError($resultat)) {
            echo ($resultat->getMessage().$resultat->getDebugInfo()) ;
        }
        if (isset($valeurs_fiche[$tableau_template[0].$tableau_template[1].$tableau_template[6]]) && ($valeurs_fiche[$tableau_template[0].$tableau_template[1].$tableau_template[6]]!=0)) {
            //on insere les nouvelles valeurs
            $requeteinsertion='INSERT INTO '.BAZ_PREFIXE.'fiche_valeur_texte (bfvt_ce_fiche, bfvt_id_element_form, bfvt_texte) VALUES ';
            //pour les checkbox, les différentes valeurs sont dans un tableau
            if (is_array($valeurs_fiche[$tableau_template[0].$tableau_template[1].$tableau_template[6]])) {
                $nb=0;
                while (list($cle, $val) = each($valeurs_fiche[$tableau_template[0].$tableau_template[1].$tableau_template[6]])) {
                    if ($nb>0) $requeteinsertion .= ', ';
                    $requeteinsertion .= '("'.$GLOBALS['_BAZAR_']['id_fiche'].'", "'.$tableau_template[0].$tableau_template[1].$tableau_template[6].'", "'.$cle.'") ';
                    $nb++;
                }
            }
            $resultat = $GLOBALS['_BAZAR_']['db']->query($requeteinsertion) ;
            if (DB::isError($resultat)) {
                echo ($resultat->getMessage().$resultat->getDebugInfo()) ;
            }
        }
    } elseif ($mode == 'formulaire_recherche') {
        if ($tableau_template[9]==1) {
            $requete =  'SELECT * FROM '.BAZ_PREFIXE.'liste_valeurs WHERE blv_ce_liste='.$tableau_template[1].
                ' AND blv_ce_i18n like "'.$GLOBALS['_BAZAR_']['langue'].'%" ORDER BY blv_label';
            $resultat = & $GLOBALS['_BAZAR_']['db'] -> query($requete) ;
            if (DB::isError ($resultat)) {
                echo ($resultat->getMessage().$resultat->getDebugInfo()) ;
            }
            require_once 'HTML/QuickForm/checkbox.php';
            $i=0;
            $optioncheckbox = array('class' => 'element_checkbox');

            while ($ligne = $resultat->fetchRow()) {
                if ($i==0) $tab_chkbox=$tableau_template[2] ; else $tab_chkbox='&nbsp;';
                $checkbox[$i]= & HTML_QuickForm::createElement($tableau_template[0], $ligne[1], $tab_chkbox, $ligne[2], $optioncheckbox) ;
                $i++;
            }

            $squelette_checkbox =& $formtemplate->defaultRenderer();
            $squelette_checkbox->setElementTemplate( '<fieldset class="bazar_fieldset">'."\n".'<legend>{label}'.
                    '<!-- BEGIN required --><span class="symbole_obligatoire">&nbsp;*</span><!-- END required -->'."\n".
                    '</legend>'."\n".'{element}'."\n".'</fieldset> '."\n"."\n", $tableau_template[0].$tableau_template[1].$tableau_template[6]);
            $squelette_checkbox->setGroupElementTemplate( "\n".'<div class="checkbox">'."\n".'{element}'."\n".'</div>'."\n", $tableau_template[0].$tableau_template[1].$tableau_template[6]);
            $formtemplate->addGroup($checkbox, $tableau_template[0].$tableau_template[1].$tableau_template[6], $tableau_template[2].$bulledaide, "\n");
        }
    } elseif ($mode == 'html') {
        $html = '';
        if (isset($valeurs_fiche[$tableau_template[0].$tableau_template[1].$tableau_template[6]]) && $valeurs_fiche[$tableau_template[0].$tableau_template[1].$tableau_template[6]]!='') {
            $requete  = 'SELECT bf_id_fiche, bf_titre FROM '.BAZ_PREFIXE.'fiche WHERE bf_id_fiche IN ('.$valeurs_fiche[$tableau_template[0].$tableau_template[1].$tableau_template[6]].') AND bf_ce_nature='.$tableau_template[1];

            //on classe par ordre alphabetique
            $requete .= ' ORDER BY bf_titre';

            $resultat = $GLOBALS['_BAZAR_']['db']->query($requete) ;
            if (DB::isError ($resultat)) {
                return ($resultat->getMessage().$resultat->getDebugInfo()) ;
            }
            $i=0;

            while ($ligne = $resultat->fetchRow()) {
                $url_checkboxfiche = clone($GLOBALS['_BAZAR_']['url']);
                $url_checkboxfiche->addQueryString(BAZ_VARIABLE_VOIR, BAZ_VOIR_CONSULTER);
                $url_checkboxfiche->addQueryString(BAZ_VARIABLE_ACTION, BAZ_VOIR_FICHE);
                $url_checkboxfiche->addQueryString('id_fiche', $ligne[0] );
                $url_checkboxfiche->addQueryString('wiki', $_GET['wiki'].'/iframe');
                $checkbox[$i]= '<a class="voir_fiche ouvrir_overlay" rel="#overlay-link" href="'.str_replace('&','&amp;',$url_checkboxfiche->getURL()).'">'.$ligne[1].'</a>';
                $url_checkboxfiche->removeQueryString(BAZ_VARIABLE_VOIR);
                $url_checkboxfiche->removeQueryString(BAZ_VARIABLE_ACTION);
                $url_checkboxfiche->removeQueryString('id_fiche');
                $url_checkboxfiche->removeQueryString('wiki');
                $i++;
            }

            if (is_array($checkbox)) {
                $html .= '<ul>'."\n";
                foreach ($checkbox as $lien_fiche) {
                    $html .= '<li>'.$lien_fiche.'</li>'."\n";
                }
                $html .= '</ul>'."\n";
            }
        }

        return $html;
    }
}

/** listefiches() - permet d'aller saisir et modifier un autre type de fiche
 *
 * @param    mixed   L'objet QuickForm du formulaire
 * @param    mixed   Le tableau des valeurs des différentes option pour le texte HTML
 * @param    string  Type d'action pour le formulaire : saisie, modification, vue,... saisie par défaut
 * @param    mixed	Tableau des valeurs par défauts (pour modification)
 *
 * @return   void
 */
function listefiches(&$formtemplate, $tableau_template, $mode, $valeurs_fiche)
{
    if (!isset($tableau_template[1])) {
        return $GLOBALS['wiki']->Format('//Erreur sur listefiches : pas d\'identifiant de type de fiche passé...//');
    }
    if (isset($tableau_template[2]) && $tableau_template[2] != '' ) {
        $query = $tableau_template[2].'|listefiche'.$valeurs_fiche['id_typeannonce'].'='.$valeurs_fiche['id_fiche'];
    } elseif (isset($valeurs_fiche) && $valeurs_fiche != '') {
        $query = 'listefiche'.$valeurs_fiche['id_typeannonce'].'='.$valeurs_fiche['id_fiche'];
    }
    if (isset($tableau_template[3])) {
        $ordre = $tableau_template[3];
    } else {
        $ordre = 'alphabetique';
    }

    if (isset($valeurs_fiche['id_fiche']) && $mode == 'saisie' ) {
        $actionbazarliste = '{{bazarliste idtypeannonce="'.$tableau_template[1].'" query="'.$query.'" ordre="'.$ordre.'"}}';
        $html = $GLOBALS['wiki']->Format($actionbazarliste);
        //ajout lien nouvelle saisie
        $url_checkboxfiche = clone($GLOBALS['_BAZAR_']['url']);
        $url_checkboxfiche->removeQueryString('id_fiche');
        $url_checkboxfiche->addQueryString('vue', BAZ_VOIR_SAISIR);
        $url_checkboxfiche->addQueryString('action', BAZ_ACTION_NOUVEAU);
        $url_checkboxfiche->addQueryString('wiki', $_GET['wiki'].'/iframe');
        $url_checkboxfiche->addQueryString('id_typeannonce', $tableau_template[1]);
        $url_checkboxfiche->addQueryString('ce_fiche_liee', $_GET['id_fiche']);
        $html .= '<a class="ajout_fiche ouvrir_overlay" href="'.str_replace('&', '&amp;', $url_checkboxfiche->getUrl()).'" rel="#overlay-link" title="'.htmlentities($tableau_template[4]).'">'.$tableau_template[4].'</a>'."\n";
        $formtemplate->addElement('html', $html);
    } elseif ($mode == 'requete') {
    } elseif ($mode == 'formulaire_recherche') {
        if ($tableau_template[9]==1) {
            $requete =  'SELECT * FROM '.BAZ_PREFIXE.'liste_valeurs WHERE blv_ce_liste='.$tableau_template[1].
                ' AND blv_ce_i18n like "'.$GLOBALS['_BAZAR_']['langue'].'%" ORDER BY blv_label';
            $resultat = & $GLOBALS['_BAZAR_']['db'] -> query($requete) ;
            if (DB::isError ($resultat)) {
                echo ($resultat->getMessage().$resultat->getDebugInfo()) ;
            }
            require_once 'HTML/QuickForm/checkbox.php';
            $i=0;
            $optioncheckbox = array('class' => 'element_checkbox');

            while ($ligne = $resultat->fetchRow()) {
                if ($i==0) $tab_chkbox=$tableau_template[2] ; else $tab_chkbox='&nbsp;';
                $checkbox[$i]= & HTML_QuickForm::createElement($tableau_template[0], $ligne[1], $tab_chkbox, $ligne[2], $optioncheckbox) ;
                $i++;
            }

            $squelette_checkbox =& $formtemplate->defaultRenderer();
            $squelette_checkbox->setElementTemplate( '<fieldset class="bazar_fieldset">'."\n".'<legend>{label}'.
                    '<!-- BEGIN required --><span class="symbole_obligatoire">&nbsp;*</span><!-- END required -->'."\n".
                    '</legend>'."\n".'{element}'."\n".'</fieldset> '."\n"."\n", $tableau_template[0].$tableau_template[1].$tableau_template[6]);
            $squelette_checkbox->setGroupElementTemplate( "\n".'<div class="checkbox">'."\n".'{element}'."\n".'</div>'."\n", $tableau_template[0].$tableau_template[1].$tableau_template[6]);
            $formtemplate->addGroup($checkbox, $tableau_template[0].$tableau_template[1].$tableau_template[6], $tableau_template[2].$bulledaide, "\n");
        }
    } elseif ($mode == 'html') {
        $actionbazarliste = '{{bazarliste idtypeannonce="'.$tableau_template[1].'" query="'.$query.'" ordre="'.$ordre.'"}}';
        $html = $GLOBALS['wiki']->Format($actionbazarliste);

        return $html;
    }
}

function bookmarklet(&$formtemplate, $tableau_template, $mode, $valeurs_fiche)
{
    if ($mode == 'html') {
        if ($GLOBALS['wiki']->GetMethod()=='bazarframe') {
            return '<a class="btn btn-danger pull-right" href="javascript:window.close();"><i class="icon-remove icon-white"></i>&nbsp;Fermer cette fen&ecirc;tre</a>';
        }
    } elseif ($mode == 'saisie') {
        if ($GLOBALS['wiki']->GetMethod()!='bazarframe') {
            $url_bookmarklet = clone($GLOBALS['_BAZAR_']['url']);
            $url_bookmarklet->removeQueryString('id_fiche');
            $url_bookmarklet->addQueryString('vue', BAZ_VOIR_SAISIR);
            $url_bookmarklet->addQueryString('action', BAZ_ACTION_NOUVEAU);
            $url_bookmarklet->addQueryString('wiki', $GLOBALS['_BAZAR_']['pagewiki'].'/bazarframe');
            $url_bookmarklet->addQueryString('id_typeannonce', $GLOBALS['_BAZAR_']['id_typeannonce']);
            $htmlbookmarklet = "<div class=\"BAZ_info\">
                <a href=\"javascript:var wleft = (screen.width-700)/2; var wtop=(screen.height-530)/2 ;window.open('".str_replace('&', '&amp;', $url_bookmarklet->getUrl())."&amp;bf_titre='+escape(document.title)+'&amp;url='+encodeURIComponent(location.href)+'&amp;description='+escape(document.getSelection()), '".$tableau_template[1]."', 'height=530,width=700,left='+wleft+',top='+wtop+',toolbar=no,location=no,directories=no,status=no,scrollbars=yes,resizable=yes,menubar=no');void 0;\">".$tableau_template[1]."</a> << ".$tableau_template[2]."</div>";
            $formtemplate->addElement('html', $htmlbookmarklet);
        }
    }
}

// Code provenant de spip :
function extension_autorisee($ext)
{
    $tables_images = array(
            // Images reconnues par PHP
            'jpg' => 'JPEG',
            'png' => 'PNG',
            'gif' =>'GIF',
            'jpeg' =>'JPEG',

            // Autres images (peuvent utiliser le tag <img>)
            'bmp' => 'BMP',
            'tif' => 'TIFF'
            );

    $tables_sequences = array(
            'aiff' => 'AIFF',
            'anx' => 'Annodex',
            'axa' => 'Annodex Audio',
            'axv' => 'Annodex Video',
            'asf' => 'Windows Media',
            'avi' => 'AVI',
            'flac' => 'Free Lossless Audio Codec',
            'flv' => 'Flash Video',
            'mid' => 'Midi',
            'mng' => 'MNG',
            'mka' => 'Matroska Audio',
            'mkv' => 'Matroska Video',
            'mov' => 'QuickTime',
            'mp3' => 'MP3',
            'mp4' => 'MPEG4',
            'mpg' => 'MPEG',
            'oga' => 'Ogg Audio',
            'ogg' => 'Ogg Vorbis',
            'ogv' => 'Ogg Video',
            'ogx' => 'Ogg Multiplex',
            'qt' => 'QuickTime',
            'ra' => 'RealAudio',
            'ram' => 'RealAudio',
            'rm' => 'RealAudio',
            'spx' => 'Ogg Speex',
            'svg' => 'Scalable Vector Graphics',
            'swf' => 'Flash',
            'wav' => 'WAV',
            'wmv' => 'Windows Media',
            '3gp' => '3rd Generation Partnership Project'
                );

    $tables_documents = array(
            'abw' => 'Abiword',
            'ai' => 'Adobe Illustrator',
            'bz2' => 'BZip',
            'bin' => 'Binary Data',
            'blend' => 'Blender',
            'c' => 'C source',
            'cls' => 'LaTeX Class',
            'css' => 'Cascading Style Sheet',
            'csv' => 'Comma Separated Values',
            'deb' => 'Debian',
            'doc' => 'Word',
            'djvu' => 'DjVu',
            'dvi' => 'LaTeX DVI',
            'eps' => 'PostScript',
            'gz' => 'GZ',
            'h' => 'C header',
            'html' => 'HTML',
            'kml' => 'Keyhole Markup Language',
            'kmz' => 'Google Earth Placemark File',
            'pas' => 'Pascal',
            'pdf' => 'PDF',
            'pgn' => 'Portable Game Notation',
            'ppt' => 'PowerPoint',
            'ps' => 'PostScript',
            'psd' => 'Photoshop',
            'rpm' => 'RedHat/Mandrake/SuSE',
            'rtf' => 'RTF',
            'sdd' => 'StarOffice',
            'sdw' => 'StarOffice',
            'sit' => 'Stuffit',
            'sty' => 'LaTeX Style Sheet',
            'sxc' => 'OpenOffice.org Calc',
            'sxi' => 'OpenOffice.org Impress',
            'sxw' => 'OpenOffice.org',
            'tex' => 'LaTeX',
            'tgz' => 'TGZ',
            'torrent' => 'BitTorrent',
            'ttf' => 'TTF Font',
            'txt' => 'texte',
            'xcf' => 'GIMP multi-layer',
            'xspf' => 'XSPF',
            'xls' => 'Excel',
            'xml' => 'XML',
            'zip' => 'Zip',

            // open document format
            'odt' => 'opendocument text',
            'ods' => 'opendocument spreadsheet',
            'odp' => 'opendocument presentation',
            'odg' => 'opendocument graphics',
            'odc' => 'opendocument chart',
            'odf' => 'opendocument formula',
            'odb' => 'opendocument database',
            'odi' => 'opendocument image',
            'odm' => 'opendocument text-master',
            'ott' => 'opendocument text-template',
            'ots' => 'opendocument spreadsheet-template',
            'otp' => 'opendocument presentation-template',
            'otg' => 'opendocument graphics-template',

            );

    if (array_key_exists($ext,$tables_images)) {
        return true;
    } else {

        if (array_key_exists($ext,$tables_sequences)) {
            return true;
        } else {
            if (array_key_exists($ext,$tables_documents)) {
                return true;
            } else {
                return false;
            }
        }

    }

}
function obtenir_extension($filename)
{
    $pos = strrpos($filename, '.');
    if ($pos === false) { // dot is not found in the filename

        return ''; // no extension
    } else {
        $extension = substr($filename, $pos+1);

        return  $extension;
    }
}
