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
// CVS : $Id: bazar.fonct.php,v 1.10 2010/03/04 14:19:03 mrflos Exp $

/**
 * Fonctions du module bazar.
 *
 *
 *@author        Florian Schmitt <florian@outils-reseaux.org>
 *@author        Alexandre Granier <alexandre@tela-botanica.org>
 * Autres auteurs :
 *@copyright     Outils-Reseaux 2000-2010
 *
 *@version       $Revision: 1.10 $ $Date: 2010/03/04 14:19:03 $
 *  +------------------------------------------------------------------------------------------------------+
 */

// +------------------------------------------------------------------------------------------------------+
// |                                            ENTETE du PROGRAMME                                       |
// +------------------------------------------------------------------------------------------------------+

use YesWiki\Bazar\Field\BazarField;
use YesWiki\Bazar\Field\EnumField;
use YesWiki\Bazar\Service\EntryManager;

/* function extractComaFromStringThenExplode
 *
 * search coma in blocks separated from " then explode by ","
 * trim each elem
 *
 * @param $input_string string to use
 * @return array containing strings
 */
 
function extractComaFromStringThenExplode($input_string)
{
    $temporary_string = trim($input_string) ;
    $result = array() ;
    // for loop to prevent infinite looping, instead of while
    for ($i = 0 ; $i < strlen($input_string) ; $i++) {
        if (empty($temporary_string)) {
            break ;
        }
        $temporary_string = trim($temporary_string) ;
        // remove coma if first
        if (substr($temporary_string, 0, 1) == ',') {
            if (strlen($temporary_string) == 1) {
                break ;
            }
            $temporary_string = substr($temporary_string, 1) ;
        }
        if (substr($temporary_string, 0, 1) == '"') {
            // empty string
            if (strlen($temporary_string) == 1) {
                break ;
            }
            // search next '",' as end caracter of name with coma
            $search_result = strpos($temporary_string, '",', 1) ;
            if ($search_result !== false) {
                // remove first '"' and last '",'
                $result[] = substr($temporary_string, 1, $search_result - 1) ;
                $temporary_string = substr($temporary_string, $search_result + 2) ;
            } else {
                // search next ','
                $search_result = strpos($temporary_string, ',', 1) ;
                if ($search_result !== false) {
                    // remove only last ','
                    $result[] = substr($temporary_string, 0, $search_result) ;
                    $temporary_string = substr($temporary_string, $search_result + 1) ;
                } else {
                    $result[] = $temporary_string ;
                    break ;
                }
            }
        } else {
            // search next ','
            $search_result = strpos($temporary_string, ',') ;
            if ($search_result !== false) {
                // remove only last ','
                $result[] = substr($temporary_string, 0, $search_result) ;
                $temporary_string = substr($temporary_string, $search_result + 1) ;
            } else {
                $result[] = $temporary_string ;
                break ;
            }
        }
    }
    $result = array_map('trim', $result);
    return $result ;
}

/**
 * interface de choix des fiches a importer.
 */
