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
    eval('function clone($object) { return $object; }');
}

function sanitizeFilename($string = '')
{
    // our list of "dangerous characters", add/remove characters if necessary
    $dangerous_characters = array(" ", '"', "'", "&", "/", "\\", "?", "#", "(", ")", "+");
    // every forbidden character is replace by an underscore
    $string = str_replace($dangerous_characters, '-', removeAccents($string));
    // Only allow one dash separator at a time (and make string lowercase)
    return mb_strtolower(preg_replace('/--+/u', '-', $string), YW_CHARSET);
}

/** afficher_image() - genere une image en cache (gestion taille et vignettes) et l'affiche comme il faut
 *
 * @param    string champ de la base
 * @param    string nom du fichier image
 * @param    string  label pour l'image
 * @param    string classes html supplementaires
 * @param    int        largeur en pixel de la vignette
 * @param    int        hauteur en pixel de la vignette
 * @param    int        largeur en pixel de l'image redimensionnee
 * @param    int        hauteur en pixel de l'image redimensionnee
 * @return   void
 */
function afficher_image(
    $champ,
    $nom_image,
    $label,
    $class,
    $largeur_vignette,
    $hauteur_vignette,
    $largeur_image,
    $hauteur_image,
    $method = 'fit'
) {
    // l'image initiale existe t'elle et est bien avec une extension jpg ou png et bien formatee
    $destimg = sanitizeFilename($nom_image);
    if (file_exists(BAZ_CHEMIN_UPLOAD . $nom_image)
      && preg_match('/^.*\.(jpg|jpe?g|png|gif)$/i', strtolower($nom_image))) {
        // faut il creer la vignette?
        if ($hauteur_vignette != '' && $largeur_vignette != '') {
            //la vignette n'existe pas, on la genere
            if (!file_exists('cache/vignette_' . $destimg)) {
                $adr_img = redimensionner_image(
                    BAZ_CHEMIN_UPLOAD . $nom_image,
                    'cache/vignette_' . $destimg,
                    $largeur_vignette,
                    $hauteur_vignette,
                    $method
                );
            } else {
                list($width, $height, $type, $attr) = getimagesize('cache/vignette_' . $destimg);
            }

            $url_base = str_replace('wakka.php?wiki=', '', $GLOBALS['wiki']->config['base_url']);

            //faut il redimensionner l'image?
            if ($hauteur_image != '' && $largeur_image != '') {
                //l'image redimensionnee n'existe pas, on la genere
                if (!file_exists('cache/image_' . $destimg)
                    || (isset($_GET['refresh']) && $_GET['refresh'] == 1)) {
                    $adr_img = redimensionner_image(
                        BAZ_CHEMIN_UPLOAD . $nom_image,
                        'cache/image_' . $destimg,
                        $largeur_image,
                        $hauteur_image,
                        $method
                    );
                }

                //on renvoit l'image en vignette, avec quand on clique, l'image redimensionnee
                return '<a data-id="' . $champ . '" class="modalbox ' . $class
                    .'" href="' . $url_base . 'cache/image_' . $destimg . '" title="' . htmlentities($nom_image) . '">' . "\n"
                    .'<img src="' . $url_base . 'cache/vignette_' . $destimg . '" alt="' . $destimg . '"'.' />'."\n"
                    .'</a> <!-- ' . $nom_image . ' -->' . "\n";
            } else {
                //on renvoit l'image en vignette, avec quand on clique, l'image originale
                return '<a data-id="' . $champ . '" class="modalbox ' . $class
                    . '" href="' . $url_base . BAZ_CHEMIN_UPLOAD . $nom_image . '" title="' . htmlentities($nom_image) . '">' . "\n"
                    . '<img class="img-responsive" src="' . $url_base . 'cache/vignette_' . $destimg
                    . '" alt="' . $nom_image . '"' . ' rel="' . $url_base . 'cache/image_' . $destimg . '" />' . "\n"
                    . '</a> <!-- ' . $nom_image . ' -->' . "\n";
            }
        } elseif ($hauteur_image != '' && $largeur_image != '') {
            //pas de vignette, mais faut il redimensionner l'image?
            if (!file_exists('cache/image_' . $destimg)) {
                $adr_img = redimensionner_image(
                    BAZ_CHEMIN_UPLOAD . $nom_image,
                    'cache/image_' . $destimg,
                    $largeur_image,
                    $hauteur_image,
                    $method
                );
            }
            return '<img src="cache/image_' . $destimg . '" class="img-responsive ' . $class
                . '" alt="' . $destimg . '"' . ' />' . "\n";
        } else {
            //on affiche l'image originale sinon
            return '<img src="' . BAZ_CHEMIN_UPLOAD . $destimg . '" class="img-responsive ' . $class
                . '" alt="' . $destimg . '"' . ' />' . "\n";
        }
    }
}

function redimensionner_image($image_src, $image_dest, $largeur, $hauteur, $method = 'fit')
{
    if (file_exists($image_src)) {
        if (!file_exists($image_dest) || (isset($_GET['refresh']) && $_GET['refresh']==1)) {
            if (file_exists($image_dest)) {
                unlink($image_dest);
            }
            if (!class_exists('Image')) {
                include_once('tools/bazar/libs/vendor/class.Images.php');
            }

            try {
                $image = new Image($image_src);
                $image->resize($largeur, $hauteur, $method);
                // Fix Orientation
                $exif = @exif_read_data($image_src);
                if (isset($exif['Orientation'])) {
                    $orientation = $exif['Orientation'];
                    switch ($orientation) {
                        case 3:
                            $image->rotate(180);
                            break;
                        case 6:
                            $image->rotate(-90);
                            break;
                        case 8:
                            $image->rotate(90);
                            break;
                    }
                }
                $ext = explode('.', $image_dest);
                $ext = end($ext);
                $image_dest = str_replace(array('cache/', '.'.$ext), '', $image_dest);
                $image->save($image_dest, "cache", $ext);
                return 'cache/'.$image_dest.'.'.$ext;
            } catch (Exception $e) {
                echo '<div class="alert alert-danger">Erreur Image :<br>';
                echo $e->getMessage();
                echo '</div>';
                return;
            }
        } else {
            return $image_dest;
        }
    }
}

//-------------------FONCTIONS DE TRAITEMENT DU TEMPLATE DU FORMULAIRE



/** formulaire_valeurs_template_champs() - Decoupe le template et renvoie un tableau structure
 *
 * @param    string  Template du formulaire
 * @return   mixed   Le tableau des elements du formulaire et options pour l'element liste
 */
function formulaire_valeurs_template_champs($template)
{
    //Parcours du template, pour mettre les champs du formulaire avec leurs valeurs specifiques
    $tableau_template = array();
    $nblignes = 0;

    //on traite le template ligne par ligne
    $chaine = explode("\n", $template);
    foreach ($chaine as $ligne) {
        $ligne = trim($ligne);
        // on ignore les lignes vides ou commencant par # (commentaire)
        if (!empty($ligne) && !(strrpos($ligne, '#', -strlen($ligne)) !== false)) {
            //on decoupe chaque ligne par le separateur *** (c'est historique)
            $tablignechampsformulaire = array_map("trim", explode("***", $ligne));
            if (function_exists($tablignechampsformulaire[0])) {
                if (count($tablignechampsformulaire) > 3) {
                    $tableau_template[$nblignes] = $tablignechampsformulaire;
                    for ($i=0; $i < 14; $i++) {
                        if (!isset($tableau_template[$nblignes][$i])) {
                            $tableau_template[$nblignes][$i] = '';
                        }
                    }
                    $nblignes++;
                }
            }
        }
    }

    return $tableau_template;
}

//-------------------FONCTIONS DE MISE EN PAGE DES FORMULAIRES



