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

use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Bazar\Service\FormManager;

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
            $val_formulaire = $GLOBALS['wiki']->services->get(FormManager::class)->getOne($id);

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
            $resultat = $GLOBALS['wiki']->services->get(FormManager::class)->getAll();

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
                $val_formulaire = $GLOBALS['wiki']->services->get(FormManager::class)->getOne($id);
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
        $resultat = $GLOBALS['wiki']->services->get(FormManager::class)->getAll();

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

        $val_formulaire = $GLOBALS['wiki']->services->get(FormManager::class)->getOne($id);

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
        $form = isset($formtab[$fiche['id_typeannonce']]) ? $formtab[$fiche['id_typeannonce']] : $GLOBALS['wiki']->services->get(FormManager::class)->getOne($fiche['id_typeannonce']);
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
            $form = $GLOBALS['wiki']->services->get(FormManager::class)->getOne($fiche['id_typeannonce']);
            $f = multiArraySearch($form, '1', preg_replace('/^(liste|checkbox)/i', '', $val));
            $f = array_shift($f);
            if (function_exists($func)) {
                $html = $func($dummy, $f, 'html', $fiche);
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
                $found = '';
                foreach ($form['prepared'] as $field) {
                    if ($field->getPropertyName() == $val) {
                        $found = $field->renderStaticIfPermitted($fiche);
                    }
                }
                $val = $found;
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

// pour verifier la presence d une valeur dans une fiche, en vue de lui faire une icone ou couleur personnalisee
function getCustomValueForEntry($parameter, $field, $entry, $default)
{
    if (is_array($parameter) && !empty($field)) {
        if (isset($entry[$field])) {
            // pour les checkbox, on teste les differentes valeurs et on renvoie la premiere qui va bien
            if (!isset($parameter[$entry[$field]]) && strpos($entry[$field], ',') !== false) {
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