function baz_afficher_formulaire_import()
{
    $output = '';
    if ($GLOBALS['wiki']->UserIsAdmin()) {
        $id = isset($_REQUEST['id']) ? $_REQUEST['id'] : '';
        if (empty($id)) {
            $id = isset($_REQUEST['id_typeannonce']) ? $_REQUEST['id_typeannonce'] : '';
        }
        //on transforme en entier, pour eviter des attaques
        $id = (int)preg_replace('/[^\d]+/', '', $id);

        $urlParams = BAZ_VARIABLE_VOIR.'='.BAZ_VOIR_IMPORTER;
        $output .= '<form method="post" action="'.$GLOBALS['wiki']->href('', $GLOBALS['wiki']->getPageTag(), $urlParams).'" '.
        'enctype="multipart/form-data" class="form-horizontal">'."\n";

        // le fichier cvs vient d'être téléchargé, on le traite
        if (isset($_POST['submit_file'])) {
            $row = 1;
            $val_formulaire = baz_valeurs_formulaire($id);

            // Recuperation champs de la fiche
            $tableau = $val_formulaire['template'];
            $alllists = array_change_key_case(baz_valeurs_liste(), CASE_LOWER);
            $nb = 0;
            $nom_champ = array();
            $type_champ = array();
            foreach ($tableau as $ligne) {
                if ($ligne[0] != 'labelhtml') {
                    if ($ligne[0] == 'radio' || $ligne[0] == 'liste' || $ligne[0] == 'checkbox' || $ligne[0] == 'listefiche' || $ligne[0] == 'checkboxfiche') {
                        $nom_champ[] =
                        $ligne[0].$ligne[1].$ligne[6];
                        $type_champ[] =
                        $ligne[0];
                        $idliste_champ[$ligne[0].$ligne[1].$ligne[6]] =
                        $ligne[1];
                    } elseif ($ligne[0] == 'carte_google') {
                        $nom_champ[] = $ligne[1];
                        $nom_champ[] = $ligne[2];
                        $type_champ[] = $ligne[0];
                        $type_champ[] = $ligne[0];
                        ++$nb;
                    } elseif ($ligne[0] == 'utilisateur_wikini') {
                        $nom_champ[] = 'nomwiki';
                        $nom_champ[] = 'mot_de_passe_wikini';
                        $type_champ[] = $ligne[0];
                        $type_champ[] = $ligne[0];
                        ++$nb;
                    } elseif ($ligne[0] == 'titre') {
                        $nom_champ[] = 'bf_titre';
                        $type_champ[] = $ligne[0];
                    } elseif ($ligne[0] == 'inscriptionliste') {
                        $nom_champ[] = str_replace(array('@', '.'), array('',
                            '', ), $ligne[1]);
                        $type_champ[] = $ligne[0];
                    } elseif ($ligne[0] == 'image' || $ligne[0] == 'fichier') {
                        $nom_champ[] = $ligne[0].$ligne[1];
                        $type_champ[] = $ligne[0];
                    } else {
                        $nom_champ[] = $ligne[1];
                        $type_champ[] = $ligne[0];
                    }
                    ++$nb;
                }
            }

            if ((!empty($_FILES['fileimport'])) &&
                ($_FILES['fileimport']['error'] == 0)) {
                //Check if the file is csv
                $filename = basename($_FILES['fileimport']['name']);
                $ext = substr($filename, strrpos($filename, '.') + 1);
                if ($ext == 'csv') {
                    $erreur = false;
                    $outputright = '';
                    $outputerror = '';
                    if (($handle = fopen($_FILES['fileimport']['tmp_name'], 'r')) !== false) {
                        while (($data = fgetcsv($handle, 0, ',')) !== false) {
                            $valeur = array();
                            $geolocalisation = false;
                            $bf_latitude = false;
                            $bf_longitude = false;
                            $erreur = false;
                            $errormsg = array();
                            $num = count($data);
                            $dateincvs = false;
                            // la premiere ligne contient les titres des colonnes
                            if ($row == 1) {
                                // on teste s'il faut ignorer les 2 premieres colonnes
                                if ($data[0] == 'datetime_create' and $data[1] == 'datetime_latest') {
                                    $startparsing = 2;
                                    // on ajoute 2 champs vides pour les dates
                                    array_unshift($nom_champ, 'datetime_create', 'datetime_latest');
                                    array_unshift($type_champ, 'datetime_create', 'datetime_latest');
                                } else {
                                    $startparsing = 0;
                                }
                            } elseif ($row > 1) {
                                for ($c = $startparsing; $c < $num; ++$c) {
                                    if (isset($nom_champ[$c])) {
                                        $valeur[$nom_champ[$c]] = $data[$c];
                                        $valeur[$nom_champ[$c]] = str_replace(
                                            array(
                                                '&sbquo;', '&fnof;', '&bdquo;',
                                                '&hellip;', '&dagger;', '&Dagger;',
                                                '&circ;', '&permil;', '&Scaron;',
                                                '&lsaquo;', '&OElig;', '&lsquo;',
                                                '&rsquo;', '&ldquo;', '&rdquo;',
                                                '&bull;', '&ndash;', '&mdash;',
                                                '&tilde;', '&trade;', '&scaron;',
                                                '&rsaquo;', '&oelig;', '&Yuml;',
                                            ),
                                            array(chr(130), chr(131), chr(132),
                                                chr(133), chr(134), chr(135),
                                                chr(136),
                                                chr(137), chr(138), chr(139),
                                                chr(140), chr(145), chr(146),
                                                chr(147),
                                                chr(148), chr(149), chr(150),
                                                chr(151), chr(152), chr(153),
                                                chr(154),
                                                chr(155), chr(156), chr(159),
                                            ),
                                            $valeur[$nom_champ[$c]]
                                        );

                                        if ($nom_champ[$c] == 'bf_latitude' &&
                                            !empty($data[$c])) {
                                            $bf_latitude = $data[$c];
                                            $geolocalisation = true;
                                        }
                                        if ($nom_champ[$c] == 'bf_longitude' &&
                                            !empty($data[$c])) {
                                            $bf_longitude = $data[$c];
                                            $geolocalisation = true;
                                        }

                                        // recuperer les labels pour les listes et checkbox sinon, id ou index
                                        if (($type_champ[$c] == 'checkbox' ||
                                            $type_champ[$c] == 'liste'||
                                            $type_champ[$c] == 'radio') &&
                                            !empty($data[$c])) {
                                            if ($type_champ[$c] == 'liste' || $type_champ[$c] == 'radio') {
                                                $idval = array_search(
                                                    $data[$c],
                                                    $alllists[strtolower($idliste_champ[$nom_champ[$c]])]['label']
                                                );
                                                // le label n'est pas trouvé, vérifier si c'est un nombre ou une clé
                                                if ((! $idval) && (is_numeric($data[$c]) ||
                                                        array_key_exists(
                                                            $data[$c],
                                                            $alllists[strtolower($idliste_champ[$nom_champ[$c]])]['label']
                                                        ))) {
                                                    $idval = $data[$c] ;
                                                }
                                            } elseif ($type_champ[$c] ==
                                                'checkbox') {
                                                $tab_chkb = extractComaFromStringThenExplode($data[$c]);
                                                $k = strtolower($idliste_champ[$nom_champ[$c]]);
                                                $refList = $alllists[$k]['label'];
                                                $tab_id = array();
                                                foreach ($tab_chkb as $value) {
                                                    // dirty patch to permits "index" instead of "label"
                                                    // https://framagit.org/Artefacts/ATable-guide-web/issues/30
                                                    if (is_numeric($value)) {
                                                        $tab_id[] = $value ;
                                                    } else {
                                                        $res = array_search($value, $refList);
                                                        // le label n'est pas trouvé, vérifier si c'est une clé et l'utiliser
                                                        if ($res === false && array_key_exists($value, $refList)) {
                                                            $res = $value;
                                                        }
                                                        $tab_id[] = $res ;
                                                    }
                                                }
                                                $idval = implode(',', $tab_id);
                                            }
                                            $valeur[$nom_champ[$c]] = $idval;
                                        }

                                        // recuperer les id pour les listefiche et checkboxfiche plutot que leur bf_titre
                                        if (($type_champ[$c] == 'checkboxfiche' || $type_champ[$c] == 'listefiche') &&
                                            isset($data[$c]) && !empty($data[$c])) {
                                            $tab_chkb = extractComaFromStringThenExplode($data[$c]);
                                            $tab_id = array();
                                            $idfiche = str_replace($type_champ[$c], '', $nom_champ[$c]);
                                            if (!isset($allentries[$idfiche])) {
                                                $fa = $GLOBALS['wiki']->services->get(EntryManager::class)->search();
                                                $tabfa = array();
                                                foreach ($fa as $valfa) {
                                                    $tabfa[$valfa['id_fiche']] = $valfa['bf_titre'];
                                                }
                                                $allentries[$id] = $tabfa;
                                            }
                                            foreach ($tab_chkb as $value) {
                                                $idval = array_search(
                                                    $value,
                                                    $allentries[$id]
                                                );
                                                if ($idval === false && array_key_exists(
                                                    $value,
                                                    $allentries[$id]
                                                )) {
                                                    $idval = $value;
                                                }
                                                $tab_id[] = $idval;
                                            }
                                            $idval = implode(',', $tab_id);
                                            $valeur[$nom_champ[$c]] = $idval;
                                        }

                                        // traitement des images (doivent être présentes dans le dossier files du wiki)
                                        if (($type_champ[$c]) == 'image' && isset($data[$c]) && !empty($data[$c])) {
                                            $imageorig = trim($valeur[$nom_champ[$c]]);
                                            $nomimage = renameUrlToSanitizedFilename($imageorig);
                                            // test si c'est url vers l'image
                                            $fileCopied = copyUrlToLocalFile($imageorig, BAZ_CHEMIN_UPLOAD.$nomimage);
                                            if ($fileCopied) {
                                                $valeur[$nom_champ[$c]] = $nomimage;
                                            } elseif (file_exists(BAZ_CHEMIN_UPLOAD.$imageorig)) {
                                                if (preg_match('/(gif|jpeg|png|jpg)$/i', $nomimage)) {
                                                    //on enleve les accents sur les noms de fichiers, et les espaces
                                                    $nomimage = preg_replace(
                                                        '/&([a-z])[a-z]+;/i',
                                                        '$1',
                                                        $imageorig
                                                    );
                                                    $nomimage = str_replace(' ', '_', $nomimage);
                                                    $valeur[$nom_champ[$c]] = $nomimage;
                                                    $chemin_destination = BAZ_CHEMIN_UPLOAD.$nomimage;

                                                    //verification de la presence de ce fichier
                                                    if (!file_exists($chemin_destination)) {
                                                        rename(
                                                            BAZ_CHEMIN_UPLOAD.
                                                            $imageorig,
                                                            $chemin_destination
                                                        );
                                                        chmod($chemin_destination, 0755);
                                                    }
                                                } else {
                                                    $errormsg[] = _t('BAZ_BAD_IMAGE_FILE_EXTENSION');
                                                    $erreur = true;
                                                }
                                            } else {
                                                $errormsg[] =
                                                _t('BAZ_IMAGE_FILE_NOT_FOUND').
                                                ' : '.$imageorig;
                                                $erreur = true;
                                            }
                                        }

                                        // traitement des images (doivent être présentes dans le dossier files du wiki)
                                        if (($type_champ[$c]) == 'fichier' && isset($data[$c]) && !empty($data[$c])) {
                                            $fileUrl = trim($valeur[$nom_champ[$c]]);
                                            $file = renameUrlToSanitizedFilename($fileUrl);
                                            // test si c'est url vers l'image
                                            $fileCopied = copyUrlToLocalFile($fileUrl, BAZ_CHEMIN_UPLOAD.$file);
                                            if ($fileCopied) {
                                                $valeur[$nom_champ[$c]] = $file;
                                            } elseif (file_exists(BAZ_CHEMIN_UPLOAD.$fileUrl)) {
                                                $valeur[$nom_champ[$c]] = $file;
                                                $chemin_destination = BAZ_CHEMIN_UPLOAD.$file;
                                                //verification de la presence de ce fichier
                                                if (!file_exists($chemin_destination)) {
                                                    rename(
                                                        BAZ_CHEMIN_UPLOAD.$fileUrl,
                                                        $chemin_destination
                                                    );
                                                    chmod($chemin_destination, 0755);
                                                }
                                            } else {
                                                $errormsg[] = _t('BAZ_FILE_NOT_FOUND').' : '.$fileUrl;
                                                $erreur = true;
                                            }
                                        }

                                        if ($geolocalisation) {
                                            $valeur['carte_google'] =
                                            $bf_latitude.'|'.$bf_longitude;
                                        }
                                    }
                                }
                                // test si $valeur contient au moins un titre
                                if (!empty($valeur['bf_titre'])) {
                                    $valeur['id_fiche'] = genere_nom_wiki($valeur['bf_titre']);
                                    $valeur['id_typeannonce'] = $id;
                                    $valeur['date_creation_fiche'] = date('Y-m-d H:i:s', time());
                                    $valeur['date_maj_fiche'] = date('Y-m-d H:i:s', time());
                                    if ($GLOBALS['wiki']->UserIsAdmin()) {
                                        $valeur['statut_fiche'] = 1;
                                    } else {
                                        $valeur['statut_fiche'] = $GLOBALS['wiki']->config['BAZ_ETAT_VALIDATION'];
                                    }
                                    $valeur['date_debut_validite_fiche'] = date('Y-m-d', time());
                                    $valeur['date_fin_validite_fiche'] = '0000-00-00';

                                    if (count($errormsg) > 0) {
                                        $outputerror .=
                                        '<label>
                                                <input type="checkbox" disabled> '
                                        .$valeur['bf_titre'].

                                        '
                                                </label>
                                                <a class="btn-mini btn-xs btn btn-default" data-target="#collapse'
                                        .$valeur['id_fiche'].$row.'" data-toggle="collapse">'
                                        .'<i class="fa fa-eye-open icon-eye-open icon-white"></i> '
                                        ._t('BAZ_SEE_ENTRY').'</a>
                                    <div class="panel panel-danger">
                                        <div id="collapse'.$valeur['id_fiche'].$row.'" class="panel-collapse collapse">
                                        <div class="panel-body">
                                            <div class="alert alert-danger">'.
                                        implode('<br>', $errormsg).'</div>'.
                                        baz_voir_fiche(0, $valeur).'
                                        </div>
                                        </div>
                                    </div>'."\n";
                                    } else {
                                        $outputright .=
                                        '<label>
                                                <input type="checkbox" name="importfiche['.$valeur['id_fiche'].$row.']" value=\''
                                        .base64_encode(serialize($valeur)).
                                        '\'> '.$valeur['bf_titre'].
                                        '
                                                </label>
                                                <a class="btn-mini btn-xs btn btn-default" data-target="#collapse'.

                                        $valeur['id_fiche'].$row.
                                        '" data-toggle="collapse">'
                                        .
                                        '<i class="fa fa-eye-open icon-eye-open icon-white"></i> '
                                        ._t('BAZ_SEE_ENTRY').'</a>
                                        <div class="panel panel-default">
                                            <div id="collapse'.
                                        $valeur['id_fiche'].$row.'" class="panel-collapse collapse">
                                            <div class="panel-body">'.
                                        baz_voir_fiche(0, $valeur).'
                                            </div>
                                            </div>
                                        </div>'."\n";
                                    }
                                }
                            }
                            ++$row;
                        }
                        fclose($handle);
                    }

                    $output .=

                    '<div class="checkbox">
                                <label class="checkbox">
                                    <input data-target="#accordion-import" type="checkbox" class="selectall"> '
                    ._t('BAZ_SELECT_ALL').
                    '
                                </label>
                            </div>
                            <div class="panel-group accordion-group no-dblclick" id="accordion-import">'."\n".
                    $outputerror.$outputright."\n".
                    '</div><!-- /#accordion-import -->'."\n".

                    '<div class="checkbox">
                                <label class="checkbox">
                                    <input data-target="#accordion-import" type="checkbox" class="selectall"> '
                    ._t('BAZ_SELECT_ALL').'
                                </label>
                            </div>'."\n".

                    '<input type="hidden" value="'.$id.
                    '" name="id" />'."\n".
                    '<button class="btn btn-primary" type="submit">'
                    ._t('BAZ_IMPORT_SELECTION').'</button>'."\n";
                }
            }
        } elseif (isset($_POST['importfiche'])) {
            if (!isset($GLOBALS['importdone'])) {
                // Pour les traitements particulier lors de l import
                $GLOBALS['_BAZAR_']['provenance'] = 'import';
                $importList = '';
                $nb = 0;
                foreach ($_POST['importfiche'] as $fiche) {
                    $fiche = unserialize(base64_decode($fiche));
                    $fiche = array_map('strval', $fiche);

                    $fiche['antispam'] = 1;
                    $fiche = $GLOBALS['wiki']->services->get(EntryManager::class)->create($id, $fiche);

                    ++$nb;
                    $importList .= ' '.$nb.') [['.$fiche['id_fiche'].' '. $fiche['bf_titre'].']]'."\n";
                }
                $output .= '<div class="alert alert-success">'. _t('BAZ_NOMBRE_FICHE_IMPORTE').' '.$nb.'</div>'."\n".
                $GLOBALS['wiki']->Format($importList);
                $GLOBALS['importdone'] = true;
            }
        } else {
            // Affichage par defaut
            //On choisit un type de fiches pour parser le csv en consequence
            //requete pour obtenir l'id et le label des types d'annonces
            $resultat = baz_valeurs_formulaire('', $GLOBALS['params']['categorienature']);

            //s'il y a plus d'un choix possible, on propose
            if (count($resultat) >= 1) {
                $output .=
                '<div class="control-group form-group">'."\n".
                '<label class="control-label col-sm-3">'."\n"

                ._t('BAZ_TYPE_FICHE_IMPORT').' :</label>'."\n".
                '<div class="controls col-sm-9">';
                $output .= '<select class="form-control" name="id" '
                .'onchange="javascript:this.form.submit();">'."\n";

                //si l'on n'a pas deja choisi de fiche, on demarre sur l'option CHOISIR, vide
                if ($id == '') {
                    $output .=
                    '<option value="" selected="selected">'.
                    _t('BAZ_CHOISIR').'</option>'."\n";
                }

                //on dresse la liste de types de fiches
                foreach ($resultat as $ligne) {
                    $output .= '<option value="'.$ligne['bn_id_nature']
                    .'"'
                    .($id == $ligne['bn_id_nature'] ?
                        ' selected="selected"' : '')
                    .'>'.$ligne['bn_label_nature'].'</option>'."\n";
                }
                $output .= '</select>'."\n".'</div>'."\n".'</div>'.
                "\n";
            } else {
                $output .= _t('BAZ_PAS_DE_FORMULAIRES_TROUVES')."\n";
            }

            if ($id != '') {
                $val_formulaire = baz_valeurs_formulaire($id);
                $output .=
                '<div class="control-group form-group">'."\n".
                '<label class="control-label col-sm-3">'."\n"
                ._t('BAZ_FICHIER_CSV_A_IMPORTER').' :</label>'."\n".
                '<div class="controls col-sm-9">';
                $output .=
                '<input type="file" class="form-control" name="fileimport" id="idfileimport" />'.
                "\n".'</div>'."\n".'</div>'."\n";
                $output .= '<div class="control-group form-group import-file">'."\n"
                .'<div class="controls col-sm-9 col-sm-offset-3">'."\n".
                '<input name="submit_file" type="submit" value="'
                ._t('BAZ_IMPORTER_CE_FICHIER').
                '" class="btn btn-primary" />'."\n".'</div>'."\n".
                '</div>'."\n";
                $output .= '<div class="alert alert-info">'."\n"
                .'<a data-dismiss="alert" class="close" type="button">&times;</a>'."\n"
                ._t('BAZ_ENCODAGE_CSV')."\n".'</div>'."\n";

                // TODO reprendre code ci apres
                //on parcourt le template du type de fiche pour fabriquer un csv pour l'exemple
                $tableau = $val_formulaire['template'];
                $nb = 0;
                $csv = '';
                foreach ($tableau as $ligne) {
                    if ($ligne[0] != 'labelhtml') {
                        if ($ligne[0] == 'liste' || $ligne[0] == 'checkbox' || $ligne[0] == 'radio' ||
                            $ligne[0] == 'listefiche' || $ligne[0] ==
                            'checkboxfiche') {
                            $csv .= _convert(
                                '"'.str_replace('"', '""', $ligne[2]).((isset($ligne[8]) && $ligne[8] == 1) ? ' *' : '').'",',
                                YW_CHARSET
                            );
                        } elseif ($ligne[0] == 'carte_google') {
                            // cas de la carto
                            $csv .= _convert(
                                '"'.str_replace('"', '""', $ligne[1]).((isset($ligne[4]) && $ligne[4] == 1) ? ' *' : '').'",',
                                YW_CHARSET
                            );
                            $csv .= _convert(
                                '"'.str_replace('"', '""', $ligne[2]).((isset($ligne[4]) && $ligne[4] == 1) ? ' *' : '').'",',
                                YW_CHARSET
                            );
                            ++$nb;
                        } elseif ($ligne[0] == 'titre') {
                            // Champ titre aggregeant plusieurs champs
                            $csv .= _convert(
                                '"'.str_replace('"', '""', 'Titre calculé').((isset($ligne[8]) && $ligne[8] == 1) ? ' *' : '').'",',
                                YW_CHARSET
                            );
                        } elseif ($ligne[0] == 'utilisateur_wikini') {
                            // utilisateur et mot de passe
                            $csv .= _convert(
                                '"'.str_replace('"', '""', 'NomWiki').((isset($ligne[8]) && $ligne[8] == 1) ? ' *' : '').'",',
                                YW_CHARSET
                            );
                            $csv .= _convert(
                                '"'.str_replace('"', '""', 'Mot de passe').((isset($ligne[8]) && $ligne[8] == 1) ? ' *' : '').'",',
                                YW_CHARSET
                            );
                            ++$nb;
                        } elseif ($ligne[0] == 'inscriptionliste') {
                            // Nom de la liste et etat de l'abonnement
                            $csv .= _convert(
                                '"'.str_replace('"', '""', $ligne[1]).((isset($ligne[8]) && $ligne[8] == 1) ? ' *' : '').'",',
                                YW_CHARSET
                            );
                        } else {
                            $csv .= _convert(
                                '"'.str_replace('"', '""', $ligne[2]).((isset($ligne[8]) && $ligne[8] == 1) ? ' *': '').'",',
                                YW_CHARSET
                            );
                        }
                        ++$nb;
                    }
                }
                $csv = substr(trim($csv), 0, -1)."\r\n";

                for ($i = 1; $i < 4; ++$i) {
                    for ($j = 1; $j < ($nb + 1); ++$j) {
                        $csv .= '"ligne '.$i.' - champ '.$j.'", ';
                    }
                    $csv = substr(trim($csv), 0, -1)."\r\n";
                }
                $output .=
                '<em>'._t('BAZ_EXEMPLE_FICHIER_CSV').
                $val_formulaire['bn_label_nature'].'</em>'."\n";
                $output .= '<pre class="precsv">'."\n".$csv."\n".
                '</pre>'."\n";

                //on cree le lien vers ce fichier
                $output .=
                '<a href="#" onclick="downloadCSV($(\'.precsv\').text(), \'export-bazar-modele-'.$id.'.csv\');return false;" class="btn btn-neutral link-csv-file">'.
                '<i class="fa fa-download"></i>'.
                _t('BAZ_TELECHARGER_FICHIER_IMPORT_CSV').'</a>'."\n";
            }
        }
        $output .= '</form>'."\n";
    } else {
        $output .=
        '<div class="alert alert-error alert-danger">'.
        _t('BAZ_NEED_ADMIN_RIGHTS').'.</div>'."\n";
    }

    return $output;
}

