<?php

/*
json.php

Copyright 2010  Florian Schmitt <florian@outils-reseaux.org>
This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

// Vérification de sécurité
if (!defined("WIKINI_VERSION")) {
    die("acc&egrave;s direct interdit");
}

if (isset($_REQUEST['demand'])) {
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && empty($_POST)) {
        $_POST = json_decode(file_get_contents('php://input'), true);
    }

    // preparation des parametres passés
    $page = (isset($_REQUEST['page']) ? $_REQUEST['page'] : '');
    $form = (isset($_REQUEST['id']) ? $_REQUEST['id'] : (isset($_REQUEST['form']) ? $_REQUEST['form'] : ''));
    $tags = (isset($_REQUEST['tags']) ? $_REQUEST['tags'] : '');
    $order = (isset($_REQUEST['order']) ? $_REQUEST['order'] : 'alphabetique');
    $html = (isset($_REQUEST['html']) && $_REQUEST['html'] == '1' ? $_REQUEST['html'] : '');
    $list = (isset($_REQUEST['list']) ? $_REQUEST['list'] : '');
    $idfiche = (isset($_REQUEST['id_fiche']) ? $_REQUEST['id_fiche'] : '');
    $pagetag = (isset($_REQUEST['pagetag']) ? $_REQUEST['pagetag'] : '');
    $is_semantic = (isset($_REQUEST['ld']) ? $_REQUEST['ld'] : '');
    //on recupere les parametres query pour une requete specifique
    $query = (isset($_REQUEST['query']) ? $_REQUEST['query'] : '');
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

    header('Content-type: ' . ( $is_semantic ? 'application/ld+json' : 'application/json' ) . '; charset=UTF-8');
    header('Access-Control-Allow-Origin: *');

    if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
        if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD']) && (
                $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'] == 'POST' ||
                $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'] == 'DELETE' ||
                $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'] == 'PUT'
            )) {
            header('Access-Control-Allow-Credentials: true');
            header('Access-Control-Allow-Headers: X-Requested-With');
            header('Access-Control-Allow-Headers: Content-Type');
            header('Access-Control-Allow-Methods: POST, GET, OPTIONS, DELETE, PUT');
            header('Access-Control-Max-Age: 86400');
        }
        exit;
    }

    // différents services disponibles
    switch ($_REQUEST['demand']) {
        case "lists":
        // listes bazar
            echo json_encode(baz_valeurs_liste($list));
            break;
        case "entry":
        // données d'une fiche bazar
            if (empty($idfiche)) {
                echo json_encode(array('error' => 'no id_fiche specified.'));
            } else {
                $wikipage = $this->LoadPage($idfiche);
                if ($wikipage) {
                    if ($html==1) {
                        echo json_encode(array('html' => baz_voir_fiche(0, $idfiche)));
                    } else {
                        $decoded_entry = json_decode($wikipage['body'], true);
                        echo json_encode($decoded_entry);
                    }
                } else {
                    echo json_encode(array('error' => 'id_fiche '.$idfiche.' not found.'));
                }
            }
            break;
        case "save_entry":
        // sauver une fiche bazar
            if (!isset($form) || empty($form)) {
                echo json_encode(array('error' => _t('BAZ_NO_FORMS_FOUND')));
            } else {
                $_POST['id_typeannonce'] = $form;
                $formdata = baz_valeurs_formulaire($form);
                $res = validateForm($_POST);
                if ($res['result']) {
                    $fiche = baz_insertion_fiche($_POST);
                    echo json_encode($fiche);
                } else {
                    echo json_encode(
                        array(
                            'error' => $res['error'],
                            'post' => $_POST
                        )
                    );
                }
            }
            break;
        case "template":
            // les templates bazar, pour afficher dans d'autres applis
            // on peut préciser dans l'url type=form (template formulaire) ou type=entry (template fiche)
            if (empty($form)) {
                echo json_encode(array('error' => 'no form id specified.'));
            } else {
                $_REQUEST['id_typeannonce'] = $form;
                $tab_nature = baz_valeurs_formulaire($_REQUEST['id_typeannonce']);
                $GLOBALS['_BAZAR_']['typeannonce'] = $tab_nature['bn_label_nature'];
                $GLOBALS['_BAZAR_']['condition'] = $tab_nature['bn_condition'];
                $GLOBALS['_BAZAR_']['template'] = $tab_nature['bn_template'];
                $type = (isset($_REQUEST['type']) ? $_REQUEST['type'] : 'form');
                if ($type == 'entry') { // template d'une fiche bazar
                    $res = '';
                    $formtemplate = '';
                    $tableau = formulaire_valeurs_template_champs($tab_nature['bn_template']);
                    for ($i = 0; $i < count($tableau); $i++) {
                        if ($tableau[$i][0] == 'liste' || $tableau[$i][0] == 'checkbox' ||
                        $tableau[$i][0] == 'listefiche' || $tableau[$i][0] == 'checkboxfiche') {
                            $nom_champ = $tableau[$i][0] . $tableau[$i][1] . $tableau[$i][6];
                        } elseif ($tableau[$i][0] == 'image' || $tableau[$i][0] == 'fichier') {
                            $nom_champ = $tableau[$i][0] . $tableau[$i][1];
                        } elseif ($tableau[$i][0] == 'titre') {
                            $nom_champ = 'bf_titre';
                        } else {
                            $nom_champ = $tableau[$i][1];
                        }

                        if ($tableau[$i][0] == 'titre' || $nom_champ == 'bf_titre') {
                            $res.= '<h2 class="entry-title">{{{bf_titre}}}</h2>' . "\n\n";
                        } elseif ($tableau[$i][0] == 'image') {
                            $url = str_replace('wakka.php?wiki=', '', $this->config['base_url']);
                            $res.= '{{#if ' . $nom_champ . '}}' . "\n".
                                '<img class="img-responsive img-centered" src="'.$url.'cache/vignette_{{'.$nom_champ.
                                '}}" alt="{{' . $nom_champ . '}}">' . "\n" . '{{/if}}' . "\n\n";
                        } elseif ($tableau[$i][0] == 'labelhtml') {
                            $res.= $tableau[$i][0]($formtemplate, $tableau[$i], 'html', array());
                        } elseif ($tableau[$i][0] == 'inscriptionliste' || $tableau[$i][0] == 'utilisateur_wikini') {
                        } elseif ($tableau[$i][0] == 'liste' || $tableau[$i][0] == 'textelong'
                            || $tableau[$i][0] == 'jour' || $tableau[$i][0] == 'listedatefin'
                            || $tableau[$i][0] == 'listedatedeb' || $tableau[$i][0] == 'champs_mail'
                            || $tableau[$i][0] == 'lien_internet') {
                            $res.= '{{#if ' . $nom_champ . '}}' . "\n" .
                                '<div class="BAZ_rubrique" data-id="' . $nom_champ . '">' . "\n" .
                                '<span class="BAZ_label">' . $tableau[$i][2] . ' :</span>' . "\n" .
                                '<span class="BAZ_texte">{{{' . $nom_champ . '}}}</span>' . "\n" .
                                '</div> <!-- /.BAZ_rubrique -->' . "\n" .
                                '{{/if}}' . "\n\n";
                        } elseif (function_exists($tableau[$i][0])) {
                            $texte = trim(
                                $tableau[$i][0](
                                    $formtemplate,
                                    $tableau[$i],
                                    'html',
                                    array($nom_champ => '{{' . $nom_champ . '}}')
                                )
                            );
                            if ($tableau[$i][0] == 'checkbox') {
                                $texte = preg_replace(
                                    '|<span class="BAZ_texte">.*</span>|Uis',
                                    '<span class="BAZ_texte">{{{' . $nom_champ . '}}}</span>',
                                    $texte
                                );
                            }
                            $res.= '{{#if ' . $nom_champ . '}}' . "\n" . $texte . "\n" . '{{/if}}' . "\n\n";
                        }
                    }
                    echo $res;
                } elseif ($type == 'form') { // template d'un formulaire bazar
                    $url = $this->href('json', $this->GetPageTag(), 'demand=save_entry');

                    // generation du formulaire
                    $form = baz_afficher_formulaire_fiche('saisie', $url, '', true);
                    $form = preg_replace(
                        '~<div class="form-actions">.*</div>~Ui',
                        "\n" . '<a href="#" class="btn btn-block btn-positive btn-save">' . _t('BAZ_SAVE') . '</a>',
                        $form
                    );
                    $form = preg_replace('~<div id="map".*>~Ui', "\n" . '<div id="map">', $form);
                    echo json_encode(array('html' => $form));
                }
            }
            break;
        case "forms":
            // les formulaires bazar
            $formval = baz_valeurs_formulaire($form);
            // si un seul formulaire, on cree un tableau à une entrée
            if (!empty($form)) {
                $formval = array($formval['bn_id_nature'] => $formval);
            }
            if (!function_exists('sortByLabel')) {
                function sortByLabel($a, $b)
                {
                    return $a['bn_label_nature'] < $b['bn_label_nature'];
                }
            }
            if ($formval) {
                usort($formval, 'sortByLabel');
                $formval = _convert($formval, 'UTF-8');
            }
            echo json_encode($formval);
            break;
        case "entries":
            // liste de fiches bazar

            // chaine de recherche
            $q = '';
            if (isset($_GET['q']) and !empty($_GET['q'])) {
                $q = $_GET['q'];
            }

            // TODO : gerer les queries
            $query = '';

            //on recupere toutes les fiches du type choisi et on les met au format csv
            $results = baz_requete_recherche_fiches(
                $tabquery,
                $order,
                $form,
                '',
                1,
                '',
                '',
                true,
                $q
            );

            $tab_entries = array();
            foreach ($results as $wikipage) {
                $decoded_entry = json_decode($wikipage['body'], true);
                //json = norme d'ecriture utilisée pour les fiches bazar (en utf8)
                if ($html == '1') {
                    $fichehtml = baz_voir_fiche(0, $decoded_entry);
                    $regexp = '/<div.*data-id="(.*)".*>\s*<span class="BAZ_label.*">.*<\/span>\s*'.
                    '<span class="BAZ_texte">\s*(.*)\s*<\/span>\s*<\/div> <!-- \/.BAZ_rubrique -->/Uis';
                    preg_match_all($regexp, $fichehtml, $matches);
                    if (isset($matches[1]) && count($matches[1]) > 0) {
                        foreach ($matches[1] as $key => $value) {
                            $decoded_entry[$value] = $matches[2][$key];
                        }
                    }
                }

                // Output JSON-LD
                if( $is_semantic ) {
                    $tab_entries[] = baz_format_jsonld($form, $decoded_entry);
                } else {
                    $tab_entries[$decoded_entry['id_fiche']] = array_map('strval', $decoded_entry);
                }
            }
            if (count($tab_entries)>0) {
                ksort($tab_entries);
                echo json_encode($tab_entries);
            }
            break;
        case "pages":
            // recuperation des pages wikis
            $sql = 'SELECT * FROM '.$this->GetConfigValue('table_prefix').'pages';
            $sql .= ' WHERE latest="Y" AND comment_on="" AND tag NOT LIKE "LogDesActionsAdministratives%" ';
            $sql .= ' AND tag NOT IN (SELECT resource FROM '.$this->GetConfigValue('table_prefix').'triples WHERE property="http://outils-reseaux.org/_vocabulary/type") ';
            $sql .= ' ORDER BY tag ASC';
            $pages = _convert($this->LoadAll($sql), 'ISO-8859-15');
            $pagesindex = array();
            foreach ($pages as $page) {
                $pagesindex[$page["tag"]] = $page;
            }
            echo json_encode($pagesindex);
            //echo array_map('json_encode', );
            break;
        case "comments":
            // les commentaires wiki
            echo json_encode($this->LoadRecentComments());
            break;
    }
}