/** radio() - Ajoute un element de type radio au formulaire
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
        if (isset($tableau_template[10]) && $tableau_template[10] != '') {
            $bulledaide.= ' &nbsp;&nbsp;<img class="tooltip_aide" title="' . htmlentities($tableau_template[10], ENT_QUOTES, YW_CHARSET) . '" src="tools/bazar/presentation/images/aide.png" width="16" height="16" alt="image aide" />';
        }
        $ob = '';
        $optionrequired = '';
        if (isset($tableau_template[8]) && $tableau_template[8] == 1) {
            $ob.= '<span class="symbole_obligatoire">*&nbsp;</span>' . "\n";
            $optionrequired.= ' radio_required';
        }
        if (isset($valeurs_fiche[$tableau_template[0].$tableau_template[1].$tableau_template[6]]) && $valeurs_fiche[$tableau_template[0].$tableau_template[1].$tableau_template[6]] != '') {
            $def = $valeurs_fiche[$tableau_template[0].$tableau_template[1].$tableau_template[6]];
        } else {
            $def = $tableau_template[5];
        }

        $radio_html = '<fieldset class="bazar_fieldset' . $optionrequired . '"><legend>' . $ob . $tableau_template[2] . $bulledaide . '</legend>';

        $valliste = baz_valeurs_liste($tableau_template[1]);
        if (is_array($valliste['label'])) {
            foreach ($valliste['label'] as $key => $label) {
                $radio_html.= '<div class="bazar_radio">';
                $radio_html.= '<input type="radio" id="' . $tableau_template[0].$tableau_template[1].$tableau_template[6].$key . '" value="' . $key . '" name="' . $tableau_template[0].$tableau_template[1].$tableau_template[6].'" class="element_radio"';
                if ($def != '' && strstr($key, $def)) {
                    $radio_html.= ' checked';
                }
                $radio_html.= ' /><label for="' . $tableau_template[0].$tableau_template[1].$tableau_template[6].$key . '">' . $label . '</label>';
                $radio_html.= '</div>';
            }
        }
        $radio_html.= '</fieldset>';

        $formtemplate->addElement('html', $radio_html);
    } elseif ($mode == 'requete') {
    } elseif ($mode == 'formulaire_recherche') {
    } elseif ($mode == 'html') {
        $html = '';
        if (isset($valeurs_fiche[$tableau_template[0].$tableau_template[1].$tableau_template[6]]) && $valeurs_fiche[$tableau_template[0].$tableau_template[1].$tableau_template[6]] != '') {
            $valliste = baz_valeurs_liste($tableau_template[1]);

            $tabresult = explode(',', $valeurs_fiche[$tableau_template[0].$tableau_template[1].$tableau_template[6]]);
            if (is_array($tabresult)) {
                $labels_result = '';
                foreach ($tabresult as $id) {
                    if (isset($valliste["label"][$id])) {
                        if ($labels_result == '') {
                            $labels_result = $valliste["label"][$id];
                        } else {
                            $labels_result.= ', ' . $valliste["label"][$id];
                        }
                    }
                }
            } {
                $html = '<div class="BAZ_rubrique" data-id="' . $tableau_template[0].$tableau_template[1].$tableau_template[6].'">' . "\n" . '<span class="BAZ_label">' . $tableau_template[2] . '&nbsp;:</span>' . "\n" . '<span class="BAZ_texte">' . "\n" . $labels_result . "\n" . '</span>' . "\n" . '</div> <!-- /.BAZ_rubrique -->' . "\n";
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
    if ($mode == 'saisie') {
        $valliste = baz_valeurs_liste($tableau_template[1]);
        if ($valliste) {
            $bulledaide = '';
            if (isset($tableau_template[10]) && $tableau_template[10] != '') {
                $bulledaide = ' &nbsp;&nbsp;<img class="tooltip_aide" title="' . htmlentities($tableau_template[10], ENT_QUOTES, YW_CHARSET) . '" src="tools/bazar/presentation/images/aide.png" width="16" height="16" alt="image aide" />';
            }

            $select_html = '<div class="control-group form-group">' . "\n" . '<label class="control-label col-sm-3">' . "\n";
            if (isset($tableau_template[8]) && $tableau_template[8] == 1) {
                $select_html.= '<span class="symbole_obligatoire">*&nbsp;</span>' . "\n";
            }
            $select_html.= $tableau_template[2] . $bulledaide . ' : </label>' . "\n" . '<div class="controls col-sm-9">' . "\n" . '<select';

            $select_attributes = '';

            if ($tableau_template[4] != '' && $tableau_template[4] > 1) {
                $select_attributes.= ' multiple="multiple" size="' . $tableau_template[4] . '"';
                $selectnametab = '[]';
            } else {
                $selectnametab = '';
            }

            $select_attributes.= ' class="form-control" id="' . $tableau_template[0].$tableau_template[1].$tableau_template[6].'" name="' . $tableau_template[0].$tableau_template[1].$tableau_template[6].$selectnametab . '"';

            if (isset($tableau_template[8]) && $tableau_template[8] == 1) {
                $select_attributes.= ' required="required"';
            }
            $select_html.= $select_attributes . '>' . "\n";

            if (isset($valeurs_fiche[$tableau_template[0].$tableau_template[1].$tableau_template[6]]) && $valeurs_fiche[$tableau_template[0].$tableau_template[1].$tableau_template[6]] != '') {
                $def = $valeurs_fiche[$tableau_template[0].$tableau_template[1].$tableau_template[6]];
            } else {
                $def = $tableau_template[5];
            }

            if ($def == '' && ($tableau_template[4] == '' || $tableau_template[4] <= 1) || $def == 0) {
                $select_html.= '<option value="" selected="selected">' . _t('BAZ_CHOISIR') . '</option>' . "\n";
            }
            if (is_array($valliste['label'])) {
                foreach ($valliste['label'] as $key => $label) {
                    $select_html.= '<option value="' . $key . '"';
                    if ($def != '' && $key == $def) {
                        $select_html.= ' selected="selected"';
                    }
                    $select_html.= '>' . $label . '</option>' . "\n";
                }
            }

            $select_html.= "</select>\n</div>\n</div>\n";

            $formtemplate->addElement('html', $select_html);
        }
    } elseif ($mode == 'formulaire_recherche') {
        //on affiche la liste sous forme de liste deroulante
        if ($tableau_template[9] == 1) {
            $valliste = baz_valeurs_liste($tableau_template[1]);
            $select[0] = _t('BAZ_INDIFFERENT');
            if (is_array($valliste['label'])) {
                $select = $select + $valliste['label'];
            }

            $option = array('id' => $tableau_template[0].$tableau_template[1].$tableau_template[6], 'class' => 'form-control');
            require_once BAZ_CHEMIN.'libs/vendor/HTML/QuickForm/select.php';
            $select = new HTML_QuickForm_select($tableau_template[0].$tableau_template[1].$tableau_template[6], $tableau_template[2], $select, $option);
            if ($tableau_template[4] != '') {
                $select->setSize($tableau_template[4]);
            }
            $select->setMultiple(0);
            $nb = (isset($_REQUEST[$tableau_template[0].$tableau_template[1].$tableau_template[6]]) ? $_REQUEST[$tableau_template[0].$tableau_template[1].$tableau_template[6]] : 0);
            $select->setValue($nb);
            $formtemplate->addElement($select);
        }

        //on affiche la liste sous forme de checkbox
        if ($tableau_template[9] == 2) {
            $valliste = baz_valeurs_liste($tableau_template[1]);
            require_once BAZ_CHEMIN.'libs/vendor/HTML/QuickForm/checkbox.php';
            $i = 0;
            $optioncheckbox = array('class' => 'checkbox');

            foreach ($valliste['label'] as $id => $label) {
                if ($i == 0) {
                    $tab_chkbox = $tableau_template[2];
                } else {
                    $tab_chkbox = '&nbsp;';
                }
                $checkbox[$i] = & HTML_QuickForm::createElement('checkbox', $id, $tab_chkbox, $label, $optioncheckbox);
                $i++;
            }

            $squelette_checkbox = & $formtemplate->defaultRenderer();
            $squelette_checkbox->setElementTemplate('<fieldset class="bazar_fieldset">' . "\n" . '<legend>{label}' . '<!-- BEGIN required --><span class="symbole_obligatoire">&nbsp;*</span><!-- END required -->' . "\n" . '</legend>' . "\n" . '{element}' . "\n" . '</fieldset> ' . "\n" . "\n", $tableau_template[0].$tableau_template[1].$tableau_template[6]);
            $squelette_checkbox->setGroupElementTemplate("\n" . '<div class="checkbox">' . "\n" . '{element}' . "\n" . '</div>' . "\n", $tableau_template[0].$tableau_template[1].$tableau_template[6]);

            $formtemplate->addGroup($checkbox, $tableau_template[0].$tableau_template[1].$tableau_template[6], $tableau_template[2], "\n");
        }
    } elseif ($mode == 'requete_recherche') {
        if ($tableau_template[9] == 1 && isset($_REQUEST[$tableau_template[0].$tableau_template[1].$tableau_template[6]]) && $_REQUEST[$tableau_template[0].$tableau_template[1].$tableau_template[6]] != 0) {
            /*return ' AND bf_id_fiche IN (SELECT bfvt_ce_fiche FROM '.BAZ_PREFIXE.'fiche_valeur_texte WHERE bfvt_id_element_form="'.$tableau_template[0].$tableau_template[1].$tableau_template[6].'" AND bfvt_texte="'.$_REQUEST[$tableau_template[0].$tableau_template[1].$tableau_template[6]].'") ';*/
        }
    } elseif ($mode == 'html') {
        $html = '';
        if (isset($valeurs_fiche[$tableau_template[0].$tableau_template[1].$tableau_template[6]]) && $valeurs_fiche[$tableau_template[0].$tableau_template[1].$tableau_template[6]] != '') {
            $valliste = baz_valeurs_liste($tableau_template[1]);

            if (isset($valliste["label"][$valeurs_fiche[$tableau_template[0].$tableau_template[1].$tableau_template[6]]])) {
                $html = '<div class="BAZ_rubrique" data-id="' . $tableau_template[0].$tableau_template[1].$tableau_template[6].'">' . "\n" . '<span class="BAZ_label">' . $tableau_template[2] . '&nbsp;:</span>' . "\n" . '<span class="BAZ_texte">' . "\n" . $valliste["label"][$valeurs_fiche[$tableau_template[0].$tableau_template[1].$tableau_template[6]]] . "\n" . '</span>' . "\n" . '</div> <!-- /.BAZ_rubrique -->' . "\n";
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
        if (isset($tableau_template[10]) && $tableau_template[10] != '') {
            $bulledaide = ' &nbsp;&nbsp;<img class="tooltip_aide" title="'
            .htmlentities($tableau_template[10], ENT_QUOTES, YW_CHARSET)
            .'" src="tools/bazar/presentation/images/aide.png" width="16" height="16" alt="image aide" />';
        }

        $valliste = baz_valeurs_liste($tableau_template[1]);
        if ($valliste) {
            $id = $tableau_template[0].$tableau_template[1].$tableau_template[6];
            //valeurs par defauts
            if (isset($valeurs_fiche[$id])) {
                $tab = explode(',', $valeurs_fiche[$id]);
            } else {
                $tab = explode(',', $tableau_template[5]);
            }

            $choixcheckbox = $valliste['label'];

            if ($tableau_template[7] == 'tags') {
                $checkbox_html = '<div class="control-group form-group">' . "\n" . '<label class="control-label col-sm-3">' . "\n";
                if ($mode == 'saisie' && isset($tableau_template[8]) && $tableau_template[8] == 1) {
                    $checkbox_html.= '<span class="symbole_obligatoire">*&nbsp;</span>' . "\n";
                }
                $checkbox_html.= $tableau_template[2] . $bulledaide . ' : </label>' . "\n" . '<div class="controls col-sm-9"';
                if ($mode == 'saisie' && isset($tableau_template[8]) && $tableau_template[8] == 1) {
                    $checkbox_html.= ' required="required"';
                }
                $checkbox_html.= '>' . "\n";
                foreach ($choixcheckbox as $key => $title) {
                    $tabfiches[$key] = '{"id":"'.$key.'", "title":"'.str_replace('"', '\"', $title).'"}';
                }
                $script = '$(function(){
                    var tagsexistants = [' . implode(',', $tabfiches) . '];
                    var bazartag = [];
                    bazartag["'.$id.'"] = $(\'#formulaire .yeswiki-input-entries'.$id.'\');
                    bazartag["'.$id.'"].tagsinput({
                        itemValue: \'id\',
                        itemText: \'title\',
                        typeahead: {
                            afterSelect: function(val) { this.$element.val(""); },
                            source: tagsexistants
                        },
                        freeInput: false,
                        confirmKeys: [13, 188]
                    });'."\n";

                if (is_array($tab) && count($tab)>0 && !empty($tab[0])) {
                    foreach ($tab as $defid) {
                        if (isset($tabfiches[$defid])) {
                            $script .= 'bazartag["'.$id.'"].tagsinput(\'add\', '.$tabfiches[$defid].');'."\n";
                        }
                    }
                }
                $script .= '});' . "\n";
                $GLOBALS['wiki']->AddJavascriptFile('tools/tags/libs/vendor/bootstrap-tagsinput.min.js');
                $GLOBALS['wiki']->AddJavascript($script);
                $checkbox_html .= '<input type="text" name="'.$id.'" class="yeswiki-input-entries yeswiki-input-entries'.$id.'">';
                $checkbox_html.= "</div>\n</div>\n";
                $formtemplate->addElement('html', $checkbox_html);
            } else {
                if (isset($tableau_template[8]) && $tableau_template[8] == 1) {
                    $classrequired = ' chk_required';
                    $req = '<span class="symbole_obligatoire">&nbsp;*</span> ';
                } else {
                    $classrequired = '';
                    $req = '';
                }
                $checkbox_html = '<div class="control-group form-group">
<label class="control-label col-sm-3">
'.$req . $tableau_template[2] . $bulledaide.' :</label>
<div class="controls col-sm-9">
<div class="bazar-checkbox-cols'.$classrequired.'">';
                foreach ($choixcheckbox as $key => $label) {
                    //teste si la valeur de la liste doit etre cochee par defaut
                    if (in_array($key, $tab)) {
                        $chk = ' checked';
                    } else {
                        $chk = '';
                    }
                    $checkbox_html .= '<div class="checkbox">'."\n"
                      .'  <label for="'.$id.'['.$key.']'.'">'."\n"
                      .'    <input class="element_checkbox" name="'.$id.'['.$key.']'.'" value="1"'
                      .$chk.' id="'.$id.'['.$key.']'.'" type="checkbox">'.$label."\n"
                      .'  </label>'."\n"
                      .'</div>'."\n";
                }

                $checkbox_html .= '</div>
</div>
</div>';
                $formtemplate->addElement('html', $checkbox_html);
            }
        }
    } elseif ($mode == 'requete') {
        return array(
            $tableau_template[0].$tableau_template[1].$tableau_template[6] =>
                $valeurs_fiche[$tableau_template[0].$tableau_template[1].$tableau_template[6]]
        );
    } elseif ($mode == 'formulaire_recherche') {
        if ($tableau_template[9] == 1) {
            $valliste = baz_valeurs_liste($tableau_template[1]);
            require_once BAZ_CHEMIN.'libs/vendor/HTML/QuickForm/checkbox.php';
            $i = 0;
            $optioncheckbox = array('class' => 'element_checkbox');

            foreach ($valliste['label'] as $id => $label) {
                if ($i == 0) {
                    $tab_chkbox = $tableau_template[2];
                } else {
                    $tab_chkbox = '&nbsp;';
                }

                if (isset($_REQUEST[$tableau_template[0].$tableau_template[1].$tableau_template[6]])
                        && array_key_exists(
                            $id,
                            $_REQUEST[$tableau_template[0].$tableau_template[1].$tableau_template[6]]
                        )) {
                    $optioncheckbox['checked'] = 'checked';
                } else {
                    unset($optioncheckbox['checked']);
                }

                $checkbox[$i] = & HTML_QuickForm::createElement('checkbox', $id, $tab_chkbox, $label, $optioncheckbox);

                $i++;
            }

            $squelette_checkbox = & $formtemplate->defaultRenderer();
            $squelette_checkbox->setElementTemplate('<fieldset class="bazar_fieldset">' . "\n" . '<legend>{label}' . '<!-- BEGIN required --><span class="symbole_obligatoire">&nbsp;*</span><!-- END required -->' . "\n" . '</legend>' . "\n" . '{element}' . "\n" . '</fieldset> ' . "\n" . "\n", $tableau_template[0].$tableau_template[1].$tableau_template[6]);
            $squelette_checkbox->setGroupElementTemplate("\n" . '<div class="checkbox">' . "\n" . '{element}' . "\n" . '</div>' . "\n", $tableau_template[0].$tableau_template[1].$tableau_template[6]);

            $formtemplate->addGroup($checkbox, $tableau_template[0].$tableau_template[1].$tableau_template[6], $tableau_template[2], "\n");
        }
    } elseif ($mode == 'html') {
        $html = '';
        if (isset($valeurs_fiche[$tableau_template[0].$tableau_template[1].$tableau_template[6]]) && $valeurs_fiche[$tableau_template[0].$tableau_template[1].$tableau_template[6]] != '') {
            $valliste = baz_valeurs_liste($tableau_template[1]);

            $tabresult = explode(',', $valeurs_fiche[$tableau_template[0].$tableau_template[1].$tableau_template[6]]);
            if (is_array($tabresult)) {
                $labels_result = '';
                foreach ($tabresult as $id) {
                    if (isset($valliste["label"][$id])) {
                        if ($labels_result == '') {
                            $labels_result = $valliste["label"][$id];
                        } else {
                            $labels_result.= ', ' . $valliste["label"][$id];
                        }
                    }
                }
            } {
                $html = '<div class="BAZ_rubrique" data-id="' . $tableau_template[0].$tableau_template[1].$tableau_template[6].'">' . "\n" . '<span class="BAZ_label">' . $tableau_template[2] . '&nbsp;:</span>' . "\n" . '<span class="BAZ_texte">' . "\n" . $labels_result . "\n" . '</span>' . "\n" . '</div> <!-- /.BAZ_rubrique -->' . "\n";
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
        if (isset($tableau_template[10]) && $tableau_template[10] != '') {
            $bulledaide = ' &nbsp;&nbsp;<img class="tooltip_aide" title="' . htmlentities($tableau_template[10], ENT_QUOTES, YW_CHARSET) . '" src="tools/bazar/presentation/images/aide.png" width="16" height="16" alt="image aide" />';
        }

        $date_html = '<div class="control-group form-group">' . "\n" . '<label class="control-label col-sm-3">' . "\n";
        if (isset($tableau_template[8]) && $tableau_template[8] == 1) {
            $date_html .= '<span class="symbole_obligatoire">*&nbsp;</span>' . "\n";
        }
        $date_html .= $tableau_template[2] . $bulledaide . ' : </label>' . "\n"
            .'<div class="controls col-sm-9">' . "\n"
            .'<div class="input-prepend input-group"><span class="add-on input-group-addon">'
            .'<i class="icon-calendar glyphicon glyphicon-calendar"></i></span>'
            .'<input type="date" name="'.$tableau_template[1].'"'
            .' class="form-control bazar-date" id="'.$tableau_template[1].'"';

        if (isset($tableau_template[8]) && $tableau_template[8] == 1) {
            $date_html .= ' required="required"';
        }

        $hashour = false;
        $tabtime = array(0 => '00', 1 => '00');
        //gestion des valeurs par defaut pour modification
        if (isset($valeurs_fiche[$tableau_template[1]]) && !empty($valeurs_fiche[$tableau_template[1]])) {
            $date_html.= ' value="' . date("Y-m-d", strtotime($valeurs_fiche[$tableau_template[1]])) . '" />';
            $hashour = (strlen($valeurs_fiche[$tableau_template[1]]) > 10);
            if ($hashour) {
                $tab = explode('T', $valeurs_fiche[$tableau_template[1]]);
                $tabtime = explode(':', $tab[1]);
            }
        } else {
            //gestion des valeurs par defaut (date du jour)
            if (isset($tableau_template[5]) && $tableau_template[5] != '') {
                // si la valeur par defaut est today, on ajoute la date du jour
                if ($tableau_template[5] = 'today') {
                    $date_html.= ' value="' . date("Y-m-d") . '" />';
                } else {
                    $date_html.= ' value="' . date("Y-m-d", strtotime($tableau_template[5])) . '" />';
                }
            } else {
                $date_html.= ' value="" />';
            }
        }

        $date_html.= '<select class="form-control select-allday" name="' . $tableau_template[1] . '_allday">
        <option value="1"' . (($hashour) ? '' : ' selected') . '>' . _t('BAZ_ALL_DAY') . '</option>
        <option value="0"' . (($hashour) ? ' selected' : '') . '>' . _t('BAZ_ENTER_HOUR') . '</option>
        </select></div>
        <div class="select-time' . (($hashour) ? '' : ' hide') . ' input-prepend input-group">
        <span class="add-on input-group-addon">
        <i class="icon-time glyphicon glyphicon-time"></i></span>
        <select class="form-control select-hour" name="' . $tableau_template[1] . '_hour">
        <option value="00"' . (($hashour) ? '' : ' selected') . (($tabtime[0] == '00') ? 'selected' : '') . '>00</option>
        <option value="01"' . (($tabtime[0] == '01') ? 'selected' : '') . '>01</option>
        <option value="02"' . (($tabtime[0] == '02') ? 'selected' : '') . '>02</option>
        <option value="03"' . (($tabtime[0] == '03') ? 'selected' : '') . '>03</option>
        <option value="04"' . (($tabtime[0] == '04') ? 'selected' : '') . '>04</option>
        <option value="05"' . (($tabtime[0] == '05') ? 'selected' : '') . '>05</option>
        <option value="06"' . (($tabtime[0] == '06') ? 'selected' : '') . '>06</option>
        <option value="07"' . (($tabtime[0] == '07') ? 'selected' : '') . '>07</option>
        <option value="08"' . (($tabtime[0] == '08') ? 'selected' : '') . '>08</option>
        <option value="09"' . (($tabtime[0] == '09') ? 'selected' : '') . '>09</option>
        <option value="10"' . (($tabtime[0] == '10') ? 'selected' : '') . '>10</option>
        <option value="11"' . (($tabtime[0] == '11') ? 'selected' : '') . '>11</option>
        <option value="12"' . (($tabtime[0] == '12') ? 'selected' : '') . '>12</option>
        <option value="13"' . (($tabtime[0] == '13') ? 'selected' : '') . '>13</option>
        <option value="14"' . (($tabtime[0] == '14') ? 'selected' : '') . '>14</option>
        <option value="15"' . (($tabtime[0] == '15') ? 'selected' : '') . '>15</option>
        <option value="16"' . (($tabtime[0] == '16') ? 'selected' : '') . '>16</option>
        <option value="17"' . (($tabtime[0] == '17') ? 'selected' : '') . '>17</option>
        <option value="18"' . (($tabtime[0] == '18') ? 'selected' : '') . '>18</option>
        <option value="19"' . (($tabtime[0] == '19') ? 'selected' : '') . '>19</option>
        <option value="20"' . (($tabtime[0] == '20') ? 'selected' : '') . '>20</option>
        <option value="21"' . (($tabtime[0] == '21') ? 'selected' : '') . '>21</option>
        <option value="22"' . (($tabtime[0] == '22') ? 'selected' : '') . '>22</option>
        <option value="23"' . (($tabtime[0] == '23') ? 'selected' : '') . '>23</option>
        </select>
        <select class="form-control select-minutes" name="' . $tableau_template[1] . '_minutes">
        <option value="00"' . (($hashour) ? ' ' : ' selected') . (($tabtime[1] == '00') ? 'selected' : '') . '>00</option>
        <option value="05"' . (($tabtime[1] == '05') ? 'selected' : '') . '>05</option>
        <option value="10"' . (($tabtime[1] == '10') ? 'selected' : '') . '>10</option>
        <option value="15"' . (($tabtime[1] == '15') ? 'selected' : '') . '>15</option>
        <option value="20"' . (($tabtime[1] == '20') ? 'selected' : '') . '>20</option>
        <option value="25"' . (($tabtime[1] == '25') ? 'selected' : '') . '>25</option>
        <option value="30"' . (($tabtime[1] == '30') ? 'selected' : '') . '>30</option>
        <option value="35"' . (($tabtime[1] == '35') ? 'selected' : '') . '>35</option>
        <option value="40"' . (($tabtime[1] == '40') ? 'selected' : '') . '>40</option>
        <option value="45"' . (($tabtime[1] == '45') ? 'selected' : '') . '>45</option>
        <option value="50"' . (($tabtime[1] == '50') ? 'selected' : '') . '>50</option>
        <option value="55"' . (($tabtime[1] == '55') ? 'selected' : '') . '>55</option>
        </select>
        </div>
        </div>' . "\n" . '</div>' . "\n";

        $formtemplate->addElement('html', $date_html);
    } elseif ($mode == 'requete') {
        if (!empty($valeurs_fiche[$tableau_template[1]]) && isset($valeurs_fiche[$tableau_template[1] . '_allday'])
                && $valeurs_fiche[$tableau_template[1] . '_allday'] == 0) {
            if (isset($valeurs_fiche[$tableau_template[1] . '_hour'])
                    && isset($valeurs_fiche[$tableau_template[1] . '_minutes'])) {
                return array(
                    $tableau_template[1] => date("c", strtotime(
                        $valeurs_fiche[$tableau_template[1]] . ' ' . $valeurs_fiche[$tableau_template[1] . '_hour']
                        . ':' . $valeurs_fiche[$tableau_template[1] . '_minutes']
                    ))
                );
            } else {
                return array($tableau_template[1] => $valeurs_fiche[$tableau_template[1]]);
            }
        } else {
            return array($tableau_template[1] => isset($valeurs_fiche[$tableau_template[1]]) ?
                $valeurs_fiche[$tableau_template[1]] : '');
        }
    } elseif ($mode == 'recherche') {
    } elseif ($mode == 'html') {
        $res = '';
        if ($valeurs_fiche[$tableau_template[1]] != "") {
            $res .= '<div class="BAZ_rubrique" data-id="' . $tableau_template[1] . '">' . "\n" . '<span class="BAZ_label">' . $tableau_template[2] . '&nbsp;:</span>' . "\n";
            if (strlen($valeurs_fiche[$tableau_template[1]]) > 10) {
                $res .= '<span class="BAZ_texte">' . strftime('%d.%m.%Y - %H:%M', strtotime($valeurs_fiche[$tableau_template[1]])) . '</span>' . "\n" . '</div> <!-- /.BAZ_rubrique -->' . "\n";
            } else {
                $res .= '<span class="BAZ_texte">' . strftime('%d.%m.%Y', strtotime($valeurs_fiche[$tableau_template[1]])) . '</span>' . "\n" . '</div> <!-- /.BAZ_rubrique -->' . "\n";
            }
        }

        return $res;
    }
}

/** listedatedeb() - voir jour()
 *
 */
function listedatedeb(&$formtemplate, $tableau_template, $mode, $valeurs_fiche)
{
    return jour($formtemplate, $tableau_template, $mode, $valeurs_fiche);
}

/** listedatefin() - voir jour()
 *
 */
function listedatefin(&$formtemplate, $tableau_template, $mode, $valeurs_fiche)
{
    return jour($formtemplate, $tableau_template, $mode, $valeurs_fiche);
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
            $tags = explode(",", mysqli_real_escape_string($GLOBALS['wiki']->dblink, $valeurs_fiche[$tableau_template[1]]));
            if (is_array($tags)) {
                sort($tags);
                foreach ($tags as $tag) {
                    $tags_javascript.= 't.add(\'' . $tag . '\');' . "\n";
                }
            }
        }

        // on recupere tous les tags du site
        $response = array();
        $tab_tous_les_tags = $GLOBALS['wiki']->GetAllTags();
        if (is_array($tab_tous_les_tags)) {
            foreach ($tab_tous_les_tags as $tab_les_tags) {
                $response[] = _convert($tab_les_tags['value'], 'ISO-8859-15');
            }
        }
        sort($response);
        $tagsexistants = '\'' . implode('\',\'', $response) . '\'';

        $script = '$(function(){
        var tagsexistants = [' . $tagsexistants . '];
        var pagetag = $(\'#formulaire .yeswiki-input-pagetag\');
        pagetag.tagsinput({
            typeahead: {
                afterSelect: function(val) { this.$element.val(""); },
                source: tagsexistants
            },
            confirmKeys: [13, 188]
        });
    });' . "\n";
        $GLOBALS['wiki']->AddJavascriptFile('tools/tags/libs/vendor/bootstrap-tagsinput.min.js');
        $GLOBALS['wiki']->AddJavascript($script);

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

        $option = array('size' => $tableau_template[3], 'maxlength' => $tableau_template[4], 'id' => $tableau_template[1], 'value' => $defauts, 'class' => 'form-control yeswiki-input-pagetag');
        $bulledaide = '';
        if (isset($tableau_template[10]) && $tableau_template[10] != '') {
            $bulledaide = ' &nbsp;&nbsp;<img class="tooltip_aide" title="' . htmlentities($tableau_template[10], ENT_QUOTES, YW_CHARSET)
                .'" src="tools/bazar/presentation/images/aide.png" width="16" height="16" alt="image aide" />';
        }
        $formtemplate->addElement('text', $tableau_template[1], $tableau_template[2] . $bulledaide, $option);
    } elseif ($mode == 'requete') {
        //on supprime les tags existants
        if (!isset($GLOBALS['delete_tags'])) {
            $GLOBALS['wiki']->DeleteTriple($valeurs_fiche['id_fiche'], 'http://outils-reseaux.org/_vocabulary/tag', null, '', '');
            $GLOBALS['delete_tags'] = true;
        }

        //on decoupe les tags pour les mettre dans un tableau
        $tags = explode(",", mysqli_real_escape_string($GLOBALS['wiki']->dblink, $valeurs_fiche[$tableau_template[1]]));

        //on ajoute les tags postés
        foreach ($tags as $tag) {
            trim($tag);
            if ($tag != '') {
                $GLOBALS['wiki']->InsertTriple($valeurs_fiche['id_fiche'], 'http://outils-reseaux.org/_vocabulary/tag', _convert($tag, YW_CHARSET, true), '', '');
            }
        }

        //on copie tout de meme dans les metadonnees
        //return formulaire_insertion_texte($tableau_template[1], $valeurs_fiche[$tableau_template[1]]);
        return array($tableau_template[1] => $valeurs_fiche[$tableau_template[1]]);
    } elseif ($mode == 'html') {
        $html = '';
        if (isset($valeurs_fiche[$tableau_template[1]]) && $valeurs_fiche[$tableau_template[1]] != '') {
            $html = '<div class="BAZ_rubrique tags_' . $tableau_template[1] . '" data-id="' . $tableau_template[1] . '">' . "\n" . '<span class="BAZ_label">' . $tableau_template[2] . '&nbsp;:</span>' . "\n";
            $html.= '<div class="BAZ_texte"> ';
            $tabtagsexistants = explode(',', $valeurs_fiche[$tableau_template[1]]);

            if (count($tabtagsexistants) > 0) {
                sort($tabtagsexistants);
                $tagsexistants = '';
                foreach ($tabtagsexistants as $tag) {
                    $tagsexistants.= '<a class="tag-label label label-info" href="' . $GLOBALS['wiki']->href('listpages', $GLOBALS['wiki']->GetPageTag(), 'tags=' . urlencode(trim($tag))) . '" title="' . _t('TAGS_SEE_ALL_PAGES_WITH_THIS_TAGS') . '">' . $tag . '</a>
                    </li>' . "\n";
                }
                $html.= $tagsexistants . "\n";
            }

            $html.= '</div>' . "\n" . '</div> <!-- /.BAZ_rubrique -->' . "\n";
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
function texte(&$formtemplate, $tableau_template, $mode, $valeurs_fiche)
{
    list($type, $identifiant, $label, $nb_min_car, $nb_max_car, $valeur_par_defaut, $regexp, $type_input, $obligatoire,, $bulle_d_aide) = $tableau_template;
    if ($mode == 'saisie') {
        // on prepare le html de la bulle d'aide, si elle existe
        if ($bulle_d_aide != '') {
            $bulledaide = '&nbsp;&nbsp;<img class="tooltip_aide" title="' . htmlentities($bulle_d_aide, ENT_QUOTES, YW_CHARSET) . '" src="tools/bazar/presentation/images/aide.png" width="16" height="16" alt="image aide" />';
        } else {
            $bulledaide = '';
        }

        //si la valeur de nb_max_car est vide, on la mets au maximum
        if ($nb_max_car == '') {
            $nb_max_car = 255;
        }

        //par defaut il s'agit d'input html de type "text" (on precise si cela n'a pas ete entre)
        if ($type_input == '') {
            $type_input = 'text';
        }

        //gestion des valeurs par defaut : d'abord on regarde s'il y a une valeur a modifier,
        //puis s'il y a une variable passee en GET,
        //enfin on prend la valeur par defaut du formulaire sinon
        if (isset($valeurs_fiche[$identifiant])) {
            $defauts = $valeurs_fiche[$identifiant];
        } elseif (isset($_GET[$identifiant])) {
            $defauts = stripslashes($_GET[$identifiant]);
        } elseif ($type_input == 'range') {
            $defauts = '0';
        } else {
            $defauts = stripslashes($valeur_par_defaut);
        }

        $input_html = '<div class="control-group form-group">' . "\n" . '<label class="control-label col-sm-3">';
        $input_html.= ($obligatoire == 1) ? '<span class="symbole_obligatoire">*&nbsp;</span>' : '';
        $input_html.= $label . $bulledaide . ' : </label>' . "\n";
        $input_html.= '<div class="controls col-sm-9">' . "\n";
        $input_html.= '<div class="input-group">' . "\n";
        $input_html.= '<input type="' . $type_input . '"';
        $input_html.= ($defauts != '') ?
            ' value="'.htmlspecialchars($defauts, ENT_COMPAT | ENT_HTML401, YW_CHARSET).'"' : '';
        $input_html.= ' name="' . $identifiant . '" class="form-control input-xxlarge" id="' . $identifiant . '"';
        $input_html.= (($type_input == 'number' || $type_input == 'range') && $nb_min_car != '') ?
            ' min="' . $nb_min_car . '"' :
            ' maxlength="' . $nb_max_car . '" size="' . $nb_max_car . '"';
        $input_html.= ($type_input == 'number' || $type_input == 'range') ? ' max="' . $nb_max_car . '"' : '';
        $input_html.= ($type_input == 'range') ? ' oninput="this.form.'.$identifiant.'number.value=this.value"' : '';
        $input_html.= ($regexp != '') ? ' pattern="' . $regexp . '"' : '';
        $input_html.= ($obligatoire == 1) ? ' required="required"' : '';
        $input_html.= '>' . "\n";
        $input_html.= ($type_input == 'range') ?
            '<output class="input-group-addon" name="'.$identifiant.'number">'.$defauts.'</output>' :
            '';
        $input_html.= '</div>' . "\n" . '</div>' . "\n" . '</div>' . "\n";

        $formtemplate->addElement('html', $input_html);
    } elseif ($mode == 'requete') {
        // TODO tester
        return array($tableau_template[1] => $valeurs_fiche[$tableau_template[1]]);
    } elseif ($mode == 'html') {
        // TODO tester
        $html = '';
        if (isset($valeurs_fiche[$tableau_template[1]]) && $valeurs_fiche[$tableau_template[1]] != '') {
            if ($tableau_template[1] == 'bf_titre') {
                // Le titre
                $html.= '<h1 class="BAZ_fiche_titre">' . $valeurs_fiche[$tableau_template[1]] . '</h1>' . "\n";
            } else {
                $html = '<div class="BAZ_rubrique" data-id="' . $tableau_template[1] . '">' . "\n" . '<span class="BAZ_label">' . $tableau_template[2] . '&nbsp;:</span>' . "\n";
                $html.= '<span class="BAZ_texte"> ';
                $html.= $valeurs_fiche[$tableau_template[1]] . '</span>' . "\n" . '</div> <!-- /.BAZ_rubrique -->' . "\n";
            }
        }
        return $html;
    }
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
        $option = array('maxlength' => 60, 'id' => 'nomwiki');
        if (!isset($valeurs_fiche['nomwiki'])) {
            //mot de passe
            $bulledaide = '';
            if (isset($tableau_template[10]) && $tableau_template[10] != '') {
                $bulledaide = ' &nbsp;&nbsp;<img class="tooltip_aide" title="'
                    .htmlentities($tableau_template[10], ENT_QUOTES, YW_CHARSET)
                    .'" src="tools/bazar/presentation/images/aide.png" width="16" height="16" alt="image aide" />';
            }
            $option = array('required' => 'required', 'size' => $tableau_template[3], 'class' => 'form-control');
            $formtemplate->addElement('password', 'mot_de_passe_wikini', '<span class="symbole_obligatoire">*</span> '._t('BAZ_MOT_DE_PASSE') . $bulledaide, $option);
            $formtemplate->addElement('password', 'mot_de_passe_repete_wikini', '<span class="symbole_obligatoire">*</span> '._t('BAZ_MOT_DE_PASSE') . ' (' . _t('BAZ_VERIFICATION') . ')', $option);
        } else {
            $formtemplate->addElement('hidden', 'nomwiki', $valeurs_fiche['nomwiki']);
        }
    } elseif ($mode == 'requete') {
        $sendmail = true;
        if (isset($GLOBALS['_BAZAR_']['provenance']) && $GLOBALS['_BAZAR_']['provenance'] == 'import') {
            $sendmail = false;
        }

        $nomwiki = (isset($valeurs_fiche['nomwiki']) && !empty($valeurs_fiche['nomwiki'])) ?
            $valeurs_fiche['nomwiki'] : $valeurs_fiche[$tableau_template[1]];

        if (!$GLOBALS['wiki']->IsWikiName($nomwiki)) {
            $nomwiki = genere_nom_wiki($valeurs_fiche[$tableau_template[1]], 0);
            // si le user existe, on ajoute un nombre
            while ($GLOBALS['wiki']->LoadUser($nomwiki)) {
                $nomwiki = genere_nom_wiki($valeurs_fiche[$tableau_template[1]]);
            }
        }

        // indicateur pour la gestion des droits associee a la fiche.
        $GLOBALS['utilisateur_wikini'] = $nomwiki;

        if (!$GLOBALS['wiki']->LoadUser($nomwiki)) {
            $requeteinsertionuserwikini = 'INSERT INTO ' . $GLOBALS['wiki']->config["table_prefix"] . "users SET " . "signuptime = now(), " . "name = '" . mysqli_real_escape_string($GLOBALS['wiki']->dblink, $nomwiki) . "', " . "email = '" . mysqli_real_escape_string($GLOBALS['wiki']->dblink, $valeurs_fiche[$tableau_template[2]]) . "', " . "password = md5('" . mysqli_real_escape_string($GLOBALS['wiki']->dblink, $valeurs_fiche['mot_de_passe_wikini']) . "')";
            $resultat = $GLOBALS['wiki']->query($requeteinsertionuserwikini);
            if ($sendmail) {
                //envoi mail nouveau mot de passe : il vaut mieux ne pas envoyer de mots de passe en clair.
                $lien = str_replace("/wakka.php?wiki=", "", $GLOBALS['wiki']->config["base_url"]);
                $objetmail = '['.str_replace("http://", "", $lien).'] Vos nouveaux identifiants sur le site '.$GLOBALS['wiki']->config["wakka_name"];
                $messagemail = "Bonjour!\n\nVotre inscription sur le site a ete finalisee, dorenavant vous pouvez vous identifier avec les informations suivantes :\n\nVotre identifiant NomWiki : ".$nomwiki."\n\nVotre email : ".$valeurs_fiche[$tableau_template[2]]."\n\nVotre mot de passe : (le mot de passe que vous avez choisi)\n\n\n\nA tres bientot ! \n\n";
                $headers =   'From: '.BAZ_ADRESSE_MAIL_ADMIN . "\r\n" .
                'Reply-To: '.BAZ_ADRESSE_MAIL_ADMIN . "\r\n" .
                'X-Mailer: PHP/' . phpversion();
                mail($valeurs_fiche[$tableau_template[2]], removeAccents($objetmail), $messagemail, $headers);
                // ajout dans la liste de mail
                if (isset($valeurs_fiche[$tableau_template[5]]) && $valeurs_fiche[$tableau_template[5]] != '') {
                    $headers = 'From: ' . $valeurs_fiche[$tableau_template[2]] . "\r\n" . 'Reply-To: ' . $valeurs_fiche[$tableau_template[2]] . "\r\n" . 'X-Mailer: PHP/' . phpversion();
                    mail($valeurs_fiche[$tableau_template[5]], 'inscription a la liste de discussion', 'inscription', $headers);
                }
            }
        }

        return array('nomwiki' => $nomwiki);
    } elseif ($mode == 'recherche') {
    } elseif ($mode == 'html') {
        $html= '';
        if (isset($valeurs_fiche['nomwiki']) and !empty($valeurs_fiche['nomwiki'])) {
            $html .= '<div class="BAZ_rubrique" data-id="nomwiki">' . "\n" . '<span class="BAZ_label">'._t('BAZ_GIVEN_ID').' :</span>' . "\n";
            $html .= '<span class="BAZ_texte"> ';
            $html .= $valeurs_fiche['nomwiki'];
            if ($GLOBALS['wiki']->GetUser() and ($GLOBALS['wiki']->GetUserName() == $valeurs_fiche['nomwiki'])) {
                $html .= ' <a class="btn btn-xs btn-default" href="'.$GLOBALS['wiki']->href('edit', $valeurs_fiche['nomwiki']).'"><i class="glyphicon glyphicon-pencil"></i> '._t('BAZ_EDIT_MY_ENTRY').'</a> <a  class="btn btn-xs btn-default" href="'.$GLOBALS['wiki']->href('', 'ParametresUtilisateur').'"><i class="glyphicon glyphicon-lock"></i> '._t('BAZ_CHANGE_PWD').'</a>';
            }
            $html .= '</span>' . "\n" . '</div> <!-- /.BAZ_rubrique -->' . "\n";
        }
        return $html;
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
    //Remplacer champ par subscribe / unsubscribe et ne pas faire le test
    $id = str_replace(array('@', '.'), array('', ''), $tableau_template[1]);
    $valsub = str_replace('@', '-subscribe@', $tableau_template[1]);
    $valunsub = str_replace('@', '-unsubscribe@', $tableau_template[1]);

    // test de presence d'ezmlm, qui necessite de reformater le mail envoyé
    if (isset($tableau_template[4]) and $tableau_template[4] == 'ezmlm') {
        $valsub = str_replace('@', '-'.str_replace('@', '=', $valeurs_fiche[$tableau_template[3]]).'@', $valsub);
        $valunsub = str_replace('@', '-'.str_replace('@', '=', $valeurs_fiche[$tableau_template[3]]).'@', $valunsub);
    }

    if ($mode == 'saisie') {
        $input_html = '<div class="control-group form-group">
                    <div class="controls col-sm-9">
                        <div class="checkbox">
                          <label for="' . $id . '">'."\n"
                          .'<input id="' . $id . '" type="checkbox"' . ((!isset($valeurs_fiche[$id]) or isset($valeurs_fiche[$id]) && $valeurs_fiche[$id] == $valsub) ? ' checked="checked"' : '') . ' value="' . $tableau_template[1] . '" name="' . $id . '" class="element_checkbox">'
                          . $tableau_template[2] . '</label>
                        </div>
                    </div>
                </div>';
        $formtemplate->addElement('html', $input_html);
    } elseif ($mode == 'requete') {
        if (!class_exists("Mail")) {
            include_once 'tools/contact/libs/contact.functions.php';
        }

        if (isset($GLOBALS['_BAZAR_']['provenance']) && $GLOBALS['_BAZAR_']['provenance'] == 'import') {
            if ($valeurs_fiche[$id] == $valsub) {
                send_mail($valeurs_fiche[$tableau_template[3]], $valeurs_fiche['bf_titre'], $valsub, 'subscribe', 'subscribe', 'subscribe');
                return array($id => $valeurs_fiche[$id]);
            } else {
                if ($valeurs_fiche[$id] == $valunsub) {
                    // On n'envoit pas de message dans ce cas la, car ca n'a pas de sens ...
                    //              send_mail($valeurs_fiche[$tableau_template[3]], $valeurs_fiche['bf_titre'], $valunsub, 'unsubscribe', 'unsubscribe', 'unsubscribe');
                    return array($id => $valeurs_fiche[$id]);
                }
            }
        } else {
            if (isset($valeurs_fiche[$id])) {
                send_mail($valeurs_fiche[$tableau_template[3]], $valeurs_fiche['bf_titre'], $valsub, 'subscribe', 'subscribe', 'subscribe');
                $valeurs_fiche[$tableau_template[1]] = $valsub;
                return array($id => $valeurs_fiche[$tableau_template[1]]);
            } else {
                // on ne desabonne que si abonne precedement
                if (isset($valeurs_fiche[$id])) {
                    send_mail($valeurs_fiche[$tableau_template[3]], $valeurs_fiche['bf_titre'], $valunsub, 'unsubscribe', 'unsubscribe', 'unsubscribe');
                    $valeurs_fiche[$tableau_template[1]] = $valunsub;
                    return array($id => $valeurs_fiche[$tableau_template[1]]);
                }
            }
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
        $formtemplate->addElement('hidden', $tableau_template[1], $tableau_template[2], array('id' => $tableau_template[1]));

        //gestion des valeurs par défaut
        $defs = array($tableau_template[1] => $tableau_template[5]);
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
    list($type, $identifiant, $label, $nb_min_car, $nb_max_car, $valeur_par_defaut, $showform, $type_input, $obligatoire, $sendmail, $bulle_d_aide) = $tableau_template;
    if ($mode == 'saisie') {
        // on prepare le html de la bulle d'aide, si elle existe
        if ($bulle_d_aide != '') {
            $bulledaide = '&nbsp;&nbsp;<img class="tooltip_aide" title="' . htmlentities($bulle_d_aide, ENT_QUOTES, YW_CHARSET) . '" src="tools/bazar/presentation/images/aide.png" width="16" height="16" alt="image aide" />';
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
        if ($nb_max_car == '') {
            $nb_max_car = 255;
        }

        //par defaut il s'agit d'input html de type "text" (on precise si cela n'a pas ete entre)
        if ($type_input == '') {
            $type_input = 'email';
        }

        $input_html = '<div class="control-group form-group">' . "\n" . '<label class="control-label col-sm-3">';
        $input_html.= ($obligatoire == 1) ? '<span class="symbole_obligatoire">*&nbsp;</span>' : '';
        $input_html.= $label . $bulledaide . ' : </label>' . "\n";
        $input_html.= '<div class="controls col-sm-9">' . "\n";
        $input_html.= '<input type="' . $type_input . '"';
        $input_html.= ($defauts != '') ? ' value="' . $defauts . '"' : '';
        $input_html.= ' name="' . $identifiant . '" class="form-control" id="' . $identifiant . '"';
        $input_html.= ' maxlength="' . $nb_max_car . '" size="' . $nb_max_car . '"';
        $input_html.= ($obligatoire == 1) ? ' required="required"' : '';
        $input_html.= '>' . "\n" . '</div>' . "\n" . '</div>' . "\n";
        if ($sendmail == 1) {
            $formtemplate->addElement('hidden', 'sendmail', $identifiant);
        }
        $formtemplate->addElement('html', $input_html);
    } elseif ($mode == 'requete') {
        if ($sendmail == 1) {
            $valeurs_fiche['sendmail'] = $identifiant;
        }
        return array($tableau_template[1] => isset($valeurs_fiche[$tableau_template[1]]) ? $valeurs_fiche[$tableau_template[1]] : '');
    } elseif ($mode == 'recherche') {
    } elseif ($mode == 'html') {
        $html = '';
        if (isset($valeurs_fiche[$tableau_template[1]]) && $valeurs_fiche[$tableau_template[1]] != '') {
            $html = '<div class="BAZ_rubrique" data-id="' . $tableau_template[1] . '">' . "\n" . '<span class="BAZ_label">' . $tableau_template[2] . '&nbsp;:</span>' . "\n";
            if ($showform == 'form') {
                // js necessaire pour valider le formulaire et faire l'envoi ajax
                $GLOBALS['wiki']->addJavascriptFile('tools/contact/libs/contact.js');
                $title = 'Contacter par mail '.htmlspecialchars($valeurs_fiche['bf_titre']);
                $html .= '<span class="BAZ_texte"><a class="btn btn-default modalbox" title="'.$title.'" href="'
                  .$GLOBALS['wiki']->href('mail', $GLOBALS['wiki']->GetPageTag(), 'field='.$tableau_template[1]).'"><i class="glyphicon glyphicon-envelope"></i> '.$title;
                $html .=  '</a></span>' . "\n" . '</div> <!-- /.BAZ_rubrique -->' . "\n";
            } else {
                $html.= '<span class="BAZ_texte"><a href="mailto:' . $valeurs_fiche[$tableau_template[1]] . '" class="BAZ_lien_mail">';
                $html.= $valeurs_fiche[$tableau_template[1]] . '</a></span>' . "\n" . '</div> <!-- /.BAZ_rubrique -->' . "\n";
            }
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
    list($type, $identifiant, $label, , , $valeur_par_defaut, , , $obligatoire, , $bulle_d_aide) = $tableau_template;
    if ($mode == 'saisie') {
        // on prepare le html de la bulle d'aide, si elle existe
        if ($bulle_d_aide != '') {
            $bulledaide = '&nbsp;&nbsp;<img class="tooltip_aide" title="' . htmlentities($bulle_d_aide, ENT_QUOTES, YW_CHARSET) . '" src="tools/bazar/presentation/images/aide.png" width="16" height="16" alt="image aide" />';
        } else {
            $bulledaide = '';
        }

        $nb_max_car = 255;
        $type_input = 'password';

        $input_html = '<div class="control-group form-group">' . "\n" . '<label class="control-label col-sm-3">';
        $input_html.= ($obligatoire == 1) ? '<span class="symbole_obligatoire">*&nbsp;</span>' : '';
        $input_html.= $label . $bulledaide . ' : </label>' . "\n";
        $input_html.= '<div class="controls col-sm-9">' . "\n";
        $input_html.= '<input type="' . $type_input . '"';
        $input_html.= ' name="' . $identifiant . '" class="form-control" id="' . $identifiant . '"';
        $input_html.= ' maxlength="' . $nb_max_car . '" size="' . $nb_max_car . '"';
        $input_html.= ($obligatoire == 1) ? ' required' : '';
        $input_html.= '>' . "\n" . '</div>' . "\n" . '</div>' . "\n";
        if (isset($valeurs_fiche[$identifiant])) {
            $input_html.= '<input type="hidden" value="'.$valeurs_fiche[$identifiant].'" name="'.$identifiant.'-previous">' . "\n";
        }
        $formtemplate->addElement('html', $input_html);
    } elseif ($mode == 'requete') {
        if (!empty($valeurs_fiche[$identifiant])) {
            return array($tableau_template[1] => md5($valeurs_fiche[$identifiant]));
        } else if (isset($valeurs_fiche[$identifiant.'-previous']) && !empty($valeurs_fiche[$identifiant.'-previous'])) {
            return array($tableau_template[1] => $valeurs_fiche[$identifiant.'-previous']);
        }
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
    list($type, $identifiant, $label, $nb_colonnes, $nb_lignes, $valeur_par_defaut, $longueurmax, $formatage,
         $obligatoire, $apparait_recherche, $bulle_d_aide) = $tableau_template;
    if (empty($formatage) || $formatage == 'wiki') {
        $formatage = 'wiki-textarea';
    } elseif ($formatage == 'html') {
        $langpref = strtolower($GLOBALS['prefered_language']).'-'.strtoupper($GLOBALS['prefered_language']);
        $langfile = 'tools/bazar/libs/vendor/summernote/lang/summernote-'.$langpref.'.js';
        $GLOBALS['wiki']->AddJavascriptFile('tools/bazar/libs/vendor/summernote/summernote.min.js');
        $GLOBALS['wiki']->AddCSSFile('tools/bazar/libs/vendor/summernote/summernote.css');
        //$GLOBALS['wiki']->AddJavascriptFile('tools/bazar/libs/vendor/summernote/plugin/summernote-ext-well.js');
        if (file_exists($langfile)) {
            $GLOBALS['wiki']->AddJavascriptFile($langfile);
            $langoptions = 'lang: "'.$langpref.'",';
        } else {
            $langoptions = '';
        }
        $script = '$(document).ready(function() {
          $(".summernote").summernote({
            '.$langoptions.'
            height: 300,    // set editor height
            minHeight: 300, // set minimum height of editor
            maxHeight: 550,                // set maximum height of editor
            focus: false,                   // set focus to editable area after initializing summernote
            toolbar: [
                //[groupname, [button list]]
                //[\'style\', [\'style\', \'well\']],
                [\'style\', [\'style\']],
                [\'textstyle\', [\'bold\', \'italic\', \'underline\', \'strikethrough\', \'clear\']],
                [\'color\', [\'color\']],
                [\'para\', [\'ul\', \'ol\', \'paragraph\']],
                [\'insert\', [\'hr\', \'link\', \'table\', \'picture\', \'video\']],
                [\'misc\', [\'fullscreen\', \'codeview\']]
            ],
            styleTags: [\'h1\', \'h2\', \'h3\', \'h4\', \'h5\', \'h6\', \'p\', \'blockquote\', \'pre\'],
            oninit: function() {
              //$(\'button[data-original-title=Style]\').prepend("Style").find("i").remove();
            }
          });
        });';
        $GLOBALS['wiki']->AddJavascript($script);
    }
    if ($mode == 'saisie') {
        $longueurmaxlabel = ($longueurmax ? ' (<span class="charsRemaining">' . $longueurmax . '</span> caract&egrave;res restants)' : '');
        $bulledaide = '';
        if ($bulle_d_aide != '') {
            $bulledaide = ' &nbsp;&nbsp;<img class="tooltip_aide" title="' . htmlentities($bulle_d_aide, ENT_QUOTES, YW_CHARSET) . '" src="tools/bazar/presentation/images/aide.png" width="16" height="16" alt="image aide" />';
        }

        $options = array('id' => $identifiant, 'class' => 'form-control input-xxlarge '.(($formatage == 'html') ? 'summernote' : $formatage));
        if ($longueurmax != '') {
            $options['maxlength'] = $longueurmax;
        }

        //gestion du champs obligatoire
        $symb = '';
        if (isset($obligatoire) && $obligatoire == 1) {
            $options['required'] = 'required';
            $symb.= '<span class="symbole_obligatoire">*&nbsp;</span>';
        }

        $formtexte = new HTML_QuickForm_textarea($identifiant, $symb . $label . $longueurmaxlabel . $bulledaide, $options);
        $formtexte->setCols($nb_colonnes);
        $formtexte->setRows($nb_lignes);
        $formtemplate->addElement($formtexte);

        //gestion des valeurs par defaut : d'abord on regarde s'il y a une valeur a modifier,
        //puis s'il y a une variable passee en GET,
        //enfin on prend la valeur par defaut du formulaire sinon
        if (isset($valeurs_fiche[$identifiant])) {
            $defauts = array($identifiant => $valeurs_fiche[$identifiant]);
        } elseif (isset($_GET[$identifiant])) {
            $defauts = array($identifiant => stripslashes($_GET[$identifiant]));
        } else {
            $defauts = array($identifiant => stripslashes($tableau_template[5]));
        }
        $formtemplate->setDefaults($defauts);
    } elseif ($mode == 'requete') {
        // En html, pour la sécurité, on n'autorise qu'un certain nombre de balises
        if ($formatage == 'html') {
            $acceptedtags = '<h1><h2><h3><h4><h5><h6><hr><hr/><br><br/><span><blockquote><i><u><b><strong><ol><ul><li><small><div><p><a><table><tr><th><td><img><figure><caption><iframe>';
            $valeurs_fiche[$identifiant] = strip_tags($valeurs_fiche[$identifiant], $acceptedtags);
        }
        return array($identifiant => $valeurs_fiche[$identifiant]);
    } elseif ($mode == 'html') {
        $html = '';
        if (isset($valeurs_fiche[$identifiant]) && $valeurs_fiche[$identifiant] != '') {
            $html = '<div class="BAZ_rubrique" data-id="' . $identifiant . '">' . "\n" . '<span class="BAZ_label">' . $label . '&nbsp;:</span>' . "\n";
            $html.= '<span class="BAZ_texte"> ';
            if ($formatage == 'wiki-textarea') {
                $containsattach = (strpos($valeurs_fiche[$identifiant], '{{attach') !== false);
                if ($containsattach) {
                    $oldpage = $GLOBALS['wiki']->GetPageTag();
                    $oldpagearray = $GLOBALS['wiki']->page;
                    $GLOBALS['wiki']->tag = $valeurs_fiche['id_fiche'];
                    $GLOBALS['wiki']->page = $GLOBALS['wiki']->LoadPage($GLOBALS['wiki']->tag);
                }
                $html.= $GLOBALS['wiki']->Format($valeurs_fiche[$identifiant]);
                if ($containsattach) {
                    $GLOBALS['wiki']->tag = $oldpage;
                    $GLOBALS['wiki']->page = $oldpagearray;
                }
            } elseif ($formatage == 'nohtml') {
                $html .= htmlentities($valeurs_fiche[$identifiant], ENT_QUOTES, YW_CHARSET);
            } elseif ($formatage == 'html') {
                $html .= $valeurs_fiche[$identifiant];
            }
            $html .= '</span>' . "\n" . '</div> <!-- /.BAZ_rubrique -->' . "\n";
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

    list($type, $identifiant, $label, $nb_min_car, $nb_max_car, $valeur_par_defaut, $regexp, $type_input, $obligatoire,, $bulle_d_aide) = $tableau_template;
    if ($mode == 'saisie') {
        // on prepare le html de la bulle d'aide, si elle existe
        if ($bulle_d_aide != '') {
            $bulledaide = '&nbsp;&nbsp;<img class="tooltip_aide" title="' . htmlentities($bulle_d_aide, ENT_QUOTES, YW_CHARSET) . '" src="tools/bazar/presentation/images/aide.png" width="16" height="16" alt="image aide" />';
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
        if ($nb_max_car == '') {
            $nb_max_car = 255;
        }

        //par defaut il s'agit d'input html de type "text" (on precise si cela n'a pas ete entre)
        if ($type_input == '') {
            $type_input = 'url';
        }

        $input_html = '<div class="control-group form-group">' . "\n" . '<label class="control-label col-sm-3">';
        $input_html.= ($obligatoire == 1) ? '<span class="symbole_obligatoire">*&nbsp;</span>' : '';
        $input_html.= $label . $bulledaide . ' : </label>' . "\n";
        $input_html.= '<div class="controls col-sm-9">' . "\n";
        $input_html.= '<input type="' . $type_input . '"';
        $input_html.= ($defauts != 'http://') ? ' value="' . $defauts . '"' : ' placeholder="' . $defauts . '"';
        $input_html.= ' name="' . $identifiant . '" class="form-control input-xxlarge" id="' . $identifiant . '"';
        $input_html.= ($obligatoire == 1) ? ' required="required"' : '';
        $input_html.= '>' . "\n" . '</div>' . "\n" . '</div>' . "\n";

        $formtemplate->addElement('html', $input_html);
    } elseif ($mode == 'requete') {
        //on supprime la valeur, si elle est restée par défaut
        if ($valeurs_fiche[$tableau_template[1]] != 'http://') {
            return array($tableau_template[1] => $valeurs_fiche[$tableau_template[1]]);
        } else {
            return;
        }
    } elseif ($mode == 'html') {
        $html = '';
        if (isset($valeurs_fiche[$tableau_template[1]]) && $valeurs_fiche[$tableau_template[1]] != '') {
            $html.= '<div class="BAZ_rubrique" data-id="' . $tableau_template[1] . '">' . "\n" . '<span class="BAZ_label">' . $tableau_template[2] . '&nbsp;:</span>' . "\n";
            $html.= '<span class="BAZ_texte">' . "\n" . '<a href="' . $valeurs_fiche[$tableau_template[1]] . '" class="BAZ_lien" target="_blank">';
            $html.= $valeurs_fiche[$tableau_template[1]] . '</a></span>' . "\n" . '</div> <!-- /.BAZ_rubrique -->' . "\n";
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
        $label = ($obligatoire == 1) ? '<span class="symbole_obligatoire">*&nbsp;</span>' . $label : $label;
        if (isset($valeurs_fiche[$type . $identifiant]) && $valeurs_fiche[$type . $identifiant] != '') {
            if (isset($_GET['delete_file']) && $_GET['delete_file'] == $valeurs_fiche[$type . $identifiant]) {
                if (baz_a_le_droit('supp_fiche', (isset($valeurs_fiche['createur']) ? $valeurs_fiche['createur'] : ''))) {
                    if (file_exists(BAZ_CHEMIN_UPLOAD . $valeurs_fiche[$type . $identifiant])) {
                        unlink(BAZ_CHEMIN_UPLOAD . $valeurs_fiche[$type . $identifiant]);
                    }
                } else {
                    $info = '<div class="alert alert-info">' . _t('BAZ_DROIT_INSUFFISANT') . '</div>' . "\n";
                    require_once BAZ_CHEMIN . 'libs' . DIRECTORY_SEPARATOR . 'vendor/HTML/QuickForm/html.php';
                    $formtemplate->addElement(new HTML_QuickForm_html("\n" . $info . "\n"));
                }
            }
            if (file_exists(BAZ_CHEMIN_UPLOAD . $valeurs_fiche[$type . $identifiant])) {
                $lien_supprimer = $GLOBALS['wiki']->href('edit', $GLOBALS['wiki']->GetPageTag());
                $lien_supprimer.= ($GLOBALS['wiki']->config["rewrite_mode"] ? "?" : "&") . 'delete_file=' . $valeurs_fiche[$type . $identifiant];

                $html = '<div class="control-group form-group">
                    <label class="control-label col-sm-3">' . $label . ' : </label>
                    <div class="controls col-sm-9">
                    <a href="' . BAZ_CHEMIN_UPLOAD . $valeurs_fiche[$type . $identifiant] . '" target="_blank">' . $valeurs_fiche[$type . $identifiant] . '</a>' . "\n" . '<a href="' . str_replace('&', '&amp;', $lien_supprimer) . '" onclick="javascript:return confirm(\'' . _t('BAZ_CONFIRMATION_SUPPRESSION_FICHIER') . '\');" >' . _t('BAZ_SUPPRIMER') . '</a><br />
                    </div>
                    </div>';
                $formtemplate->addElement('html', $html);
                $formtemplate->addElement('hidden', $type . $identifiant, $valeurs_fiche[$type . $identifiant]);
            } else {
                if ($bulle_d_aide != '') {
                    $label = $label . ' &nbsp;&nbsp;<img class="tooltip_aide" title="' . htmlentities($bulle_d_aide, ENT_QUOTES, YW_CHARSET) . '" src="tools/bazar/presentation/images/aide.png" width="16" height="16" alt="image aide" />';
                }

                //gestion du champs obligatoire
                if (isset($obligatoire) && $obligatoire == 1) {
                    $option = array('required' => 'required');
                }

                $formtemplate->addElement('file', $type . $identifiant, $label, $option);
            }
        } else {
            if ($bulle_d_aide != '') {
                $label = $label . ' &nbsp;&nbsp;<img class="tooltip_aide" title="'
                    . htmlentities($bulle_d_aide, ENT_QUOTES, YW_CHARSET)
                    . '" src="tools/bazar/presentation/images/aide.png" width="16" height="16" alt="image aide" />';
            }

            //gestion du champs obligatoire
            if (isset($obligatoire) && $obligatoire == 1) {
                $option = array('required' => 'required');
            }

            $formtemplate->addElement('file', $type . $identifiant, $label, $option);
        }
    } elseif ($mode == 'requete') {
        if (isset($_FILES[$type . $identifiant]['name']) && $_FILES[$type . $identifiant]['name'] != '') {
            //on enleve les accents sur les noms de fichiers, et les espaces
            $nomfichier = $valeurs_fiche['id_fiche'].'_'.$identifiant.'_'
                .sanitizeFilename($_FILES[$type . $identifiant]['name']);
            $chemin_destination = BAZ_CHEMIN_UPLOAD . $nomfichier;

            //verification de la presence de ce fichier
            $extension = obtenir_extension($nomfichier);
            if ($extension != '' && extension_autorisee($extension) == true) {
                if (!file_exists($chemin_destination)) {
                    move_uploaded_file($_FILES[$type . $identifiant]['tmp_name'], $chemin_destination);
                    chmod($chemin_destination, 0755);
                } else {
                    echo 'fichier déja existant<br />';
                }
            } else {
                echo 'fichier non autorise<br />';

                return array($type . $identifiant => '');
            }

            return array($type . $identifiant => $nomfichier);
        } elseif (isset($valeurs_fiche[$type . $identifiant]) && file_exists(BAZ_CHEMIN_UPLOAD . $valeurs_fiche[$type . $identifiant])) {
            return array($type . $identifiant => $valeurs_fiche[$type . $identifiant]);
        } else {
            return array($type . $identifiant => '');
        }
    } elseif ($mode == 'recherche') {
    } elseif ($mode == 'html') {
        $html = '';
        if (isset($valeurs_fiche[$type . $identifiant]) && $valeurs_fiche[$type . $identifiant] != '') {
            $html = '<div class="BAZ_rubrique" data-id="'.
                        htmlentities($type.$identifiant, ENT_QUOTES, YW_CHARSET).'">'."\n".
                    '   <span class="BAZ_label">'._t('BAZ_DOWNLOAD_FILE').' :</span>'."\n".
                    '   <span class="BAZ_texte"><a href="'.BAZ_CHEMIN_UPLOAD.$valeurs_fiche[$type.$identifiant].'">'.
                            $valeurs_fiche[$type . $identifiant] . '</a></span>'."\n".
                    '</div>' . "\n";
        }
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
    list($type, $identifiant, $label, $hauteur_vignette, $largeur_vignette, $hauteur_image, $largeur_image, $class, $obligatoire, $apparait_recherche, $bulle_d_aide, $maxsize) = $tableau_template;

    if ($mode == 'saisie') {
        $label = ($obligatoire == 1) ? '<span class="symbole_obligatoire">*&nbsp;</span>' . $label : $label;
        // javascript pour gerer la previsualisation
        $js = 'function getOrientation(file, callback) {
  var reader = new FileReader();
  reader.onload = function(e) {

    var view = new DataView(e.target.result);
    if (view.getUint16(0, false) != 0xFFD8) return callback(-2);
    var length = view.byteLength, offset = 2;
    while (offset < length) {
      var marker = view.getUint16(offset, false);
      offset += 2;
      if (marker == 0xFFE1) {
        if (view.getUint32(offset += 2, false) != 0x45786966) return callback(-1);
        var little = view.getUint16(offset += 6, false) == 0x4949;
        offset += view.getUint32(offset + 4, little);
        var tags = view.getUint16(offset, little);
        offset += 2;
        for (var i = 0; i < tags; i++)
          if (view.getUint16(offset + (i * 12), little) == 0x0112)
            return callback(view.getUint16(offset + (i * 12) + 8, little));
      }
      else if ((marker & 0xFF00) != 0xFF00) break;
      else offset += view.getUint16(offset, false);
    }
    return callback(-1);
  };
  reader.readAsArrayBuffer(file.slice(0, 64 * 1024));
}


        function handleFileSelect(evt) {
          var target = evt.target || evt.srcElement;
          var id = target.id;
          var files = target.files; // FileList object

          // Loop through the FileList and render image files as thumbnails.
          for (var i = 0, f; f = files[i]; i++) {

            // Only process image files.
            if (!f.type.match(\'image.*\')) {
              continue;
            }';

        // si une taille maximale est indiquée, on teste
        if (!empty($maxsize)) {
            $js .= 'if (f.size>'.$maxsize.') {
                    alert("L\'image est trop grosse, maximum '.$maxsize.' octets");
                    document.getElementById(id).type = \'\';
                    document.getElementById(id).type = \'file\';
                    continue ;
                  }';
        }

        $js .= 'var reader = new FileReader();
            // Closure to capture the file information.
            reader.onload = (function(theFile) {
              return function(e) {
                getOrientation(theFile, function(orientation) {
                  var css = \'\';
                  if (orientation === 6) {
                    css = \'transform:rotate(90deg);\';
                  } else if (orientation === 8) {
                    css = \'transform:rotate(270deg);\';
                  } else if (orientation === 3) {
                    css = \'transform:rotate(180deg);\';
                  } else {
                    css = \'\';
                  }
                  // TODO: rotate image
                  //console.log(\'orientation: \' + css);
                  css = \'\';
                  // Render thumbnail.
                  var span = document.createElement(\'span\');
                  span.innerHTML = [\'<img class="img-responsive" style="\', css, \'" src="\', e.target.result,
                  \'" title="\', escape(theFile.name), \'"/>\'].join(\'\');
                  document.getElementById(\'img-\'+id).innerHTML = span.innerHTML;
                  document.getElementById(\'data-\'+id).value = e.target.result;
                  document.getElementById(\'filename-\'+id).value = theFile.name;
                });

              };
            })(f);

            // Read in the image file as a data URL.
            reader.readAsDataURL(f);
          }
        }

        var imageinputs = document.getElementsByClassName(\'yw-image-upload\');
        for (var i = 0; i < imageinputs.length; i++)
        {
           imageinputs.item(i).addEventListener(\'change\', handleFileSelect, false);
        }';
        $GLOBALS['wiki']->addJavascript($js);

        //on verifie qu'il ne faut supprimer l'image
        if (isset($_GET['suppr_image']) && isset($valeurs_fiche[$type . $identifiant]) && $valeurs_fiche[$type . $identifiant] == $_GET['suppr_image']) {
            if (baz_a_le_droit('supp_fiche', (isset($valeurs_fiche['createur']) ? $valeurs_fiche['createur'] : ''))) {
                //on efface le fichier s'il existe
                if (file_exists(BAZ_CHEMIN_UPLOAD . $valeurs_fiche[$type . $identifiant])) {
                    unlink(BAZ_CHEMIN_UPLOAD . $valeurs_fiche[$type . $identifiant]);
                }
                $nomimg = $valeurs_fiche[$type . $identifiant];

                //on efface une entrée de la base de données
                unset($valeurs_fiche[$type . $identifiant]);
                $valeur = $valeurs_fiche;
                $valeur['date_maj_fiche'] = date('Y-m-d H:i:s', time());
                $valeur['id_fiche'] = $valeurs_fiche['id_fiche'];
                if (YW_CHARSET != 'UTF-8') {
                    $valeur = array_map('utf8_encode', $valeur);
                }
                $valeur = json_encode($valeur);

                //on sauve les valeurs d'une fiche dans une PageWiki, pour garder l'historique
                $GLOBALS["wiki"]->SavePage($valeurs_fiche['id_fiche'], $valeur);

                //on affiche les infos sur l'effacement du fichier, et on reinitialise la variable pour le fichier pour faire apparaitre le formulaire d'ajout par la suite
                $info = '<div class="alert alert-info">' . _t('BAZ_FICHIER') . $nomimg . _t('BAZ_A_ETE_EFFACE') . '</div>' . "\n";
                require_once BAZ_CHEMIN . 'libs' . DIRECTORY_SEPARATOR . 'vendor/HTML/QuickForm/html.php';
                $formtemplate->addElement(new HTML_QuickForm_html("\n" . $info . "\n"));
                $valeurs_fiche[$type . $identifiant] = '';
            } else {
                $info = '<div class="alert alert-danger">' . _t('BAZ_DROIT_INSUFFISANT') . '</div>' . "\n";
                require_once BAZ_CHEMIN . 'libs' . DIRECTORY_SEPARATOR . 'vendor/HTML/QuickForm/html.php';
                $formtemplate->addElement(new HTML_QuickForm_html("\n" . $info . "\n"));
            }
        }

        if ($bulle_d_aide != '') {
            $label = $label . ' &nbsp;&nbsp;<img class="tooltip_aide" title="'
                .htmlentities($bulle_d_aide, ENT_QUOTES, YW_CHARSET)
                .'" src="tools/bazar/presentation/images/aide.png" width="16" height="16" alt="image aide" />';
        }

        //cas ou il y a une image dans la base de donnees
        if (isset($valeurs_fiche[$type . $identifiant]) && $valeurs_fiche[$type . $identifiant] != '') {
            //il y a bien le fichier image, on affiche l'image, avec possibilite de la supprimer ou de la modifier
            if (file_exists(BAZ_CHEMIN_UPLOAD . $valeurs_fiche[$type . $identifiant])) {
                require_once BAZ_CHEMIN.'libs/vendor/HTML/QuickForm/html.php';

                $formtemplate->addElement(new HTML_QuickForm_html("\n" . '<div class="control-group form-group">'."\n"
                .'<label class="control-label col-sm-3">' . "\n" . $label . '</label>' . "\n"));



                $inputhtml = '<div class="controls col-sm-9">';
                // apercu de l'image
                $inputhtml .= '<div class="row"><div class="col-xs-3">';

                $lien_supprimer = $GLOBALS['wiki']->href('edit', $GLOBALS['wiki']->GetPageTag());
                $lien_supprimer.= ($GLOBALS['wiki']->config["rewrite_mode"] ? "?" : "&") . 'suppr_image=' . $valeurs_fiche[$type . $identifiant];
                $lien_supprimer_image = '<a class="btn btn-sm btn-block btn-danger" href="' . str_replace('&', '&amp;', $lien_supprimer) . '" onclick="javascript:return confirm(\'' . _t('BAZ_CONFIRMATION_SUPPRESSION_IMAGE') . '\');" ><i class="glyphicon glyphicon-trash"></i> ' . _t('BAZ_SUPPRIMER_IMAGE') . '</a>' . "\n";
                $inputhtml .= '<label class="btn btn-block btn-default"><i class="glyphicon glyphicon-pencil"></i>
                '. _t('BAZ_MODIFIER_IMAGE').'<input type="file" style="display: none;" class="yw-image-upload" id="'.$type . $identifiant.'" name="'.$type . $identifiant.'" accept=".jpeg, .jpg, .gif, .png">
            </label>'.$lien_supprimer_image;


                $inputhtml .= '</div>'."\n";
                $inputhtml .= '<output id="img-'.$type . $identifiant.'" class="col-xs-9">'.afficher_image($identifiant, $valeurs_fiche[$type . $identifiant], $label, 'img-responsive', $largeur_vignette, $hauteur_vignette, $largeur_image, $hauteur_image).'</output>
              <input type="hidden" id="data-'.$type . $identifiant.'" name="data-'.$type . $identifiant.'" value="">'."\n"
                .'<input type="hidden" id="filename-'.$type . $identifiant.'" name="filename-'.$type . $identifiant.'" value="">'."\n"
                .'</div>'."\n".'</div>'."\n".'</div>'."\n";
                $formtemplate->addElement('html', $inputhtml);
                $formtemplate->addElement('hidden', 'oldimage_' . $type . $identifiant, $valeurs_fiche[$type . $identifiant]);
            } else {
                //le fichier image n'existe pas, du coup on efface l'entree dans la base de donnees
                echo '<div class="alert alert-danger">' . _t('BAZ_FICHIER') . $valeurs_fiche[$type . $identifiant] . _t('BAZ_FICHIER_IMAGE_INEXISTANT') . '</div>' . "\n";

                //on efface une entrée de la base de données
                unset($valeurs_fiche[$type . $identifiant]);
                unset($valeurs_fiche['data-'.$type . $identifiant]);

                $valeur = $valeurs_fiche;
                $valeur['date_maj_fiche'] = date('Y-m-d H:i:s', time());
                $valeur['id_fiche'] = $valeurs_fiche['id_fiche'];
                if (YW_CHARSET != 'UTF-8') {
                    $valeur = array_map('utf8_encode', $valeur);
                }
                $valeur = json_encode($valeur);
                //on sauve les valeurs d'une fiche dans une PageWiki, pour garder l'historique
                $GLOBALS["wiki"]->SavePage($valeurs_fiche['id_fiche'], $valeur);
            }
        } else {
            //cas ou il n'y a pas d'image dans la base de donnees, on affiche le formulaire d'envoi d'image
            $inputhtml = '<div class="control-group form-group">
  <label class="control-label col-sm-3">'.$label.'</label>
  <div class="controls col-sm-9">
    <input type="file" class="yw-image-upload" id="'.$type . $identifiant.'" name="'.$type . $identifiant.'" accept=".jpeg, .jpg, .gif, .png" '.((isset($obligatoire) && $obligatoire == 1) ? 'required': '').'>
    <output id="img-'.$type . $identifiant.'" class="col-xs-6"></output>
    <input type="hidden" id="data-'.$type . $identifiant.'" name="data-'.$type . $identifiant.'" value="">'
            .'<input type="hidden" id="filename-'.$type . $identifiant.'" name="filename-'.$type . $identifiant.'" value="">'."\n"
            .'</div>
</div>' . "\n";
            $formtemplate->addElement('html', $inputhtml);
        }
    } elseif ($mode == 'requete') {
        if (!empty($_POST['data-'.$type . $identifiant]) and !empty($_POST['filename-'.$type . $identifiant])) {
            //on enleve les accents sur les noms de fichiers, et les espaces
            $nomimage = $valeurs_fiche['id_fiche'].'_'.sanitizeFilename($_POST['filename-'.$type . $identifiant]);
            if (preg_match("/(gif|jpeg|png|jpg)$/i", $nomimage)) {
                $chemin_destination = BAZ_CHEMIN_UPLOAD . $nomimage;

                //verification de la presence de ce fichier
                if (!file_exists($chemin_destination)) {
                    file_put_contents($chemin_destination, file_get_contents($_POST['data-'.$type . $identifiant]));
                    chmod($chemin_destination, 0755);

                    //generation des vignettes
                    if ($hauteur_vignette != '' && $largeur_vignette != '' && !file_exists('cache/vignette_' . $nomimage)) {
                        $adr_img = redimensionner_image($chemin_destination, 'cache/vignette_' . $nomimage, $largeur_vignette, $hauteur_vignette);
                    }

                    //generation des images
                    if ($hauteur_image != '' && $largeur_image != '' && !file_exists('cache/image_' . '_' . $nomimage)) {
                        $adr_img = redimensionner_image($chemin_destination, 'cache/image_' . $nomimage, $largeur_image, $hauteur_image);
                    }
                } else {
                    echo '<div class="alert alert-danger">L\'image ' . $nomimage . ' existait d&eacute;ja, elle n\'a pas &eacute;t&eacute; remplac&eacute;e.</div>';
                }
            } else {
                echo '<div class="alert alert-danger">Fichier non autoris&eacute;.</div>';
            }


            return array(
                $type.$identifiant => $nomimage,
                'fields-to-remove' => array('filename-'.$type . $identifiant, 'data-'.$type . $identifiant, 'oldimage_' . $type . $identifiant)
            );
        } elseif (isset($valeurs_fiche['oldimage_' . $type . $identifiant]) && $valeurs_fiche['oldimage_' . $type . $identifiant] != '') {
            return array(
                $type . $identifiant => $valeurs_fiche['oldimage_' . $type . $identifiant],
                'fields-to-remove' => array('filename-'.$type . $identifiant, 'data-'.$type . $identifiant, 'oldimage_' . $type . $identifiant)
            );
        }
    } elseif ($mode == 'recherche') {
    } elseif ($mode == 'html') {
        if (isset($valeurs_fiche[$type . $identifiant]) && $valeurs_fiche[$type . $identifiant] != '' && file_exists(BAZ_CHEMIN_UPLOAD . $valeurs_fiche[$type . $identifiant])) {
            return afficher_image($identifiant, $valeurs_fiche[$type . $identifiant], $label, $class, $largeur_vignette, $hauteur_vignette, $largeur_image, $hauteur_image);
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
        require_once BAZ_CHEMIN.'libs/vendor/HTML/QuickForm/html.php';
        $formtemplate->addElement(new HTML_QuickForm_html("\n" . $texte_saisie . "\n"));
    } elseif ($mode == 'requete') {
        return;
    } elseif ($mode == 'formulaire_recherche') {
        $formtemplate->addElement('html', $texte_recherche);
    } elseif ($mode == 'html') {
        return $texte_fiche . "\n";
    }
}

/** metadatas() - Ajoute un look par defaut aux fiches
 *
 * @param    mixed   L'objet QuickForm du formulaire
 * @param    mixed   Le tableau des valeurs des différentes options des metadatas
 * @param    string  Type d'action pour le formulaire : saisie, modification, vue,... saisie par défaut
 * @return   void
 */
function metadatas(&$formtemplate, $tableau_template, $mode, $valeurs_fiche)
{
    list($type, $theme, $squelette, $style, $bgimg, $lang, $pages) = $tableau_template;
    // TODO : gerer la langue par defaut et les pages associées
    if ($mode == 'requete') {
        $GLOBALS['wiki']->SaveMetaDatas($valeurs_fiche['id_fiche'], array('theme' => $theme, 'style' => $style, 'squelette' => $squelette, 'bgimg' => $bgimg));
    }
}

/** acls() - change les droits par defaut de la fiche
 *
 * @param    mixed   L'objet QuickForm du formulaire
 * @param    mixed   Le tableau des valeurs des différentes options des acls (* : tous, + : les identifiés, @groupes, user,...)
 * @param    string  Type d'action pour le formulaire : saisie, modification, vue,... saisie par défaut
 * @return   void
 */
function acls(&$formtemplate, $tableau_template, $mode, $valeurs_fiche)
{
    if ($mode == 'requete') {
        list($type, $read, $write, $comment) = array_map('trim', $tableau_template);

        // le signe # ou le mot user indiquent que le createur de la fiche sera utilisé pour les droits
        if ($read == 'user' or $read == '#') {
            $read = $valeurs_fiche['nomwiki'];
        }
        if ($write == 'user' or $write == '#') {
            $write = $valeurs_fiche['nomwiki'];
        }
        if ($comment == 'user' or $comment == '#') {
            $comment = $valeurs_fiche['nomwiki'];
        }
        // hack pour que SavePage ne re-ecrit pas les droits avec les valeurs par défaut
        $GLOBALS['wiki']->pageCache[$valeurs_fiche['id_fiche']]['body'] = $valeurs_fiche;
        $GLOBALS['wiki']->pageCache[$valeurs_fiche['id_fiche']]['tag'] = $valeurs_fiche['id_fiche'];
        $GLOBALS['wiki']->pageCache[$valeurs_fiche['id_fiche']]['owner'] = $valeurs_fiche['createur'];
        $GLOBALS['wiki']->pageCache[$valeurs_fiche['id_fiche']]['comment_on'] = '';

        // on sauve les acls
        $GLOBALS['wiki']->SaveAcl($valeurs_fiche['id_fiche'], 'read', $read);
        $GLOBALS['wiki']->SaveAcl($valeurs_fiche['id_fiche'], 'write', $write);
        $GLOBALS['wiki']->SaveAcl($valeurs_fiche['id_fiche'], 'comment', $comment);
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
        $formtemplate->addElement('hidden', 'bf_titre', $template, array('id' => 'bf_titre'));
    } elseif ($mode == 'requete') {
        if (isset($GLOBALS['_BAZAR_']['provenance']) && $GLOBALS['_BAZAR_']['provenance'] == 'import') {
            $valeurs_fiche['id_fiche'] = (isset($valeurs_fiche['id_fiche']) ? $valeurs_fiche['id_fiche'] : genere_nom_wiki($valeurs_fiche['bf_titre']));
            return array('bf_titre' => $valeurs_fiche['bf_titre'], 'id_fiche' => $valeurs_fiche['id_fiche']);
        }

        preg_match_all('#{{(.*)}}#U', $valeurs_fiche['bf_titre'], $matches);
        $tab = array();
        foreach ($matches[1] as $var) {
            if (isset($valeurs_fiche[$var])) {
                //pour une listefiche ou une checkboxfiche on cherche le titre de la fiche
                if (preg_match('#^listefiche#', $var) != false || preg_match('#^checkboxfiche#', $var) != false) {
                    $tab_fiche = baz_valeurs_fiche($valeurs_fiche[$var]);
                    $valeurs_fiche['bf_titre'] = str_replace('{{' . $var . '}}', ($tab_fiche['bf_titre'] != null) ? $tab_fiche['bf_titre'] : '', $valeurs_fiche['bf_titre']);
                } elseif (preg_match('#^liste#', $var) != false || preg_match('#^checkbox#', $var) != false) {
                    //sinon on prend le label de la liste
                    //on récupere le premier chiffre (l'identifiant de la liste)
                    preg_match_all('/[0-9]{1,4}/', $var, $matches);
                    $req = 'SELECT blv_label FROM ' . BAZ_PREFIXE . 'liste_valeurs WHERE blv_ce_liste=' . $matches[0][0] . ' AND blv_valeur=' . $valeurs_fiche[$var] . ' AND blv_ce_i18n="fr-FR"';
                    $label = $GLOBALS['wiki']->LoadSingle($req);
                    $valeurs_fiche['bf_titre'] = str_replace('{{' . $var . '}}', ($label[0] != null) ? $label[0] : '', $valeurs_fiche['bf_titre']);
                } else {
                    $valeurs_fiche['bf_titre'] = str_replace('{{' . $var . '}}', $valeurs_fiche[$var], $valeurs_fiche['bf_titre']);
                }
            }
        }
        $valeurs_fiche['id_fiche'] = (isset($valeurs_fiche['id_fiche']) ? $valeurs_fiche['id_fiche'] : genere_nom_wiki($valeurs_fiche['bf_titre']));
        return array('bf_titre' => $valeurs_fiche['bf_titre'], 'id_fiche' => $valeurs_fiche['id_fiche']);
    } elseif ($mode == 'html') {
        // Le titre
        return '<h1 class="BAZ_fiche_titre">' . htmlentities($valeurs_fiche['bf_titre'], ENT_QUOTES, YW_CHARSET) . '</h1>' . "\n";
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
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') {
            $http = 'https';
        } else {
            $http = 'http';
        }
        $initmapscript = '// Init leaflet map
    var map = new L.Map(\'osmmapform\', {
        scrollWheelZoom:'.BAZ_PERMETTRE_ZOOM_MOLETTE.',
        zoomControl:'.BAZ_AFFICHER_NAVIGATION.'
    });
    var geocodedmarker;
    var OsmLayer = new L.TileLayer(\''.$http.'://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png\', {maxZoom: 18, attribution: \'\'});
    map.setView(new L.LatLng('.BAZ_MAP_CENTER_LAT.', '.BAZ_MAP_CENTER_LON.'), '.BAZ_GOOGLE_ALTITUDE.').addLayer(OsmLayer);

    $("body").on("keyup keypress", "#bf_latitude, #bf_longitude", function(){
      var pattern = /^-?[\d]{1,3}[.][\d]+$/;
      var thisVal = $(this).val();
      if(!thisVal.match(pattern)) $(this).val($(this).val().replace(/[^\d.]/g,\'\'));
    });
    $("body").on("blur", "#bf_latitude, #bf_longitude", function() {
        var point = L.latLng($("#bf_latitude").val(), $("#bf_longitude").val());
        geocodedmarker.setLatLng(point);
        map.panTo( point, {animate:true});
    });
    ';
        $geocodingscript = 'function showAddress() {
          var address = "";
          if (document.getElementById("bf_adresse")) address += document.getElementById("bf_adresse").value + \' \';
          if (document.getElementById("bf_adresse1")) address += document.getElementById("bf_adresse1").value + \' \';
          if (document.getElementById("bf_adresse2")) address += document.getElementById("bf_adresse2").value + \' \';
          if (document.getElementById("bf_ville")) address += document.getElementById("bf_ville").value + \' \';
          if (document.getElementById("bf_code_postal")) address += document.getElementById("bf_code_postal").value + \' \';
          address = address.replace(/\\("|\'|\\)/g, " ").trim();
        	geocodage( address, showAddressOk, showAddressError );
          return false;
        }

        function showAddressOk( lon, lat )
        {
        	//console.log("showAddressOk: "+lon+", "+lat);
          geocodedmarkerRefresh( L.latLng( lat, lon ) );
        }

        function showAddressError( msg )
        {
        	//console.log("showAddressError: "+msg);
        	if ( msg == "not found" ) {
				    alert("Adresse non trouvée, veuillez déplacer le point vous meme ou indiquer les coordonnées");
            geocodedmarkerRefresh( map.getCenter() );
    		  } else {
            alert("Une erreur est survenue: " + msg );
          }
    	}

        function geocodedmarkerRefresh( point )
        {
			if (geocodedmarker) map.removeLayer(geocodedmarker);
            geocodedmarker = L.marker(point, {draggable:true}).addTo(map);
            geocodedmarker.bindPopup("<div class=\"well well-sm\"><i class=\"glyphicon glyphicon-globe\"></i> Lat. : <span class=\"bf_latitude\">"+point.lat+"</span> / Lon. : <span class=\"bf_longitude\">"+point.lng+"</span></div>Déplacer le point pour le mettre a un endroit plus approprié.", {closeButton: false, closeOnClick: false}).openPopup();
            map.panTo( geocodedmarker.getLatLng(), {animate:true});
            $(\'#bf_latitude\').val(point.lat);
            $(\'#bf_longitude\').val(point.lng);
            geocodedmarker.on("dragend",function(ev){
            	this.openPopup();
            	var changedPos = ev.target.getLatLng();
            	$(\'#bf_latitude\').val(changedPos.lat);
            	$(\'#bf_longitude\').val(changedPos.lng);
            	$(\'.bf_latitude\').html(changedPos.lat);
            	$(\'.bf_longitude\').html(changedPos.lng);
            });
    	}

        '."\n";
        $GLOBALS['wiki']->AddJavascriptFile('tools/bazar/presentation/javascripts/geocoder.js');

        $deflat = '';
        $deflon = '';
        if (isset($valeurs_fiche['carte_google'])) {
            $tab = explode('|', $valeurs_fiche['carte_google']);
            if (count($tab) > 1 && !empty($tab[0]) && !empty($tab[1])) {
                $deflat = $tab[0];
                $deflon = $tab[1];
                $geocodingscript .= 'var point = L.latLng('.$deflat.', '.$deflon.');
                geocodedmarker = L.marker(point, {draggable:true}).addTo(map);
                map.panTo( geocodedmarker.getLatLng(), {animate:true});
                geocodedmarker.bindPopup("<div class=\"well well-sm\"><i class=\"glyphicon glyphicon-globe\"></i> Lat. : <span class=\"bf_latitude\">"+point.lat+"</span> / Lon. : <span class=\"bf_longitude\">"+point.lng+"</span></div>Déplacer le point pour le mettre a un endroit plus approprié.", {closeButton: false, closeOnClick: false});
                geocodedmarker.on("dragend",function(ev){
                    this.openPopup(point);
                    var changedPos = ev.target.getLatLng();
                    $(\'#bf_latitude\').val(changedPos.lat);
                    $(\'#bf_longitude\').val(changedPos.lng);
                    $(\'.bf_latitude\').html(changedPos.lat);
                    $(\'.bf_longitude\').html(changedPos.lng);
                });
                ';
            }
        }
        $GLOBALS['wiki']->AddCSSFile('tools/bazar/libs/vendor/leaflet/leaflet.css');
        $GLOBALS['wiki']->AddCSSFile(
            'tools/bazar/libs/vendor/leaflet/leaflet.ie.css',
            '<!--[if lte IE 8]>',
            '<![endif]-->'
        );
        $GLOBALS['wiki']->AddJavascriptFile('tools/bazar/libs/vendor/leaflet/leaflet.js');
        $GLOBALS['wiki']->AddJavascript($initmapscript.$geocodingscript);

        $required = (($obligatoire == 1) ? ' required="required"' : '');
        $symbole_obligatoire = ($obligatoire == 1) ? '<span class="symbole_obligatoire">*&nbsp;</span>' : '';

        $formtemplate->addElement(
            'html',
            '<div class="control-group form-group">
                <label class="control-label col-sm-3"></label>
                <div class="controls col-sm-9">
                    <a class="btn btn-primary" onclick="showAddress();">'
                    ._t('BAZ_VERIFIER_MON_ADRESSE')
                    .'</a>
            <input type="hidden" value="'.$deflat.'" id="bf_latitude" name="bf_latitude">
            <input type="hidden" value="'.$deflon.'" id="bf_longitude" name="bf_longitude">
            '
            .'<div id="osmmapform" style="margin-top:5px; width:'.BAZ_GOOGLE_IMAGE_LARGEUR.'; height:'
            .BAZ_GOOGLE_IMAGE_HAUTEUR.';"></div>
                </div>
        </div>'
        );
    } elseif ($mode == 'requete') {
        return array('carte_google' => $valeurs_fiche[$lat] . '|' . $valeurs_fiche[$lon]);
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
    if ($mode == 'saisie' || ($mode == 'formulaire_recherche' && $tableau_template[9] == 1)) {
        $bulledaide = '';
        if ($mode == 'saisie' && isset($tableau_template[10]) && $tableau_template[10] != '') {
            $bulledaide = ' &nbsp;&nbsp;<img class="tooltip_aide" title="' . htmlentities($tableau_template[10], ENT_QUOTES, YW_CHARSET) . '" src="tools/bazar/presentation/images/aide.png" width="16" height="16" alt="image aide" />';
        }

        $select_html = '<div class="control-group form-group">' . "\n" . '<label class="control-label col-sm-3">' . "\n";
        if ($mode == 'saisie' && isset($tableau_template[8]) && $tableau_template[8] == 1) {
            $select_html.= '<span class="symbole_obligatoire">*&nbsp;</span>' . "\n";
        }
        $select_html.= $tableau_template[2] . $bulledaide . ' : </label>' . "\n" . '<div class="controls col-sm-9">' . "\n" . '<select';

        $select_attributes = '';

        if ($mode == 'saisie' && $tableau_template[4] != '' && $tableau_template[4] > 1) {
            $select_attributes.= ' multiple="multiple" size="' . $tableau_template[4] . '"';
            $selectnametab = '[]';
        } else {
            $selectnametab = '';
        }

        $select_attributes.= ' class="form-control" id="' . $tableau_template[0].$tableau_template[1].$tableau_template[6].'" name="' . $tableau_template[0].$tableau_template[1].$tableau_template[6].$selectnametab . '"';

        if ($mode == 'saisie' && isset($tableau_template[8]) && $tableau_template[8] == 1) {
            $select_attributes.= ' required="required"';
        }
        $select_html.= $select_attributes . '>' . "\n";

        if (isset($valeurs_fiche[$tableau_template[0].$tableau_template[1].$tableau_template[6]]) && $valeurs_fiche[$tableau_template[0].$tableau_template[1].$tableau_template[6]] != '') {
            $def = $valeurs_fiche[$tableau_template[0].$tableau_template[1].$tableau_template[6]];
        } elseif (isset($_REQUEST[$tableau_template[0].$tableau_template[1].$tableau_template[6]]) && $_REQUEST[$tableau_template[0].$tableau_template[1].$tableau_template[6]] != '') {
            $def = $_REQUEST[$tableau_template[0].$tableau_template[1].$tableau_template[6]];
        } else {
            $def = $tableau_template[5];
        }

        /*$valliste = baz_valeurs_liste($tableau_template[1]);*/
        if ($def == '' && ($tableau_template[4] == '' || $tableau_template[4] <= 1) || $def == 0) {
            $select_html.= '<option value="" selected="selected">' . _t('BAZ_CHOISIR') . '</option>' . "\n";
        }
        $val_type = baz_valeurs_formulaire($tableau_template[1]);
        $tabquery = array();
        if (!empty($tableau_template[12])) {
            $tableau = array();
            $tab = explode('|', $tableau_template[12]);
             //découpe la requete autour des |
            foreach ($tab as $req) {
                $tabdecoup = explode('=', $req, 2);
                $tableau[$tabdecoup[0]] = trim($tabdecoup[1]);
            }
            $tabquery = array_merge($tabquery, $tableau);
        } else {
            $tabquery = '';
        }
        $tab_result = baz_requete_recherche_fiches($tabquery, 'alphabetique', $tableau_template[1], $val_type["bn_type_fiche"], 1, '', '', false, (!empty($tableau_template[13])) ? $tableau_template[13] : '');
        $select = '';
        foreach ($tab_result as $fiche) {
            $valeurs_fiche_liste = json_decode($fiche["body"], true);
            if (YW_CHARSET != 'UTF-8') {
                $valeurs_fiche_liste = array_map('utf8_decode', $valeurs_fiche_liste);
            }
            $select[$valeurs_fiche_liste['id_fiche']] = $valeurs_fiche_liste['bf_titre'];
        }
        if (is_array($select)) {
            foreach ($select as $key => $label) {
                $select_html.= '<option value="' . $key . '"';
                if ($def != '' && strstr($key, $def)) {
                    $select_html.= ' selected="selected"';
                }
                $select_html.= '>' . $label . '</option>' . "\n";
            }
        }

        $select_html.= "</select>\n</div>\n</div>\n";

        $formtemplate->addElement('html', $select_html);
    } elseif ($mode == 'requete') {
        if (isset($valeurs_fiche[$tableau_template[0].$tableau_template[1].$tableau_template[6]]) && ($valeurs_fiche[$tableau_template[0].$tableau_template[1].$tableau_template[6]] != 0)) {
            return array($tableau_template[0].$tableau_template[1].$tableau_template[6] => $valeurs_fiche[$tableau_template[0].$tableau_template[1].$tableau_template[6]]);
        }
    } elseif ($mode == 'html') {
        $html = '';
        if (isset($valeurs_fiche[$tableau_template[0].$tableau_template[1].$tableau_template[6]])
            && $valeurs_fiche[$tableau_template[0].$tableau_template[1].$tableau_template[6]] != '') {
            if ($tableau_template[3] == 'fiche') {
                $html = baz_voir_fiche(0, $valeurs_fiche[$tableau_template[0].$tableau_template[1].$tableau_template[6]]);
            } else {
                $val_fiche = baz_valeurs_fiche($valeurs_fiche[$tableau_template[0].$tableau_template[1].$tableau_template[6]]);
                $html = '';
                if (is_array($val_fiche)) {
                    $html .= '<div class="BAZ_rubrique" data-id="' . $tableau_template[0].$tableau_template[1].$tableau_template[6].'">' . "\n" . '<span class="BAZ_label">' . $tableau_template[2] . '&nbsp;:</span>' . "\n";
                    $html.= '<span class="BAZ_texte">';
                    $html.= '<a href="' . str_replace('&', '&amp;', $GLOBALS['wiki']->href('', $valeurs_fiche[$tableau_template[0].$tableau_template[1].$tableau_template[6]])) . '" class="modalbox" title="Voir la fiche ' . htmlspecialchars($val_fiche['bf_titre'], ENT_COMPAT | ENT_HTML401, YW_CHARSET) . '">' . $val_fiche['bf_titre'] . '</a></span>' . "\n" . '</div> <!-- /.BAZ_rubrique -->' . "\n";
                }
            }
        }

        return $html;
    }
}
 //fin listefiche()

/** checkboxfiche() - permet d'aller saisir et modifier un autre type de fiche
 *
 * @param    mixed   L'objet QuickForm du formulaire
 * @param    mixed   Le tableau des valeurs des différentes option pour le texte HTML
 * @param    string  Type d'action pour le formulaire : saisie, modification, vue,... saisie par défaut
 * @param    mixed  Tableau des valeurs par défauts (pour modification)
 *
 * @return   void
 */
function checkboxfiche(&$formtemplate, $tableau_template, $mode, $valeurs_fiche)
{
    $id = $tableau_template[0].$tableau_template[1].$tableau_template[6];
    //on teste la presence de filtres pour les valeurs
    $tabquery = array();
    if (isset($_GET["query"]) && !empty($_GET["query"])) {
        $tableau = array();
        $tab = explode('|', $_GET["query"]);
         //découpe la requete autour des |
        foreach ($tab as $req) {
            $tabdecoup = explode('=', $req, 2);
            $tableau[$tabdecoup[0]] = trim($tabdecoup[1]);
        }
        $tabquery = array_merge($tabquery, $tableau);
    }

    if ($mode == 'saisie' || ($mode == 'formulaire_recherche' && $tableau_template[9] == 1)) {
        $bulledaide = '';
        if ($mode == 'saisie' && isset($tableau_template[10]) && $tableau_template[10] != '') {
            $bulledaide = ' &nbsp;&nbsp;<img class="tooltip_aide" title="' . htmlentities($tableau_template[10], ENT_QUOTES, YW_CHARSET) . '" src="tools/bazar/presentation/images/aide.png" width="16" height="16" alt="image aide" />';
        }

        $checkbox_html = '<div class="control-group form-group">' . "\n" . '<label class="control-label col-sm-3">' . "\n";
        if ($mode == 'saisie' && isset($tableau_template[8]) && $tableau_template[8] == 1) {
            $checkbox_html.= '<span class="symbole_obligatoire">*&nbsp;</span>' . "\n";
        }
        $checkbox_html.= $tableau_template[2] . $bulledaide . ' : </label>' . "\n" . '<div class="controls col-sm-9"';
        if ($mode == 'saisie' && isset($tableau_template[8]) && $tableau_template[8] == 1) {
            $checkbox_html.= ' required="required"';
        }
        $checkbox_html.= '>' . "\n";

        if (isset($valeurs_fiche[$id]) &&
                  $valeurs_fiche[$id] != '') {
            $def = explode(',', $valeurs_fiche[$id]);
        } elseif (isset($_REQUEST[$id]) &&
                        $_REQUEST[$id] != '') {
            $def = explode(',', $_REQUEST[$id]);
        } else {
            $def = explode(',', $tableau_template[5]);
        }
        $val_type = baz_valeurs_formulaire($tableau_template[1]);

        //on recupere les parameres pour une requete specifique
        if ($GLOBALS['wiki']->config['global_query'] && isset($_GET['query'])) {
            $query = $tableau_template[12];
            if (!empty($query)) {
                $query.= '|' . $_GET['query'];
            } else {
                $query = $_GET['query'];
            }
        } else {
            $query = $tableau_template[12];
        }
        if (!empty($query)) {
            $tabquery = array();
            $tableau = array();
            $tab = explode('|', $query);
             //découpe la requete autour des |
            foreach ($tab as $req) {
                $tabdecoup = explode('=', $req, 2);
                $tableau[$tabdecoup[0]] = trim($tabdecoup[1]);
            }
            $tabquery = array_merge($tabquery, $tableau);
        } else {
            $tabquery = '';
        }
        $tab_result = baz_requete_recherche_fiches(
            $tabquery,
            'alphabetique',
            $tableau_template[1],
            $val_type["bn_type_fiche"],
            1,
            '',
            '',
            true,
            (!empty($tableau_template[13])) ? $tableau_template[13] : ''
        );


        $checkboxtab = '';
        foreach ($tab_result as $fiche) {
            $valeurs_fiche_liste = json_decode($fiche["body"], true);
            if (YW_CHARSET != 'UTF-8') {
                $valeurs_fiche_liste = array_map('utf8_decode', $valeurs_fiche_liste);
            }
            $checkboxtab[$valeurs_fiche_liste['id_fiche']] = $valeurs_fiche_liste['bf_titre'];
        }
        if (is_array($checkboxtab)) {
            if ($tableau_template[7] == 'tags') {
                foreach ($checkboxtab as $key => $title) {
                    $tabfiches[$key] = '{"id":"'.$key.'", "title":"'.str_replace('"', '\"', $title).'"}';
                }
                $script = '$(function(){
                    var tagsexistants = [' . implode(',', $tabfiches) . '];
                    var bazartag = [];
                    bazartag["'.$id.'"] = $(\'#formulaire .yeswiki-input-entries'.$id.'\');
                    bazartag["'.$id.'"].tagsinput({
                        itemValue: \'id\',
                        itemText: \'title\',
                        typeahead: {
                            afterSelect: function(val) { this.$element.val(""); },
                            source: tagsexistants
                        },
                        freeInput: false,
                        confirmKeys: [13, 188]
                    });'."\n";

                if (is_array($def) && count($def)>0 && !empty($def[0])) {
                    foreach ($def as $key) {
                        if (isset($tabfiches[$key])) {
                            $script .= 'bazartag["'.$id.'"].tagsinput(\'add\', '.$tabfiches[$key].');'."\n";
                        }
                    }
                }
                $script .= '});' . "\n";
                $GLOBALS['wiki']->AddJavascriptFile('tools/tags/libs/vendor/bootstrap-tagsinput.min.js');
                $GLOBALS['wiki']->AddJavascript($script);
                $checkbox_html .= '<input type="text" name="'.$id.'" class="yeswiki-input-entries yeswiki-input-entries'.$id.'">';
            } else {
                $checkbox_html.= '<input type="text" class="pull-left filter-entries" value="" placeholder="'.
                    _t('BAZAR_FILTER').'"><label class="pull-right"><input type="checkbox" class="selectall" /> '.
                    _t('BAZAR_CHECKALL') . '</label>' . "\n" . '<div class="clearfix"></div>' . "\n" .
                    '<ul class="list-bazar-entries list-unstyled">';
                foreach ($checkboxtab as $key => $label) {
                    $checkbox_html.= '<div class="yeswiki-checkbox checkbox">
                                        <label for="ckbx_'.$key.'">
                                        <input type="checkbox" id="ckbx_' . $key . '" value="1" name="' .
                                        $id.'['.$key.']"';
                    if ($def != '' && in_array($key, $def)) {
                        $checkbox_html.= ' checked="checked"';
                    }
                    $checkbox_html.= ' class="element_checkbox">'.$label.'
                    </label></div>';
                }
                $checkbox_html.= "\n".'</ul>'."\n";
                // javascript additions
                $GLOBALS['wiki']->AddJavascriptFile('tools/bazar/libs/vendor/jquery.fastLiveFilter.js');
                $script = "$(function() { $('.filter-entries').each(function() {
                                $(this).fastLiveFilter($(this).siblings('.list-bazar-entries')); });
                            });";
                $GLOBALS['wiki']->AddJavascript($script);
            }
        }

        $checkbox_html.= "</div>\n</div>\n";

        $formtemplate->addElement('html', $checkbox_html);
    } elseif ($mode == 'requete') {
        if (isset($valeurs_fiche[$tableau_template[0].$tableau_template[1].$tableau_template[6]]) && ($valeurs_fiche[$tableau_template[0].$tableau_template[1].$tableau_template[6]] != 0)) {
            return array($tableau_template[0].$tableau_template[1].$tableau_template[6] => $valeurs_fiche[$tableau_template[0].$tableau_template[1].$tableau_template[6]]);
        }
    } elseif ($mode == 'html') {
        $html = '';
        if (isset($valeurs_fiche[$tableau_template[0].$tableau_template[1].$tableau_template[6]]) && $valeurs_fiche[$tableau_template[0].$tableau_template[1].$tableau_template[6]] != '') {
            $html.= '<div class="BAZ_rubrique" data-id="' . $tableau_template[0].$tableau_template[1].$tableau_template[6].'">' . "\n" . '<span class="BAZ_label">' . $tableau_template[2] . '&nbsp;:</span>' . "\n";
            $html.= '<span class="BAZ_texte">' . "\n";
            $tab_fiche = explode(',', $valeurs_fiche[$tableau_template[0].$tableau_template[1].$tableau_template[6]]);

            foreach ($tab_fiche as $fiche) {
                $html .= '<ul>';
                if ($tableau_template[3] == 'fiche') {
                    $html.= baz_voir_fiche(0, $fiche);
                } else {
                    $val_fiche = baz_valeurs_fiche($fiche);

                    // il y a des filtres à faire sur les fiches
                    if (count($tabquery) > 0) {
                        $match = false;
                        foreach ($tabquery as $key => $value) {
                            if (strstr($val_fiche[$key], $value)) {
                                $match = true;
                            } else {
                                $match = false;
                                break;
                            }
                        }
                    }
                    if (is_array($val_fiche) && (!isset($match) || $match == true)) {
                        $html.= '<li><a href="' . str_replace('&', '&amp;', $GLOBALS['wiki']->href('', $fiche)) . '" class="modalbox" title="Voir la fiche ' . htmlspecialchars($val_fiche['bf_titre'], ENT_COMPAT | ENT_HTML401, YW_CHARSET) . '">' . $val_fiche['bf_titre'] . '</a></li>' . "\n";
                    }
                }
                $html .= '</ul>';
            }
            $html.= '</span>' . "\n" . '</div> <!-- /.BAZ_rubrique -->' . "\n";
        }

        return $html;
    }
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
        $template = BAZ_TEMPLATE_LISTE_DEFAUT;
    }
    $actionbazarliste = '{{bazarliste id="' . $tableau_template[1] . '" query="' . $query . '" nb="' . $nb . '" ' . $otherparams . ' template="' . $template . '"}}';
    if (isset($valeurs_fiche['id_fiche']) && $mode == 'saisie') {
        $html = $GLOBALS['wiki']->Format($actionbazarliste);

        //ajout lien nouvelle saisie
        $url_checkboxfiche = clone ($GLOBALS['_BAZAR_']['url']);
        $url_checkboxfiche->removeQueryString('id_fiche');
        $url_checkboxfiche->addQueryString('vue', BAZ_VOIR_SAISIR);
        $url_checkboxfiche->addQueryString('action', BAZ_ACTION_NOUVEAU);
        $url_checkboxfiche->addQueryString('wiki', $_GET['wiki'] . '/iframe');
        $url_checkboxfiche->addQueryString('id_typeannonce', $tableau_template[1]);
        $url_checkboxfiche->addQueryString('ce_fiche_liee', $valeurs_fiche['id_fiche']);
        $html.= '<a class="ajout_fiche ouvrir_overlay" href="' . str_replace('&', '&amp;', $url_checkboxfiche->getUrl()) . '" rel="#overlay-link" title="' . htmlentities($tableau_template[4], ENT_QUOTES, YW_CHARSET) . '">' . $tableau_template[4] . '</a>' . "\n";
        $formtemplate->addElement('html', $html);
    } elseif ($mode == 'html') {
        $html = '<span class="BAZ_texte">'.$GLOBALS['wiki']->Format($actionbazarliste).'</span>';
        return $html;
    }
}

// nouvelle appelation pour moins la confondre
function listefichesliees(&$formtemplate, $tableau_template, $mode, $valeurs_fiche)
{
    listefiches($formtemplate, $tableau_template, $mode, $valeurs_fiche);
}


function bookmarklet(&$formtemplate, $tableau_template, $mode, $valeurs_fiche)
{
    if ($mode == 'html') {
        if ($GLOBALS['wiki']->GetMethod() == 'iframe') {
            return '<a class="btn btn-danger pull-right" href="javascript:window.close();"><i class="glyphicon glyphicon-remove icon-remove icon-white"></i>&nbsp;Fermer cette fen&ecirc;tre</a>';
        }
    } elseif ($mode == 'saisie') {
        if ($GLOBALS['wiki']->GetMethod() != 'iframe') {
            $url_bookmarklet = clone ($GLOBALS['_BAZAR_']['url']);
            $url_bookmarklet->removeQueryString('id_fiche');
            $url_bookmarklet->addQueryString('vue', BAZ_VOIR_SAISIR);
            $url_bookmarklet->addQueryString('action', BAZ_ACTION_NOUVEAU);
            $url_bookmarklet->addQueryString('wiki', $GLOBALS['_BAZAR_']['pagewiki'] . '/iframe');
            $url_bookmarklet->addQueryString('id_typeannonce', $GLOBALS['params']['idtypeannonce']);
            $urlfield = trim($tableau_template[3]) ? $tableau_template[3] : 'bf_url' ;
            $descfield = trim($tableau_template[4]) ? $tableau_template[4] : 'bf_description' ;
            $htmlbookmarklet = "<div class=\"BAZ_info\">
                <a href=\"javascript:var wleft = (screen.width-700)/2; var wtop=(screen.height-530)/2 ;window.open('" . str_replace('&', '&amp;', $url_bookmarklet->getUrl()) . "&amp;bf_titre='+escape(document.title)+'&amp;$urlfield='+encodeURIComponent(location.href)+'&amp;$descfield='+escape(document.getSelection()), '" . $tableau_template[1] . "', 'height=530,width=700,left='+wleft+',top='+wtop+',toolbar=no,location=no,directories=no,status=no,scrollbars=yes,resizable=yes,menubar=no');void 0;\" class=\"btn btn-default\">" . $tableau_template[1] . "</a> << " . $tableau_template[2] . "</div>";
            $formtemplate->addElement('html', $htmlbookmarklet);
        }
    }
}

// Code provenant de spip :
function extension_autorisee($ext)
{
    $tables_images = array(

    // Images reconnues par PHP
    'jpg' => 'JPEG', 'png' => 'PNG', 'gif' => 'GIF', 'jpeg' => 'JPEG',

    // Autres images (peuvent utiliser le tag <img>)
    'bmp' => 'BMP', 'tif' => 'TIFF');

    $tables_sequences = array('aiff' => 'AIFF', 'anx' => 'Annodex', 'axa' => 'Annodex Audio', 'axv' => 'Annodex Video', 'asf' => 'Windows Media', 'avi' => 'AVI', 'flac' => 'Free Lossless Audio Codec', 'flv' => 'Flash Video', 'mid' => 'Midi', 'mng' => 'MNG', 'mka' => 'Matroska Audio', 'mkv' => 'Matroska Video', 'mov' => 'QuickTime', 'mp3' => 'MP3', 'mp4' => 'MPEG4', 'mpg' => 'MPEG', 'oga' => 'Ogg Audio', 'ogg' => 'Ogg Vorbis', 'ogv' => 'Ogg Video', 'ogx' => 'Ogg Multiplex', 'qt' => 'QuickTime', 'ra' => 'RealAudio', 'ram' => 'RealAudio', 'rm' => 'RealAudio', 'spx' => 'Ogg Speex', 'svg' => 'Scalable Vector Graphics', 'swf' => 'Flash', 'wav' => 'WAV', 'wmv' => 'Windows Media', '3gp' => '3rd Generation Partnership Project');

    $tables_documents = array('abw' => 'Abiword', 'ai' => 'Adobe Illustrator', 'bz2' => 'BZip', 'bin' => 'Binary Data', 'blend' => 'Blender', 'c' => 'C source', 'cls' => 'LaTeX Class', 'css' => 'Cascading Style Sheet', 'csv' => 'Comma Separated Values', 'deb' => 'Debian', 'doc' => 'Word', 'docx' => 'Word', 'djvu' => 'DjVu', 'dvi' => 'LaTeX DVI', 'eps' => 'PostScript', 'gz' => 'GZ', 'h' => 'C header', 'html' => 'HTML', 'kml' => 'Keyhole Markup Language', 'kmz' => 'Google Earth Placemark File', 'pas' => 'Pascal', 'pdf' => 'PDF', 'pgn' => 'Portable Game Notation', 'ppt' => 'PowerPoint', 'pptx' => 'PowerPoint', 'ps' => 'PostScript', 'psd' => 'Photoshop', 'rpm' => 'RedHat/Mandrake/SuSE', 'rtf' => 'RTF', 'sdd' => 'StarOffice', 'sdw' => 'StarOffice', 'sit' => 'Stuffit', 'sty' => 'LaTeX Style Sheet', 'sxc' => 'OpenOffice.org Calc', 'sxi' => 'OpenOffice.org Impress', 'sxw' => 'OpenOffice.org', 'tex' => 'LaTeX', 'tgz' => 'TGZ', 'torrent' => 'BitTorrent', 'ttf' => 'TTF Font', 'txt' => 'texte', 'xcf' => 'GIMP multi-layer', 'xspf' => 'XSPF', 'xls' => 'Excel', 'xlsx' => 'Excel', 'xml' => 'XML', 'zip' => 'Zip',

    // open document format
    'odt' => 'opendocument text', 'ods' => 'opendocument spreadsheet', 'odp' => 'opendocument presentation', 'odg' => 'opendocument graphics', 'odc' => 'opendocument chart', 'odf' => 'opendocument formula', 'odb' => 'opendocument database', 'odi' => 'opendocument image', 'odm' => 'opendocument text-master', 'ott' => 'opendocument text-template', 'ots' => 'opendocument spreadsheet-template', 'otp' => 'opendocument presentation-template', 'otg' => 'opendocument graphics-template',);

    if (array_key_exists($ext, $tables_images)) {
        return true;
    } else {
        if (array_key_exists($ext, $tables_sequences)) {
            return true;
        } else {
            if (array_key_exists($ext, $tables_documents)) {
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
    if ($pos === false) {
         // dot is not found in the filename
        return ''; // no extension
    } else {
        $extension = substr($filename, $pos + 1);
        return $extension;
    }
}