/**
 * interface de choix des fiches a exporter.
 */
function baz_afficher_formulaire_export()
{
    $output = '';

    if ($GLOBALS['wiki']->UserIsAdmin()) {
        $id = isset($_REQUEST['id']) ? $_REQUEST['id'] : (isset($_POST['id_typeannonce']) ? $_POST['id_typeannonce'] : '');
        //on transforme en entier, pour eviter des attaques
        $id = (int)preg_replace('/[^\d]+/', '', $id);

        //On choisit un type de fiches pour parser le csv en consequence
        $resultat = baz_valeurs_formulaire('', $GLOBALS['params']['categorienature']);

        $output .=
        '<form method="post" class="form-horizontal" action="'.$GLOBALS['wiki']
            ->Href()
        .(($GLOBALS['wiki']->GetMethod() != 'show') ?
            '/'.$GLOBALS['wiki']->GetMethod() :
            '&amp;'.BAZ_VARIABLE_VOIR.'='.BAZ_VOIR_EXPORTER).'">'."\n";

        //s'il y a plus d'un choix possible, on propose
        if (count($resultat) >= 1) {
            $output .=
            '<div class="control-group form-group">'."\n"

            .'<label class="control-label col-sm-3">'."\n".
            _t('BAZ_TYPE_FICHE_EXPORT').' :</label>'."\n"
            .'<div class="controls col-sm-9">';
            $output .=

            '<select class="form-control" name="id_typeannonce" onchange="javascript:this.form.submit();">'."\n";

            //si l'on n'a pas deja choisit de fiche, on demarre sur l'option CHOISIR, vide
            if (!isset($_POST['id_typeannonce'])) {
                $output .=
                '<option value="" selected="selected">'._t('BAZ_CHOISIR').
                '</option>'."\n";
            }

            //on dresse la liste de types de fiches
            foreach ($resultat as $ligne) {
                $output .= '<option value="'.$ligne['bn_id_nature'].'"'
                .(($id == $ligne['bn_id_nature']) ?
                    ' selected="selected"' : '').'>'
                .$ligne['bn_label_nature'].'</option>'."\n";
            }
            $output .= '</select>'."\n".'</div>'."\n".'</div>'."\n";
        } else {
            //sinon c'est vide
            $output .=
            '<div class="alert alert-danger">'.
            _t('BAZ_PAS_DE_FORMULAIRES_TROUVES').'</div>'."\n";
        }
        $output .= "\n".'</form>'."\n";

        if ($id == '') {
            return $output;
        }

        $val_formulaire = baz_valeurs_formulaire($id);

        //on parcourt le template du type de fiche pour fabriquer un csv pour l'exemple
        $tableau = $val_formulaire['template'];
        $nb = 0;
        $csv = '';
        $tab_champs = array();

        foreach ($tableau as $ligne) {
            if ($ligne[0] != 'labelhtml') {
                // listes
                if ($ligne[0] == 'radio' || $ligne[0] == 'liste' || $ligne[0] == 'checkbox'
                    || $ligne[0] == 'listefiche' || $ligne[0] ==
                    'checkboxfiche') {
                    $tab_champs[] = $ligne[0].'|'.$ligne[1].'|'.
                    $ligne[6];
                    $csv .= '"'.str_replace('"', '""', $ligne[2])
                    .((isset($ligne[8]) && $ligne[8] == 1) ? ' *' : '').
                    '",';
                } elseif ($ligne[0] == 'image' || $ligne[0] == 'fichier') {
                    // image et fichiers
                    $tab_champs[] = $ligne[0].'|'.$ligne[1];
                    $csv .= '"'.str_replace('"', '""', $ligne[2])
                    .((isset($ligne[8]) && $ligne[8] == 1) ? ' *' : '').
                    '",';
                } elseif ($ligne[0] == 'carte_google') {
                    // cas de la carto
                    $tab_champs[] = $ligne[1];
                    // bf_latitude
                    $tab_champs[] = $ligne[2];
                    // bf_longitude
                    $csv .= '"'.str_replace('"', '""', $ligne[1])
                    .((isset($ligne[4]) && $ligne[4] == 1) ? ' *' : '').
                    '",';
                    $csv .= '"'.str_replace('"', '""', $ligne[2])
                    .((isset($ligne[4]) && $ligne[4] == 1) ? ' *' : '').
                    '",';
                } elseif ($ligne[0] == 'titre') {
                    // Champ titre aggregeant plusieurs champs
                    $tab_champs[] = 'bf_titre';
                    $csv .= '"'.str_replace('"', '""', 'Titre calculé')
                    .((isset($ligne[8]) && $ligne[8] == 1) ? ' *' : '').
                    '",';
                } elseif ($ligne[0] == 'utilisateur_wikini') {
                    // Champ titre aggregeant plusieurs champs
                    $tab_champs[] = 'nomwiki';
                    $tab_champs[] = 'mot_de_passe_wikini';
                    $csv .= '"'.str_replace('"', '""', 'NomWiki')
                    .((isset($ligne[8]) && $ligne[8] == 1) ? ' *' : '').
                    '",';
                    $csv .= '"'.str_replace('"', '""', 'Mot de passe')
                    .((isset($ligne[8]) && $ligne[8] == 1) ? ' *' : '').
                    '",';
                } elseif ($ligne[0] == 'inscriptionliste') {
                    // Nom de la liste et etat de l'abonnement
                    $tab_champs[] = str_replace(array('@', '.'), array('',
                        '', ), $ligne[1]);
                    // nom de la liste
                    $csv .= '"'.str_replace('"', '""', $ligne[1])
                    .((isset($ligne[8]) && $ligne[8] == 1) ? ' *' : '').
                    '",';
                } else {
                    $tab_champs[] = $ligne[1];
                    $csv .= '"'.str_replace('"', '""', $ligne[2])
                    .((isset($ligne[8]) && $ligne[8] == 1) ? ' *' : '').
                    '",';
                }
                ++$nb;
            }
        }

        // CSV file headers
        //$csv = substr(trim($csv), 0, -1)."\r\n";
        $csv = '"datetime_create","datetime_latest",'.substr(trim($csv), 0, -1)."\n";

        // chaine de recherche
        $q = '';
        if (isset($_GET['q']) and !empty($_GET['q'])) {
            $q = $_GET['q'];
        }

        // TODO : gerer les queries
        $query = '';

        //on recupere toutes les fiches du type choisi et on les met au format csv
        $tableau_fiches = $GLOBALS['wiki']->services->get(EntryManager::class)->search([ 'queries'=>$query, 'formsIds'=>[$id], 'keywords' => $q ]);
        $total = count($tableau_fiches);
        foreach ($tableau_fiches as $fiche) {
            // create date and latest date
            $fiche_time_create = date_create_from_format('Y-m-d H:i:s', $fiche['date_creation_fiche']);
            $fiche_time_latest = date_create_from_format('Y-m-d H:i:s', $fiche['date_maj_fiche']);

            $tab_csv = array();

            foreach ($tab_champs as $index) {
                $tabindex = explode('|', $index);
                $index = str_replace('|', '', $index);

                //ces types de champs necessitent un traitement particulier
                if ($tabindex[0] == 'radio' || $tabindex[0] == 'liste' || $tabindex[0] == 'checkbox'
                    || $tabindex[0] == 'listefiche' || $tabindex[0] ==
                    'checkboxfiche') {
                        
                    // liste ou fiche
                    if ($tabindex[0] == 'radio' || $tabindex[0] == 'liste' || $tabindex[0] == 'checkbox') {
                        $values_liste = baz_valeurs_liste($tabindex[1]);

                        $tabresult = isset($fiche[$index]) ? explode(',', $fiche[$index]) : null ;
                        if (is_array($tabresult)) {
                            $labels_result = '';
                            foreach ($tabresult as $id) {
                                $res_value = $values_liste["label"][$id] ;
                                if (isset($res_value)) {
                                    if ((strpos($res_value, ',') !== false || substr($res_value, 0, 1) == '"')
                                            && $tabindex[0] == 'checkbox') {
                                        //  for checkbox if value contains ',' or begin with '"' add '"' before and after
                                        $res_value = (strpos($res_value, '",') === false) ? '"' . $res_value . '"' : $id ;
                                    }
                                    if ($labels_result == '') {
                                        $labels_result = $res_value;
                                    } else {
                                        $labels_result.= ', ' . $res_value;
                                    }
                                }
                            }
                            $fiche[$index] = $labels_result ;
                        }
                    } else {
                        $tabresult = isset($fiche[$index]) ? explode(',', $fiche[$index]) : null ;
                        if (is_array($tabresult)) {
                            $labels_result = '';
                            foreach ($tabresult as $id) {
                                $val_fiche = $GLOBALS['wiki']->services->get(EntryManager::class)->getOne($id);
                                if (is_array($val_fiche)) {
                                    $res_value = $val_fiche['bf_titre'] ;
                                    if ((strpos($res_value, ',') !== false || substr($res_value, 0, 1) == '"')
                                            && $tabindex[0] == 'checkboxfiche') {
                                        //  for checkboxfiches if title contains ',' or begin with "
                                        //  add '"' before and after
                                        //  except if '",' for compatibility for import
                                        $res_value = (strpos($res_value, '",') === false) ? '"' . $res_value . '"' : $id ;
                                    }
                                    if ($labels_result == '') {
                                        $labels_result = $res_value;
                                    } else {
                                        $labels_result.= ', ' . $res_value;
                                    }
                                }
                            }
                            $fiche[$index] = $labels_result ;
                        }
                    }
                }

                // si la valeur existe, on l'affiche
                if (isset($fiche[$index])) {
                    if ($index == 'mot_de_passe_wikini') {
                        $fiche[$index] = md5($fiche[$index]);
                    }
                    // ajoute l'URL de base aux images et fichiers
                    if ($tabindex[0] == 'image' || $tabindex[0] == 'fichier') {
                        $fiche[$index] = $GLOBALS['wiki']->getBaseUrl() . '/' . BAZ_CHEMIN_UPLOAD . $fiche[$index];
                    }
                    $tab_csv[] = html_entity_decode(
                        '"'.str_replace('"', '""', $fiche[$index]).'"'
                    );
                } else {
                    $tab_csv[] = '';
                }
            }

            //$csv .= implode(',', $tab_csv)."\r\n";
            $csv.= '"'.date_format($fiche_time_create, 'd/m/Y H:i:s')
                .'","'.date_format($fiche_time_latest, 'd/m/Y H:i:s')
                .'",'.implode(',', $tab_csv)."\n";
        }

        //$csv = _convert( $csv );
        $output .= '<em>'._t('BAZ_VISUALISATION_FICHIER_CSV_A_EXPORTER')

        .$val_formulaire['bn_label_nature'].' - '._t('BAZ_TOTAL_FICHES')
        .' : '.$total.'</em>'."\n";
        $output .= '<pre class="precsv">'."\n".$csv."\n".'</pre>'.
        "\n";

        //on cree le lien vers ce fichier
        $output .=
        '<a href="#" onclick="downloadCSV($(\'.precsv\').html(), \'export-bazar-'.$id.'.csv\');return false;" class="btn btn-neutral link-csv-file">'.
        '<i class="fa fa-download"></i>'.
        _t('BAZ_TELECHARGER_FICHIER_EXPORT_CSV').'</a>'."\n";
    } else {
        $output .= '<div class="alert alert-error alert-danger">'.
        _t('BAZ_NEED_ADMIN_RIGHTS').'.</div>'."\n";
    }

    return $output;
}

/** publier_fiche () - Publie ou non dans les fichiers XML la fiche bazar d'un utilisateur
 * @global boolean Valide: oui ou non
 */
function publier_fiche($valid)
{

    //l'utilisateur a t'il le droit de valider
    if (baz_a_le_droit('valider_fiche')) {
        if ($valid == 0) {
            $requete =
            'UPDATE '.$GLOBALS['wiki']->config['table_prefix'].
            'fiche SET  bf_statut_fiche=2 WHERE bf_id_fiche="'.
            $_GET['id_fiche'].'"';
            echo '<div class="alert alert-success">'."\n"
            .'<a data-dismiss="alert" class="close" type="button">&times;</a>'
            ._t('BAZ_FICHE_PAS_VALIDEE').'</div>'."\n";
        } else {
            $requete =
            'UPDATE '.$GLOBALS['wiki']->config['table_prefix'].
            'fiche SET  bf_statut_fiche=1 WHERE bf_id_fiche="'.
            $_GET['id_fiche'].'"';
            echo '<div class="alert alert-success">'."\n"
            .'<a data-dismiss="alert" class="close" type="button">&times;</a>'
            ._t('BAZ_FICHE_VALIDEE').'</div>'."\n";
        }

        // ====================Mise a jour de la table '.$GLOBALS['wiki']->config['table_prefix'].'fiche====================
        $resultat = $GLOBALS['wiki']->query($requete);

        unset($resultat);

        //TODO envoie mail annonceur
    }

    return;
}

function multiArraySearch($array, $key, $value)
{
    $results = array();

    if (is_array($array)) {
        if (isset($array[$key]) && $array[$key] == $value) {
            $results[] = $array;
        }

        foreach ($array as $subarray) {
            $results = array_merge($results, multiArraySearch($subarray, $key, $value));
        }
    }

    return $results;
}

function baz_forms_and_lists_ids()
{
    foreach (baz_valeurs_liste() as $listId => $list) {
        $lists[$listId] = $list['titre_liste'];
    }

    $requete = 'SELECT bn_id_nature, bn_label_nature FROM '.$GLOBALS['wiki']->config['table_prefix'].'nature';
    $result = $GLOBALS['wiki']->LoadAll($requete);
    foreach ($result as $form) {
        $forms[$form['bn_id_nature']] = $form['bn_label_nature'];
    }
    return ['lists' => $lists, 'forms' => $forms];
}

function getHtmlDataAttributes($fiche, $formtab = '')
{
    $htmldata = '';
    if (is_array($fiche) && isset($fiche['id_typeannonce'])) {
        $form = isset($formtab[$fiche['id_typeannonce']]) ? $formtab[$fiche['id_typeannonce']] : baz_valeurs_formulaire($fiche['id_typeannonce']);
        foreach ($fiche as $key => $value) {
            if (!empty($value)) {
                if (in_array(
                    $key,
                    array(
                        'bf_latitude',
                        'bf_longitude',
                        'id_typeannonce',
                        'owner',
                        'date_creation_fiche',
                        'date_debut_validite_fiche',
                        'date_fin_validite_fiche',
                        'id_fiche',
                        'statut_fiche',
                        'date_maj_fiche',
                    )
                )) {
                    $htmldata .=
                    'data-'.htmlspecialchars($key).'="'.
                    htmlspecialchars($value).'" ';
                } else {
                    if (is_array($form['template'])) {
                        foreach ($form['template'] as $id => $val) {
                            if ($val[1] === $key || (isset($val[6]) &&
                              $val[0].$val[1].$val[6] === $key)) {
                                if (in_array(
                                    $form['template'][$id][0],
                                    array(
                                        'checkbox',
                                        'liste',
                                        'checkboxfiche',
                                        'listefiche',
                                        'tags',
                                        'jour',
                                        'scope',
                                        'radio',
                                        //'texte'
                                    )
                                )
                                ) {
                                    $htmldata .=
                                    'data-'.htmlspecialchars($key).'="'.
                                    htmlspecialchars($value).'" ';
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    return $htmldata;
}

/**  show() - Formatte un paragraphe champs d'une fiche seulement si la valeur est renseignée
 * @global string champ de la fiche (au format html)
 * @global string Label du champs (facultatif)
 * @global string classe CSS du paragraphe (facultatif "field" par défaut)
 * @global string balise HTML du paragraphe (facultatif "field" par défaut)
 *
 * @return string HTML
 */
function show($val, $label = '', $class = 'field', $tag = 'p', $fiche = '')
{
    if (is_array($fiche)) {
        // on recupere les valeurs plutot que les clés pour les champs checkbox et liste
        if (substr($val, 0, 10) ===  'listeListe' or substr($val, 0, 13) === 'checkboxListe') {
            $func = (substr($val, 0, 10) ===  'listeListe' ? 'liste' : 'checkbox');
            $dummy = '';
            $form = baz_valeurs_formulaire($fiche['id_typeannonce']);
            $form = multiArraySearch($form, '1', preg_replace('/^(liste|checkbox)/i', '', $val));
            $form = array_shift($form);
            $html = $func($dummy, $form, 'html', $fiche);
            preg_match_all(
                '/<span class="BAZ_texte">\s*(.*)\s*<\/span>/is',
                $html,
                $matches
            );
            if (isset($matches[1][0]) && $matches[1][0] != '') {
                $val = $matches[1][0];
            } else {
                $val = '';
            }
        } else {
            $val = isset($fiche[$val]) ? $fiche[$val] : '';
        }
    }
    if (!empty($val)) {
        echo '<'.$tag;
        if (!empty($class)) {
            echo ' class="'.$class.'"';
        }
        echo '>'."\n";
        if (!empty($label)) {
            echo '<strong>'.$label.'</strong> '."\n";
        }
        echo $val.'</'.$tag.'>'."\n";
    }
}

/** removeAccents() Renvoie une chaine de caracteres avec les accents en moins
 *   @param  string  chaine de caracteres avec de potentiels accents a enlever
 *
 *   return  string chaine de caracteres, sans accents
 */
function removeAccents($str, $charset = YW_CHARSET)
{
    $str = htmlentities($str, ENT_NOQUOTES, $charset);
    $str = preg_replace('#&([A-za-z])(?:acute|cedil|caron|circ|grave|orn|ring|slash|th|tilde|uml);#', '\1', $str);
    $str = preg_replace('#&([A-za-z]{2})(?:lig);#', '\1', $str); // pour les ligatures e.g. '&oelig;'
    $str = preg_replace('#&[^;]+;#', '', $str); // supprime les autres caractères

    return $str;
}

/** genere_nom_wiki()
 *  Prends une chaine de caracteres, et la tranforme en NomWiki unique, en la limitant
 *  a 50 caracteres et en mettant 2 majuscules
 *  Si le NomWiki existe deja, on propose recursivement NomWiki2, NomWiki3, etc..
 *
 *   @param  string  chaine de caracteres avec de potentiels accents a enlever
 *   @param int nombre d'iteration pour la fonction recursive (1 par defaut)
 *
 *
 *   return  string chaine de caracteres, en NomWiki unique
 */
function genere_nom_wiki($nom, $occurence = 1)
{

    // si la fonction est appelee pour la premiere fois, on nettoie le nom passe en parametre
    if ($occurence <= 1) {
        // les noms wiki ne doivent pas depasser les 50 caracteres, on coupe a 48
        // histoire de pouvoir ajouter un chiffre derriere si nom wiki deja existant
        // plus traitement des accents et ponctuation
        // plus on met des majuscules au debut de chaque mot et on fait sauter les espaces
        $temp = removeAccents(mb_substr(preg_replace('/[[:punct:]]/', ' ', $nom), 0, 47, YW_CHARSET));
        $temp = explode(' ', ucwords(strtolower($temp)));
        $nom = '';
        foreach ($temp as $mot) {
            // on vire d'eventuels autres caracteres speciaux
            $nom .= preg_replace('/[^a-zA-Z0-9]/', '', trim($mot));
        }

        // on verifie qu'il y a au moins 2 majuscules, sinon on en rajoute une a la fin
        $var = preg_replace('/[^A-Z]/', '', $nom);
        if (strlen($var) < 2) {
            $last = ucfirst(substr($nom, strlen($nom) - 1));
            $nom = substr($nom, 0, -1).$last;
        }

        $nom = '';
        foreach ($temp as $mot) {
            // on vire d'eventuels autres caracteres speciaux
            $nom .= preg_replace('/[^a-zA-Z0-9]/', '', trim($mot));
        }

        // on verifie qu'il y a au moins 2 majuscules, sinon on en rajoute une a la fin
        $var = preg_replace('/[^A-Z]/', '', $nom);
        if (strlen($var) < 2) {
            $last = ucfirst(substr($nom, strlen($nom) - 1));
            $nom = substr($nom, 0, -1).$last;
        }
    } elseif ($occurence > 2) {
        // si on en est a plus de 2 occurences, on supprime le chiffre precedent et on ajoute la nouvelle occurence
        $nb = -1 * strlen(strval($occurence - 1));
        $nom = substr($nom, 0, $nb).$occurence;
    } else {
        // cas ou l'occurence est la deuxieme : on reprend le NomWiki en y ajoutant le chiffre 2
        $nom = $nom.$occurence;
    }

    if ($occurence == 0) {
        // pour occurence = 0 on ne teste pas l'existance de la page
        return $nom;
    } elseif (!is_array($GLOBALS['wiki']->LoadPage($nom))) {
        // on verifie que la page n'existe pas deja : si c'est le cas on le retourne
        return $nom;
    } else {
        // sinon, on rappele recursivement la fonction jusqu'a ce que le nom aille bien
        ++$occurence;

        return genere_nom_wiki($nom, $occurence);
    }
}

/** baz_rechercher() Formate la liste de toutes les fiches
 *   @return  string    le code HTML a afficher
 */
function baz_rechercher($typeannonce = '', $categorienature = '')
{
    if (!isset($GLOBALS['_BAZAR_']['nbbazarsearch'])) {
        $GLOBALS['_BAZAR_']['nbbazarsearch'] = 0;
    }
    ++$GLOBALS['_BAZAR_']['nbbazarsearch'];

    $res = '';

    // parametres complémentaires de l'url (vont etre passés en GET)
    $data['vue'] = BAZ_VOIR_CONSULTER;
    $data['action'] = BAZ_MOTEUR_RECHERCHE;

    $data['query'] = '';
    $first = true;
    if (is_array($GLOBALS['params']['query']) and count($GLOBALS['params']['query'])>0) {
        foreach ($GLOBALS['params']['query'] as $key => $value) {
            if ($first) {
                $first = false;
            } else {
                $data['query'] .= '|';
            }
            $data['query'] .= $key.'='.$value;
        }
    }


    $data['facette'] = '';
    if (isset($_GET['facette']) && !empty($_GET['facette'])) {
        $data['facette'] = $_GET['facette'];
    }

    // creation du lien pour le formulaire de recherche
    $data['url'] = $GLOBALS['wiki']->href('', $GLOBALS['wiki']->GetPageTag());

    // on recupere la liste des formulaires, a afficher dans une liste deroulante pour la recherche
    $tab_formulaires = baz_valeurs_formulaire($typeannonce, $categorienature);

    // on recupere le nb de types de fiches, pour plus tard
    $nb_type_de_fiches = 0;
    $type_fiche = '';
    $type_formulaire_select[''] = _t('BAZ_TOUS_TYPES_FICHES');
    if (is_array($tab_formulaires) and !isset($tab_formulaires["bn_id_nature"])) {
        foreach ($tab_formulaires as $nomwiki => $ligne) {
            ++$nb_type_de_fiches;
            $tableau_typeformulaires[] = $nomwiki;
            $type_formulaire_select[$nomwiki] = $ligne['bn_label_nature'].
            ((!empty($type_fiche)) ? ' ('.$type_fiche.')' : '');
        }
    } elseif (isset($tab_formulaires["bn_id_nature"])) {
        $nb_type_de_fiches = 1;
        unset($type_formulaire_select);
        $data['forms'] = '';
    }
    if ($nb_type_de_fiches > 1 and !in_array("id_typeannonce", $GLOBALS['params']['groups'])) {
        $data['forms'] = $type_formulaire_select;
    } else {
        $data['forms'] = '';
    }
    if (isset($_GET['id']) && !empty($_GET['id'])) {
        $data['idform'] = $_GET['id'];
    } elseif (is_array($typeannonce) && count($typeannonce) == 1) {
        $data['idform'] = $typeannonce[0];
    } else {
        $data['idform'] = '';
    }

    $data['wiki'] = $_GET['wiki'];

    // y a t'il des mots clés pour le moteur de recherche
    $data['search'] = '';
    if (isset($_REQUEST['q']) && !empty($_REQUEST['q'])) {
        $data['search'] = $_REQUEST['q'];
    }

    // affichage du formulaire
    $res .= '<div id="bazar-search-'.$GLOBALS['_BAZAR_']['nbbazarsearch'].'">';
    $res .= $GLOBALS['wiki']->render("@bazar/search_form.tpl.html", $data);

    $fiches = $GLOBALS['wiki']->services->get(EntryManager::class)->search([
        'queries'=>$GLOBALS['params']['query'],
        'formsIds'=>$data['idform'],
        'keywords'=>$data['search']
    ]);
    $shownbres = count($GLOBALS['params']['groups']) == 0 || count($fiches) == 0;
    $res .= displayResultList($fiches, $GLOBALS['params'], $shownbres).'</div>';
    return $res;
}

/**
 * Mets dans le cache une url .
 *
 * @param $url : url a mettre en cache
 * @param $cache_life : booleen pour afficher ou non le nombre  du resultat de la recherche (vrai par defaut)
 *
 * @return string location of cached file
 */
function cacheUrl($url, $cache_life = '60', $dir = 'cache')
{
    $cache_file = $dir.'/'.removeAccents(preg_replace('/--+/u', '-', preg_replace('/[[:punct:]]/', '-', $url)));

    $filemtime = @filemtime($cache_file);  // returns FALSE if file does not exist
    if (!$filemtime or (time() - $filemtime >= $cache_life)) {
        file_put_contents($cache_file, file_get_contents($url));
    }
    return $cache_file;
}

/**
 * Renvoie le contenu d une url en cache.
 *
 * @param $url : url a mettre en cache
 * @param $cache_life : booleen pour afficher ou non le nombre  du resultat de la recherche (vrai par defaut)
 */
function getCachedUrlContent($url, $cache_life = '60')
{
    $cache_file = cacheUrl($url, $cache_life);
    return file_get_contents($cache_file);
}

/*
 * Filter an array of fields by their potential entry ID
 */
function filterFieldsByPropertyName(array $fields, array $id)
{
    return array_filter($fields, function ($field) use ($id) {
        if ($field instanceof BazarField) {
            return in_array($field->getPropertyName(), $id);
        } elseif (is_array($field) && isset($field['id'])) {
            return in_array($field['id'], $id);
        }
    });
}

/*
 * Scan all forms and return the first field matching the given ID
 */
function findFieldByName($allForms, $name)
{
    foreach ($allForms as $form) {
        foreach ($form['prepared'] as $field) {
            if ($field instanceof BazarField) {
                if ($field->getPropertyName() === $name) {
                    return $field;
                }
            } elseif (is_array($field)) {
                if (isset($field['id']) && $field['id'] === $name) {
                    return $field;
                }
            }
        }
    }
}

function startsWith($haystack, $needle)
{
    $length = strlen($needle);
    return (substr($haystack, 0, $length) === $needle);
}

function endsWith($haystack, $needle)
{
    $length = strlen($needle);
    if ($length == 0) {
        return true;
    }

    return (substr($haystack, -$length) === $needle);
}

/**
 * Génére un tableau avec les informations sur les facettes
 * @param  array $fiches tableau des fiches trouvées
 * @param  array $params tableau des parametres passés a l'action
 * @param  array $formtab tableau des formulaires associés aux fiches si dispo (facultatif)
 * @param  bool  $onlyLists scanne les listes seulement (false par defaut)
 * @return array         tableau des statistiques des données des facettes
 */
function scanAllFacettable($fiches, $params, $formtab = '', $onlyLists = false)
{
    $facettevalue = $fields = [];

    foreach ($fiches as $fiche) {
        // on recupere les valeurs du formulaire si elles n'existaient pas
        $valform = isset($formtab[$fiche['id_typeannonce']]) ? $formtab[$fiche['id_typeannonce']] : baz_valeurs_formulaire($fiche['id_typeannonce']);
        // on filtre pour n'avoir que les liste, checkbox, listefiche ou checkboxfiche
        $fields[$fiche['id_typeannonce']] = isset($fields[$fiche['id_typeannonce']]) ? $fields[$fiche['id_typeannonce']] : filterFieldsByPropertyName(
            $valform['prepared'],
            $params['groups']
        );
        foreach ($fiche as $key => $value) {
            $facetteasked = (isset($params['groups'][0]) && $params['groups'][0] == 'all')
              || in_array($key, $params['groups']);
            if (!empty($value) and is_array($fields[$fiche['id_typeannonce']]) && $facetteasked) {
                $filteredFields = filterFieldsByPropertyName($fields[$fiche['id_typeannonce']], [$key]);
                $field = array_pop($filteredFields);

                $fieldPropName = null;
                if ($field instanceof BazarField) {
                    $fieldPropName = $field->getPropertyName();
                    $fieldType = $field->getType();
                } elseif (is_array($field)) {
                    $fieldPropName = $field['id'];
                    $fieldType = $field['type'];
                }

                if ($fieldPropName) {
                    $islistforeign = (strpos($fieldPropName, 'listefiche')===0) || (strpos($fieldPropName, 'checkboxfiche')===0);
                    $islist = in_array($fieldType, array('checkbox', 'select', 'scope', 'radio', 'liste')) && !$islistforeign;
                    $istext = (!in_array($fieldType, array('checkbox', 'select', 'scope', 'radio', 'liste', 'checkboxfiche', 'listefiche')));

                    if ($islistforeign) {
                        // listefiche ou checkboxfiche
                        $facettevalue[$fieldPropName]['type'] = 'fiche';
                        $facettevalue[$fieldPropName]['source'] = $key;
                        $tabval = explode(',', $value);
                        foreach ($tabval as $tval) {
                            if (isset($facettevalue[$fieldPropName][$tval])) {
                                ++$facettevalue[$fieldPropName][$tval];
                            } else {
                                $facettevalue[$fieldPropName][$tval] = 1;
                            }
                        }
                    } elseif ($islist) {
                        // liste ou checkbox
                        $facettevalue[$fieldPropName]['type'] = 'liste';
                        $facettevalue[$fieldPropName]['source'] = $key;
                        $tabval = explode(',', $value);
                        foreach ($tabval as $tval) {
                            if (isset($facettevalue[$fieldPropName][$tval])) {
                                ++$facettevalue[$fieldPropName][$tval];
                            } else {
                                $facettevalue[$fieldPropName][$tval] = 1;
                            }
                        }
                    } elseif ($istext and !$onlyLists) {
                        // texte
                        $facettevalue[$key]['type'] = 'form';
                        $facettevalue[$key]['source'] = $key;
                        if (isset($facettevalue[$key][$value])) {
                            ++$facettevalue[$key][$value];
                        } else {
                            $facettevalue[$key][$value] = 1;
                        }
                    }
                }
            }
        }
    }
    return $facettevalue;
}

/**
 * Affiche la liste des resultats d'une recherche.
 *
 * @param $tableau_fiches : tableau de fiches provenant du resultat de la recherche
 * @param $info_nb : booleen pour afficher ou non le nombre  du resultat de la recherche (vrai par defaut)
 */
function displayResultList($tableau_fiches, $params, $info_nb = true, $formtab = '')
{
    // on compte le nombre de fois que l'action bazarliste est appelée afin de différencier les instances
    if (!isset($GLOBALS['_BAZAR_']['nbbazarliste'])) {
        $GLOBALS['_BAZAR_']['nbbazarliste'] = 0;
    }
    ++$GLOBALS['_BAZAR_']['nbbazarliste'];
    $params['nbbazarliste'] = $GLOBALS['_BAZAR_']['nbbazarliste'];

    // Add display data to all fiches
    $fiches['fiches'] = array_map(function ($fiche) use ($params) {
        $GLOBALS['wiki']->services->get(EntryManager::class)->appendDisplayData($fiche, false, $params['correspondance']);
        return $fiche;
    }, $tableau_fiches);

    // tri des fiches
    if ($params['random']) {
        shuffle($fiches['fiches']);
    } else {
        $GLOBALS['ordre'] = $params['ordre'];
        $GLOBALS['champ'] = $params['champ'];
        usort($fiches['fiches'], 'champCompare');
    }

    // Limite le nombre de résultat au nombre de fiches demandées
    if ($params['nb'] != '') {
        $fiches['fiches'] = array_slice($fiches['fiches'], 0, $params['nb']);
    }

    // Tableau des valeurs "facettables" avec leur nombres
    $facettevalue = array();
    // On scanne tous les champs qui pourraient faire des filtres pour les facettes
    if (count($params['groups']) > 0) {
        $facettevalue = scanAllFacettable($fiches['fiches'], $params, $formtab);
    }
    if ($info_nb) {
        $fiches['info_res'] = '<div class="alert alert-info">'._t('BAZ_IL_Y_A');

        $nb_result = count($fiches['fiches']);

        if ($nb_result <= 1) {
            $fiches['info_res'] .= $nb_result.' '._t('BAZ_FICHE').'</div>'."\n";
        } else {
            $fiches['info_res'] .= $nb_result.' '._t('BAZ_FICHES').'</div>'."\n";
        }
    } else {
        $fiches['info_res'] = '';
    }
    $fiches['pager_links'] = '';
    if (!empty($params['pagination'])) {
        // Mise en place du Pager
        require_once 'tools/bazar/libs/vendor/Pager/Pager.php';
        $tab = $_GET;
        unset($tab['wiki']);
        // use wiki get param instead of short camelCase param
        // if (isset($tab[$GLOBALS['wiki']->getPageTag()])) {
        //     unset($tab[$GLOBALS['wiki']->getPageTag()]);
        //     unset($_GET[$GLOBALS['wiki']->getPageTag()]);
        // }
        $param = array(
            'mode' => $GLOBALS['wiki']->config['BAZ_MODE_DIVISION'],
            'perPage' => $params['pagination'],
            'delta' => $GLOBALS['wiki']->config['BAZ_DELTA'],
            'httpMethod' => 'GET',
            'path' => $GLOBALS['wiki']->getBaseUrl(),
            'extraVars' => $tab,
            'altNext' => _t('BAZ_SUIVANT'),
            'altPrev' => _t('BAZ_PRECEDENT'),
            'nextImg' => _t('BAZ_SUIVANT'),
            'prevImg' => _t('BAZ_PRECEDENT'),
            'itemData' => $fiches['fiches'],
            'curPageSpanPre' => '<li class="active"><a>',
            'curPageSpanPost' => '</a></li>',
            'useSessions' => false,
            'closeSession' => false,
        );
        $pager = &Pager::factory($param);
        $fiches['fiches'] = $pager->getPageData();
        $fiches['pager_links'] = '<div class="bazar_numero text-center">'."\n".'<ul class="pagination">'."\n".$pager->links.'</ul>'."\n".'</div>'."\n";
    }
    $fiches['param'] = $params;

    // affichage des resultats
    $result = $GLOBALS['wiki']->render("@bazar/{$params['template']}", $fiches);
    $output = '<div id="bazar-list-'.$params['nbbazarliste'].'"
                    class="bazar-list" data-template="' . $params['template'] . '">
                        <div class="list">'.$result.'</div></div>';

    // affichage spécifique pour facette
    if (count($facettevalue) > 0) {
        $i = 0;
        $first = true;
        $facettableValues = [];

        if (is_array($formtab)) {
            // formulaire externe
            $allform = $formtab;
        } else {
            // on charge tous les formulaires
            $allform = baz_valeurs_formulaire();
        }

        // on recupere les facettes cochees
        $tabfacette = array();
        if (isset($_GET['facette']) && !empty($_GET['facette'])) {
            $tab = explode('|', $_GET['facette']);
            //découpe la requete autour des |
            foreach ($tab as $req) {
                $tabdecoup = explode('=', $req, 2);
                if (count($tabdecoup)>1) {
                    $tabfacette[$tabdecoup[0]] = explode(',', trim($tabdecoup[1]));
                }
            }
        }

        foreach ($params['groups'] as $id) {
            // on formatte la liste des resultats en fonction de la source
            if (isset($facettevalue[$id])) {
                if ($facettevalue[$id]['type'] == 'liste') {
                    $field = findFieldByName($allform, $facettevalue[$id]['source']);
                    $list['titre_liste'] = $field->getName();
                    $list['label'] = $field->getOptions();
                } elseif ($facettevalue[$id]['type'] == 'fiche') {
                    $src = str_replace(array('listefiche', 'checkboxfiche'), '', $facettevalue[$id]['source']);
                    $form = $allform[$src];
                    $list['titre_liste'] = $form['bn_label_nature'];
                    foreach ($facettevalue[$id] as $idfiche => $nb) {
                        if ($idfiche != 'source' && $idfiche != 'type') {
                            $f = $GLOBALS['wiki']->services->get(EntryManager::class)->getOne($idfiche);
                            $list['label'][$idfiche] = $f['bf_titre'];
                        }
                    }
                } elseif ($facettevalue[$id]['type'] == 'form') {
                    if ($facettevalue[$id]['source'] == 'id_typeannonce') {
                        $list['titre_liste'] = _t('BAZ_TYPE_FICHE');
                        foreach ($facettevalue[$id] as $idf => $nb) {
                            if ($idf != 'source' && $idf != 'type') {
                                $list['label'][$idf] = $allform[$idf]['bn_label_nature'];
                            }
                        }
                    } elseif ($facettevalue[$id]['source'] == 'owner') {
                        $list['titre_liste'] = _t('BAZ_CREATOR');
                        foreach ($facettevalue[$id] as $idf => $nb) {
                            if ($idf != 'source' && $idf != 'type') {
                                $list['label'][$idf] = $idf;
                            }
                        }
                    } else {
                        $list['titre_liste'] = $id;
                        foreach ($facettevalue[$id] as $idf => $nb) {
                            if ($idf != 'source' && $idf != 'type') {
                                $list['label'][$idf] = $idf;
                            }
                        }
                    }
                }
            }

            $idkey = htmlspecialchars($id);

            $facettableValues[$idkey]['icon'] =
              (isset($params['groupicons'][$i]) && !empty($params['groupicons'][$i])) ?
                '<i class="'.$params['groupicons'][$i].'"></i> ' : '';

            $facettableValues[$idkey]['title'] =
              (isset($params['titles'][$i]) && !empty($params['titles'][$i])) ?
                $params['titles'][$i] : $list['titre_liste'];

            $facettableValues[$idkey]['collapsed'] = !$first && !$params['groupsexpanded'];

            foreach ($list['label'] as $listkey => $label) {
                if (isset($facettevalue[$id][$listkey]) && !empty($facettevalue[$id][$listkey])) {
                    $facettableValues[$idkey]['list'][] = array(
                        'id' => $idkey.$listkey,
                        'name' => $idkey,
                        'value' => htmlspecialchars($listkey),
                        'label' => $label,
                        'nb' => $facettevalue[$id][$listkey],
                        'checked' => (isset($tabfacette[$idkey]) and in_array($listkey, $tabfacette[$idkey])) ? ' checked' : '',
                    );
                }
            }
            ++$i;
            $first = false;
        }
        $output = $GLOBALS['wiki']->render("@bazar/{$params['facettetemplate']}", [
            'content' => $output,
            'filters' => $facettableValues,
            'nbfiches' => count($fiches['fiches']),
            'params' => $params
        ]);
    }
    // affiche les possibilités d'export
    if (!preg_match('/\/bazariframe/U', $_GET['wiki']) and $params['showexportbuttons']) {
        $key = '';
        if (isset($_GET['id']) and !empty($_GET['id'])) {
            $key = $_GET['id'];
        } elseif (is_array($GLOBALS['params']['idtypeannonce'])) {
            $key = implode(',', $GLOBALS['params']['idtypeannonce']);
        } elseif (!empty($GLOBALS['params']['idtypeannonce'])) {
            $key = $GLOBALS['params']['idtypeannonce'];
        } else {
            $keys =  array_keys($GLOBALS['_BAZAR_']['form']);
            $key = is_array($keys) ? implode(',', $keys) : $params['idtypeannonce'];
        }

        if (isset($_GET['q']) and !empty($_GET['q'])) {
            $key .= '&q='.$_GET['q'];
        }
        if (!empty($params['query'])) {
            $key .= '&query=';
            $first = true;
            $queryurl = '';
            foreach ($params['query'] as $id => $val) {
                if ($first) {
                    $first = false;
                } else {
                    $queryurl .= '|';
                }
                $queryurl .= $id.'='.$val;
            };
            $key.= $queryurl;
            // on sauve la valeur de query initiale pour des traitement javascripts
            $output .= '<input type="hidden" id="queryinit" value="'.htmlspecialchars($queryurl).'">'."\n";
        }
        if (!empty($key)) {
            $output .= '<div class="export-links pull-right"><a class="btn btn-default btn-mini btn-xs"
            data-toggle="tooltip" data-placement="bottom" title="'._t('BAZ_RSS').'"
            href="'.$GLOBALS['wiki']->href('rss', $GLOBALS['wiki']->getPageTag(), 'id='.$key).'">
            <i class="fa fa-signal icon-signal"></i></a>
            <a class="btn btn-default btn-mini btn-xs"
            data-toggle="tooltip" data-placement="bottom" title="'._t('BAZ_CSV').'"
            href="'.$GLOBALS['wiki']->href('', $GLOBALS['wiki']->getPageTag(), 'vue=exporter&id='.$key).'">
            CSV</a>
            <a class="btn btn-default btn-mini btn-xs"
            data-toggle="tooltip" data-placement="bottom" title="'._t('BAZ_JSON').'"
            href="'.$GLOBALS['wiki']->href('json', $GLOBALS['wiki']->getPageTag(), 'demand=entries&id='.$key).'">
            JSON</a>
            <a class="btn btn-default btn-mini btn-xs"
            data-toggle="tooltip" data-placement="bottom" title="'._t('BAZ_WIDGET').'"
            href="'.$GLOBALS['wiki']->href('widget', $GLOBALS['wiki']->getPageTag(), 'id='.$key).'">
            '._t('BAZ_WIDGET').'</a></div>';
        }
    }
    return $output;
}

// pour verifier la presence d une valeur dans une fiche, en vue de lui faire une icone ou couleur personnalisee
function getCustomValueForEntry($parameter, $field, $entry, $default)
{
    if (is_array($parameter) && !empty($field)) {
        if (isset($entry[$field])) {
            // pour les checkbox, on teste les differentes valeurs et on renvoie la premiere qui va bien
            if (0 === strpos($field, 'checkbox')) {
                $tab = explode(',', $entry[$field]);
                foreach ($tab as $value) {
                    if (isset($parameter[$value])) {
                        // on retourne la premiere valeur trouvee
                        return $parameter[$value];
                    }
                }
                // on n a pas trouve de valeur, on renvoie la valeur par defaut
                return $default;
            } else {
                return isset($parameter[$entry[$field]]) ?
                    $parameter[$entry[$field]] : $default;
            }
        } else {
            // si la valeur n existe pas, on met l icone par defaut
            return $default;
        }
    } else {
        // si le parametre n'est pas un tableau, il contient la valeur par defaut
        return $default;
    }
}

// tri par ordre desire
function champCompare($a, $b)
{
    if ($GLOBALS['ordre'] == 'desc') {
        return strcoll(mb_strtolower($b[$GLOBALS['champ']]), mb_strtolower($a[$GLOBALS['champ']]));
    } else {
        return strcoll(mb_strtolower($a[$GLOBALS['champ']]), mb_strtolower($b[$GLOBALS['champ']]));
    }
}

// choix de la periode
function getDateMin($period)
{
    switch ($period) {
        case 'day':
            $d = strtotime("-1 day");
            return date("Y-m-d H:i:s", $d);
            break;
        case 'week':
            $d = strtotime("-1 week");
            return date("Y-m-d H:i:s", $d);
            break;
        case 'month':
            $d = strtotime("-1 month");
            return date("Y-m-d H:i:s", $d);
            break;
    }
}

/**
 * Construit un boolean à partir des valeurs "0","no","non","false" pour false,
 * sinon retourne true.
 * @param string $parameterName
 * @param boolean $default
 * @return boolean
 */
function getParameter_boolean($wiki, $parameterName, $default = true)
{
    $p = $wiki->GetParameter($parameterName);
    if (empty($p)) {
        $p = $default ;
    } elseif ($p == '0'
        || $p == 'no'
        || $p == 'non'
        || $p == 'false'
    ) {
        $p = false ;
    } else {
        $p = true ;
    }
    return $p ;
}

/** getAllParameters() - récupère tous les parametres possible pour une action de bazar
 * @return array tableau des parametres avec les valeurs par défaut
 */
function getAllParameters($wiki)
{
    $param = array();

    $param['action'] = $wiki->GetParameter(BAZ_VARIABLE_ACTION);
    if (isset($_GET[BAZ_VARIABLE_ACTION])) {
        $param['action'] = $_GET[BAZ_VARIABLE_ACTION];
    }

    $param['vue'] = $wiki->GetParameter(BAZ_VARIABLE_VOIR);
    if (isset($_GET[BAZ_VARIABLE_VOIR])) {
        $param['vue'] = $_GET[BAZ_VARIABLE_VOIR];
    }
    if (empty($param['vue'])) {
        // si rien n'est donne, on met la vue par defaut
        $param['vue'] = BAZ_VOIR_DEFAUT;
    }

    // afficher le menu de vues bazar ?
    $param['voirmenu'] = $wiki->GetParameter('voirmenu');
    if (empty($param['voirmenu']) && $param['voirmenu'] != '0') {
        $param['voirmenu'] = $GLOBALS['wiki']->config['baz_menu'];
    }

    // autoriser qu'une catégorie de formulaire
    $param['categorienature'] = $wiki->GetParameter('cat');
    if ($param['categorienature'] == 'toutes') {
        $param['categorienature'] = '';
    }
    // retrocompatibilite avec le parametre categorienature
    if (empty($param['categorienature'])) {
        $param['categorienature'] = $wiki->GetParameter('categorienature');
        if ($param['categorienature'] == 'toutes') {
            $param['categorienature'] = '';
        }
    }

    // identifiant du formulaire (plusieures valeurs possibles, séparées par des virgules)
    $param['idtypeannonce'] = $wiki->GetParameter('id');
    if (empty($param['idtypeannonce'])) {
        $param['idtypeannonce'] = isset($_GET['id']) ? $_GET['id'] : '';
    } else {
        $param['idtypeannonce'] = explode(',', $param['idtypeannonce']);
        $param['idtypeannonce'] = array_map('trim', $param['idtypeannonce']);
    }
    // retrocompatibilite avec le parametre idtypeannonce
    if (!is_array($param['idtypeannonce']) and empty($param['idtypeannonce'])) {
        // identifiant du formulaire (plusieures valuers possibles, séparées par des virgules)
        $param['idtypeannonce'] = $wiki->GetParameter('idtypeannonce');
        if (empty($param['idtypeannonce'])) {
            $param['idtypeannonce'] = '';
        } else {
            $param['idtypeannonce'] = explode(',', $param['idtypeannonce']);
            $param['idtypeannonce'] = array_map('trim', $param['idtypeannonce']);
        }
    }

    //on recupere les parameres pour une requete specifique
    if (isset($_GET['query'])) {
        $param['query'] = $wiki->GetParameter('query');
        if (!empty($param['query'])) {
            $param['query'] .= '|'.$_GET['query'];
        } else {
            $param['query'] = $_GET['query'];
        }
    } else {
        $param['query'] = $wiki->GetParameter('query');
    }
    if (!empty($param['query'])) {
        $tabquery = array();

        $tab = explode('|', $param['query']); //découpe la requete autour des |
        foreach ($tab as $req) {
            $tabdecoup = explode('=', $req, 2);
            if (isset($tabquery[$tabdecoup[0]]) && !empty($tabquery[$tabdecoup[0]])) {
                $tabquery[$tabdecoup[0]] = $tabquery[$tabdecoup[0]].','.trim($tabdecoup[1]);
            } else {
                $tabquery[$tabdecoup[0]] = trim($tabdecoup[1]);
            }
        }
    } else {
        $tabquery = '';
    }
    $param['query'] = $tabquery;

    // ordre du tri (asc ou desc)
    $param['ordre'] = $wiki->GetParameter('ordre');
    if (empty($param['ordre'])) {
        $param['ordre'] = 'asc';
    }

    // champ du formulaire utilisé pour le tri
    $param['champ'] = $wiki->GetParameter('champ');
    if (empty($param['champ'])) {
        $param['champ'] = 'bf_titre'; // si pas de champ précisé, on triera par le titre
    }

    // template utilisé pour l'affichage
    $param['template'] = isset($_GET['template']) ? $_GET['template'] : $wiki->GetParameter('template');
    if (empty($param['template'])) {
        $param['template'] = $GLOBALS['wiki']->config['default_bazar_template'];
    }
    if (strpos($param['template'], '.html') === false) {
        $param['template'] = $param['template'] . '.tpl.html';
    }

    // nombre maximal de résultats à afficher
    $param['nb'] = $wiki->GetParameter('nb');

    // classe css a ajouter en rendu des templates
    $param['class'] = $wiki->GetParameter('class');

    // ajout des options pour gerer la fiche (modifier, droits, etc,.. )
    $param['barregestion'] = getParameter_boolean($wiki, 'barregestion', true);

    // ajout des bouton pour gerer la fiche (modifier, droits, etc,.. )
    $param['showexportbuttons'] = getParameter_boolean($wiki, 'showexportbuttons', false);

    // possibilité d'avoir un ordre aléatoire des fiches
    $param['random'] = getParameter_boolean($wiki, 'random', false);

    // filtrer les resultats sur une periode données
    // si une date est indiquée
    $param['period'] = $wiki->GetParameter('period');
    if (isset($_GET['period']) && in_array($_GET['period'], array('day', 'week', 'month'))) {
        $param['datemin'] = getDateMin($_GET['period']);
    } elseif (!empty($param['period'])) {
        $param['datemin'] = getDateMin($param['period']);
    } else {
        $param['datemin'] = '';
    }

    // facette : identifiants servant de filtres
    //    plusieures valeurs possibles, séparées par des virgules,
    //    "all" pour toutes les facette possibles)
    //    exemple : {{bazarliste groups="bf_ce_titre,bf_ce_pays,etc."..}}
    $param['groups'] = isset($_GET['groups']) ? $_GET['groups'] : $wiki->GetParameter('groups');
    if (empty($param['groups'])) {
        $param['groups'] = array();
    } else {
        $param['groups'] = explode(',', $param['groups']);
        $param['groups'] = array_map('trim', $param['groups']);
    }

    // facette: titres des boite de filtres correspondants au parametre groups
    //    plusieures valeurs possibles, séparées par des virgules, le meme nombre que "groups"
    //    exemple : {{bazarliste titles="Titre,Pays,etc."..}}
    $param['titles'] = isset($_GET['titles']) ? $_GET['titles'] : $wiki->GetParameter('titles');
    if (empty($param['titles'])) {
        $param['titles'] = array();
    } else {
        $param['titles'] = explode(',', $param['titles']);
        $param['titles'] = array_map('trim', $param['titles']);
    }

    // facette: titres des boite de filtres correspondants au parametre groups
    //    plusieures valeurs possibles, séparées par des virgules, le meme nombre que "groups"
    //    exemple : {{bazarliste titles="Titre,Pays,etc."..}}
    $param['groupicons'] = $wiki->GetParameter('groupicons');
    if (empty($param['groupicons'])) {
        $param['groupicons'] = array();
    } else {
        $param['groupicons'] = explode(',', $param['groupicons']);
        $param['groupicons'] = array_map('trim', $param['groupicons']);
    }

    // nombre de résultats affichées avant pagination
    $param['pagination'] = $wiki->GetParameter('pagination');

    // ajout d'un filtre pour chercher du txte dans les resultats pour les facette
    $param['filtertext'] = getParameter_boolean($wiki, 'filtertext', false);

    // correspondance transfere les valeurs d'un champs vers un autre, afin de correspondre dans un template
    $param['correspondance'] = $wiki->GetParameter('correspondance');

    /*
     * Facette : filtres à gauche ou droite (droite par défaut)
     */
    $param['filterposition'] = isset($_GET['filterposition']) ? $_GET['filterposition'] : $wiki->GetParameter('filterposition');
    if (empty($param['filterposition']) || (!empty($param['filterposition'])
      && $param['filterposition'] != 'left')) {
        $param['filterposition'] = 'right';
    }

    /*
     * Facette : largeur colonne
     */
    $param['filtercolsize'] = isset($_GET['filtercolsize']) ? $_GET['filtercolsize'] : $wiki->GetParameter('filtercolsize');
    if (empty($param['filtercolsize'])
      || (!empty($param['filtercolsize'])
        && (!(ctype_digit($param['filtercolsize'])
          && intval($param['filtercolsize']) >= 1 && intval($param['filtercolsize']) <= 12)))) {
        $param['filtercolsize'] = '3';
    }

    /*
     * Facette: déplier tous les groupes (panels à droite)
     */
    $param['groupsexpanded'] = isset($_GET['groupsexpanded']) ? $_GET['groupsexpanded'] : $wiki->GetParameter('groupsexpanded');
    if (empty($param['groupsexpanded'])) {
        $param['groupsexpanded'] = 'true';
    }
    $param['groupsexpanded'] = $param['groupsexpanded'] == "true"; // convert to boolean

    /*
     * Facette: template pour les facettes
     */
    $param['facettetemplate'] = isset($_GET['facettetemplate']) ? $_GET['facettetemplate'] : $wiki->GetParameter('facettetemplate');
    if (empty($param['facettetemplate'])) {
        $param['facettetemplate'] = 'facette-default.tpl.html';
    }

    /*
     * Agenda : calendrier plus petit
     */
    $param['minical'] = $wiki->GetParameter('minical');

    /*
     * Permettre de rediriger vers une url apres saisie de fiche
     */
    $param['redirecturl'] = $wiki->GetParameter('redirecturl');

    // Parametres pour Bazarliste avec carto
    getAllParameters_carto($wiki, $param);

    return $param;
}

/**
 * Juste pour alléger la fonction getAllParameters(), regroupe les paramètres pour la cartographie.
 *
 * @param unknown $wiki
 * @param array $param
 */
function getAllParameters_carto($wiki, array &$param)
{

    /*
     * provider : designe le fond de carte utilisé pour la carte
     * cf. https://github.com/leaflet-extras/leaflet-providers
     */
    $param['provider'] = isset($_GET['provider']) ? $_GET['provider'] : $wiki->GetParameter('provider');
    if (empty($param['provider'])) {
        $param['provider'] = $GLOBALS['wiki']->config['baz_provider'];
    }
    // on recupere d eventuels id et token pour les providers en ayant besoin
    $param['providerid'] = $wiki->GetParameter('providerid');
    $param['providerpass'] = $wiki->GetParameter('providerpass');
    if (!empty($param['providerid']) && !empty($param['providerpass'])) {
        if ($param['provider'] == 'MapBox') {
            $param['provider_credentials'] = ', {id: \''.$param['providerid']
            .'\', accessToken: \''.$param['providerpass'].'\'}';
        } else {
            $param['provider_credentials'] = ', {
                app_id: \''.$param['providerid'].'\',
                app_code: \''.$param['providerpass'].'\'
            }';
        }
    } else {
        $param['provider_credentials'] = '';
    }

    /*
     * "providers" : une liste de fonds de carte.
     *
     * Exemple:
     * provider="OpenStreetMap.France" providers="OpenStreetMap.Mapnik,OpenStreetMap.France"
     *
     * TODO: ajouter gestion "providers_credentials"
     */
    $param['providers'] = $wiki->GetParameter('providers');
    if (!empty($param['providers'])) {
        $param['providers'] = explode(',', $param['providers']);
    }

    /*
     * "layers" : une liste de layers (couches).
     * Exemple avec 1 layer tiles, 1 layer geojson:
     * layers="BD Carthage|Tiles|//a.tile.openstreetmap.fr/route500hydro/{z}/{x}/{y}.png,CUCS 2014|GeoJson|wakka.php?wiki=geojsonCUCS2014/raw"
     * layers="BD Carthage|Tiles|//a.tile.openstreetmap.fr/route500hydro/{z}/{x}/{y}.png,CUCS 2014|GeoJson|color:'red';opacity:0.3|wakka.php?wiki=geojsonCUCS2014/raw"
     *
     * format pour chaque layer : NOM|TYPE|URL ou NOM|TYPE|OPTIONS|URL
     * - OPTIONS: facultatif ex: "color:red; opacity:0.3"
     * nota bene: le séparateur d'options est le ';' et pas la ',' qui est déjà utilisée pour séparer les LAYERS.
     * - TYPE: Tiles ou GeoJson
     * - URL: Attention au Blocage d'une requête multi-origines (Cross-Origin Request).
     *  Le plus simple est de recopier les data GeoJson dans une page du Wiki puis de l'appeler avec le handler "/raw".
     *
     * TODO: ajouter gestion "layers_credentials"
     */
    $param['layers'] = $wiki->GetParameter('layers');
    if (!empty($param['layers'])) {
        $param['layers'] = explode(',', $param['layers']);
    }

    /*
     * iconprefix : designe le prefixe des classes CSS utilisees pour la carto
     */
    $param['iconprefix'] = isset($_GET['iconprefix']) ? $_GET['iconprefix'] : $wiki->GetParameter('iconprefix');
    if (empty($param['iconprefix'])) {
        if (!empty($GLOBALS['wiki']->config['baz_marker_icon_prefix'])) {
            $param['iconprefix'] = $GLOBALS['wiki']->config['baz_marker_icon_prefix'];
        } else {
            $param['iconprefix'] = '';
        }
    } else {
        $param['iconprefix'] = trim($param['iconprefix']);
    }

    /*
     * iconfield : designe le champ utilise pour les icones des marqueurs
     */
    $param['iconfield'] = isset($_GET['iconfield']) ? $_GET['iconfield'] : $wiki->GetParameter('iconfield');

    /*
     * icon : icone des marqueurs
     */
    $param['icon'] = isset($_GET['icon']) ? $_GET['icon'] : $wiki->GetParameter('icon');
    if (!empty($param['icon'])) {
        $tabparam = array();
        $tabparam = getMultipleParameters($param['icon'], ',', '=');
        if ($tabparam['fail'] != 1) {
            if (count($tabparam) > 1 && !empty($param['iconfield'])) {
                foreach ($tabparam as $key=>$data) {
                    // on inverse cle et valeur, pour pouvoir les reprendre facilement dans la carto
                    $tabparam[$data] = $key;
                }
                $param['icon'] = $tabparam;
            } else {
                $param['icon'] = trim($tabparam[0]);
            }
        } else {
            exit('<div class="alert alert-danger">action bazarliste : le paramètre icon est mal rempli.<br />Il doit être de la forme icon="nomIcone1=valeur1, nomIcone2=valeur2"</div>');
        }
    } else {
        $param['icon'] = $GLOBALS['wiki']->config['baz_marker_icon'];
    }
    /*
     * colorfield : designe le champ utilise pour la couleur des marqueurs
     */
    $param['colorfield'] = isset($_GET['colorfield']) ? $_GET['colorfield'] : $wiki->GetParameter('colorfield');

    /*
    * color : couleur des marqueurs
    */
    $colors = array(
        'red', 'darkred', 'lightred', 'orange', 'beige', 'green', 'darkgreen', 'lightgreen', 'blue', 'darkblue',
        'lightblue', 'purple', 'darkpurple', 'pink', 'cadetblue', 'white', 'gray', 'lightgray', 'black',
    );
    $param['color'] = isset($_GET['color']) ? $_GET['color'] : $wiki->GetParameter('color');
    if (!empty($param['color'])) {
        $tabparam = array();
        $tabparam = getMultipleParameters($param['color'], ',', '=');
        if ($tabparam['fail'] != 1) {
            if (count($tabparam) > 1 && !empty($param['colorfield'])) {
                foreach ($tabparam as $key=>$data) {
                    // on inverse cle et valeur, pour pouvoir les reprendre facilement dans la carto
                    $tabparam[$data] = $key;
                }
                $param['color'] = $tabparam;
            } else {
                $param['color'] = trim($colors[0]);
                if (!in_array($param['color'], $colors)) {
                    $param['color'] = $GLOBALS['wiki']->config['baz_marker_color'];
                }
            }
        } else {
            exit('<div class="alert alert-danger">action bazarliste : le paramètre color est mal rempli.<br />Il doit être de la forme color="couleur1=valeur1, couleur2=valeur2"</div>');
        }
    } else {
        $param['color'] = $GLOBALS['wiki']->config['baz_marker_color'];
    }

    /*
     * smallmarker : mettre des puces petites ? non par defaut
     */
    $param['smallmarker'] = isset($_GET['smallmarker']) ? $_GET['smallmarker'] : $wiki->GetParameter('smallmarker');
    if (empty($param['smallmarker'])) {
        $param['markersize'] = isset($_GET['markersize']) ? $_GET['markersize'] : $wiki->GetParameter('markersize');
        if (!empty($param['markersize']) and $param['markersize'] == 'small') {
            $param['smallmarker'] = '1';
        } else {
            $param['smallmarker'] = $GLOBALS['wiki']->config['baz_small_marker'];
        }
    }
    if (!empty($param['smallmarker']) && $param['smallmarker'] == '1') {
        $param['smallmarker'] = '';
        $param['iconSize'] = '[15, 20]';
        $param['iconAnchor'] = '[8, 19]';
        $param['popupAnchor'] = '[0, -19]';
    } else {
        $param['smallmarker'] = ' xl';
        $param['iconSize'] = '[35, 46]';
        $param['iconAnchor'] = '[18, 45]';
        $param['popupAnchor'] = '[0, -45]';
    }

    /*
     * width : largeur de la carte à l'écran en pixels ou pourcentage
     */
    $param['width'] = isset($_GET['width']) ? $_GET['width'] : $wiki->GetParameter('width');
    if (empty($param['width'])) {
        $param['width'] = $GLOBALS['wiki']->config['baz_map_width'];
    }

    /*
     * height : hauteur de la carte à l'écran en pixels ou pourcentage
     */
    $param['height'] = isset($_GET['height']) ? $_GET['height'] : $wiki->GetParameter('height');
    if (empty($param['height'])) {
        $param['height'] = $GLOBALS['wiki']->config['baz_map_height'];
    }

    /*
     * lat : latitude point central en degres WGS84 (exemple : 46.22763) , sinon parametre par defaut
     */
    $param['latitude'] = isset($_GET['lat']) ? $_GET['lat'] : $wiki->GetParameter('lat');
    if (empty($param['latitude'])) {
        $param['latitude'] = $GLOBALS['wiki']->config['baz_map_center_lat'];
    }

    /*
     * lon : longitude point central en degres WGS84 (exemple : 3.42313) , sinon parametre par defaut
     */
    $param['longitude'] = isset($_GET['lon']) ? $_GET['lon'] : $wiki->GetParameter('lon');
    if (empty($param['longitude'])) {
        $param['longitude'] = $GLOBALS['wiki']->config['baz_map_center_lon'];
    }

    /*
     * niveau de zoom : de 1 (plus eloigne) a 15 (plus proche) , sinon parametre par defaut 5
     */
    $param['zoom'] = isset($_GET['zoom']) ? $_GET['zoom'] : $wiki->GetParameter('zoom');
    if (empty($param['zoom'])) {
        $param['zoom'] = $GLOBALS['wiki']->config['baz_map_zoom'];
    }

    /*
     * Outil de navigation , sinon parametre par defaut true
     */
    $param['navigation'] = isset($_GET['navigation']) ? $_GET['navigation'] : $wiki->GetParameter('navigation');
    if (empty($param['navigation'])) {
        $param['navigation'] = $GLOBALS['wiki']->config['baz_show_nav'];
    }

    /*
     * Zoom sur molette : true or false (defaut)
     */
    $param['zoom_molette'] = $wiki->GetParameter('zoommolette');
    if (empty($param['zoom_molette'])) {
        $param['zoom_molette'] = $GLOBALS['wiki']->config['baz_wheel_zoom'];
    }

    /*
     * Affichage en eclate des points superposes : true or false (defaut)
     */
    $param['spider'] = $wiki->GetParameter('spider'); // true or false
    if (empty($param['spider'])) {
        $param['spider'] = 'false';
    }

    /*
     * Affichage en cluster : true or false, par defaut false
     */
    $param['cluster'] = $wiki->GetParameter('cluster'); // true or false
    if (empty($param['cluster'])) {
        $param['cluster'] = 'false';
    }

    /*
     * Ajout bouton plein écran
     * fullscreen: true or false
     * https://github.com/brunob/leaflet.fullscreen
     */
    $param['fullscreen'] = $wiki->GetParameter('fullscreen');
    if (empty($param['fullscreen'])) {
        $param['fullscreen'] = 'true';
    }

    /*
    * Provide a json configuration with URL
    */
    $param['jsonconfurl'] = $wiki->GetParameter('jsonconfurl');
}

function getMultipleParameters($param, $firstseparator = ',', $secondseparator = '=')
{
    // This function's aim is to fetch (key , value) couples stored in a multiple parameter
    // $param is the parameter where we have to fecth the couples
    // $firstseparator is the separator between the couples (usually ',')
    // $secondseparator is the separator between key and value in each couple (usually '=')
    // Returns the table of (key , value) couples
    // If fails to explode the data, then $tabparam['fail'] == 1
    $tabparam = array();
    $tabparam['fail'] = 0;
    // check if first and second separators are at least somewhere
    if (strpos($param, $secondseparator) !== false) {
        $params = explode($firstseparator, $param);
        $params = array_map('trim', $params);
        if (count($params) > 0) {
            foreach ($params as $value) {
                if (!empty($value)) {
                    $tab = explode($secondseparator, $value);
                    $tab = array_map('trim', $tab);
                    if (count($tab) > 1) {
                        $tabparam[$tab[0]] = $tab[1];
                    } else {
                        $tabparam['fail'] = 1;
                    }
                }
            }
        } else {
            $tabparam['fail'] = 1;
        }
    } else {
        $tabparam['fail'] = 1;
    }
    return $tabparam;
}
