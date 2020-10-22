<?php

namespace YesWiki;

class BazarFiche
{
    protected $wiki = ''; // give access to the main wiki object

    public function __construct($wiki)
    {
        $this->wiki = $wiki;
    }

    /**
     * Returns true if the provided page is a Bazar fiche
     * @param $tag
     * @return bool
     */
    public function isFiche($tag)
    {
        $pageType = $this->wiki->GetTripleValue($tag, 'http://outils-reseaux.org/_vocabulary/type', '', '');
        return ($pageType === 'fiche_bazar');
    }

    /**
     * Get one specified fiche
     * @param $tag
     * @param false $semantic
     * @param string $time pour consulter une fiche dans l'historique
     * @return mixed|null
     */
    public function getOne($tag, $semantic = false, $time = null)
    {
        if( !$this->isFiche($tag) ) return false;

        $page = $this->wiki->LoadPage($tag, $time || '');
        $data = $this->decode($page['body']);

        // cas ou on ne trouve pas les valeurs id_fiche
        if (!isset($data['id_fiche'])) {
            $data['id_fiche'] = $tag;
        }

        // TODO call this function only when necessary
        $this->appendDisplayData($data, $semantic);

        return $data;
    }

    /**
     * Return an array of fiches based on search parameters
     * @param array $params
     * @return mixed
     */
    public function search($params = [])
    {
        // Merge les paramètres passé avec des paramètres par défaut
        $params = array_merge(
            [
                'queries' => '', // Sélection par clé-valeur
                'formsIds' => [], // Types de fiches (par ID de formulaire)
                'user' => '', // N'affiche que les fiches d'un utilisateur
                'keywords' => '', // Mots-clés pour la recherche fulltext
                'searchOperator' => 'OR' // Opérateur à appliquer aux mots-clés
            ],
            $params
        );

        // requete pour recuperer toutes les PageWiki etant des fiches bazar
        $requete_pages_wiki_bazar_fiches =
            'SELECT DISTINCT resource FROM '.$this->wiki->config['table_prefix'].'triples '.
            'WHERE value = "fiche_bazar" AND property = "http://outils-reseaux.org/_vocabulary/type" '.
            'ORDER BY resource ASC';

        $requete =
            'SELECT DISTINCT * FROM '.$this->wiki->config['table_prefix'].
            'pages WHERE latest="Y" AND comment_on = \'\'';

        // On limite au type de fiche
        if (!empty($params['formsIds'])) {
            if (is_array($params['formsIds'])) {
                $requete .= ' AND ' . join(' OR ', array_map(function ($formId) {
                        return 'body LIKE \'%"id_typeannonce":"' . $formId . '"%\'';
                    }, $params['formsIds']));
            } else {
                // on a une chaine de caractere pour l'id plutot qu'un tableau
                $requete .= ' AND body LIKE \'%"id_typeannonce":"' . $params['formsIds'] . '"%\'';
            }
        }

        // periode de modification
        if (!empty($GLOBALS['params']['datemin'])) {
            $requete .= ' AND time >= "'.$GLOBALS['params']['datemin'].'"';
        }

        // si une personne a ete precisee, on limite la recherche sur elle
        if ($params['user'] !== '') {
            $params['user'] = mysqli_escape_string(
                $this->wiki->dblink,
                preg_replace('/^"(.*)"$/', '$1', json_encode($params['user']))
            );
            // WTF : https://stackoverflow.com/questions/13287145/mysql-querying-for-unicode-entities#13327605
            $params['user'] = str_replace('\\u00', '\\\\\u00', $params['user']);

            $requete .= ' AND body LIKE _utf8\'%"createur":"'.$params['user'].'"%\'';
        }

        $requete .= ' AND tag IN ('.$requete_pages_wiki_bazar_fiches.')';

        $requeteSQL = '';

        //preparation de la requete pour trouver les mots cles
        if (trim($params['keywords']) != '' && $params['keywords'] !=_t('BAZ_MOT_CLE')) {
            $this->wiki->Query("SET sql_mode = 'NO_BACKSLASH_ESCAPES';");
            $search = str_replace(array('["', '"]'), '', json_encode(array(removeAccents($params['keywords']))));
            $recherche = explode(' ', $search);
            $nbmots = count($recherche);
            $requeteSQL .= ' AND (';
            for ($i = 0; $i < $nbmots; ++$i) {
                if ($i > 0) {
                    $requeteSQL .= ' OR ';
                }
                $requeteSQL .= ' body LIKE \'%'.mysqli_escape_string($this->wiki->dblink, $recherche[$i]).'%\'';
            }
            $requeteSQL .= ')';
        }

        //on ajoute dans la requete les valeurs passees dans les champs liste et checkbox du moteur de recherche
        if ($params['queries'] == '') {
            $params['queries'] = array();

            // on transforme les specifications de recherche sur les liste et checkbox
            if (isset($_REQUEST['rechercher'])) {
                reset($_REQUEST);

                foreach ($_REQUEST as $nom => $val) {
                    if (((substr($nom, 0, 5) == 'liste') || (substr($nom, 0, 8) ==
                                'checkbox')) && $val != '0' && $val != '') {
                        if (is_array($val)) {
                            $val = implode(',', array_keys($val));
                        }
                        $params['queries'][$nom] = $val;
                    }
                }
            }
        }

        // cas des criteres passés en parametres get
        if (isset($_GET['query'])) {
            $query = $_GET['query'];
            $tableau = array();
            $tab = explode('|', $query);
            //découpe la requete autour des |
            foreach ($tab as $req) {
                $tabdecoup = explode('=', $req, 2);
                if (count($tabdecoup)>1) {
                    $tableau[$tabdecoup[0]] = trim($tabdecoup[1]);
                }
            }
            $params['queries'] = array_merge($params['queries'], $tableau);
        }

        reset($params['queries']);

        foreach ($params['queries'] as $nom => $val) {
            if (!empty($nom) && !empty($val)) {
                $valcrit = explode(',', $val);
                if (is_array($valcrit) && count($valcrit) > 1) {
                    $requeteSQL .= ' AND (';
                    $first = true;
                    foreach ($valcrit as $critere) {
                        if (!$first) {
                            $requeteSQL .= ' '.$params['searchOperator'].' ';
                        }

                        if (strcmp(substr($nom, 0, 5), 'liste') == 0) {
                            $requeteSQL .=
                                'body REGEXP \'"'.$nom.'":"'.$critere.'"\'';
                        } else {
                            $requeteSQL .=
                                'body REGEXP \'"'.$nom.'":("'.$critere.
                                '"|"[^"]*,'.$critere.'"|"'.$critere.',[^"]*"|"[^"]*,'
                                .$critere.',[^"]*")\'';
                        }

                        $first = false;
                    }
                    $requeteSQL .= ')';
                } else {
                    if (strcmp(substr($nom, 0, 5), 'liste') == 0) {
                        $requeteSQL .=
                            ' AND (body REGEXP \'"'.$nom.'":"'.$val.'"\')';
                    } else {
                        $requeteSQL .=
                            ' AND (body REGEXP \'"'.$nom.'":("'.$val.
                            '"|"[^"]*,'.$val.'"|"'.$val.',[^"]*"|"[^"]*,'
                            .$val.',[^"]*")\')';
                    }
                }
            }
        }

        // requete de jointure : reprend la requete precedente et ajoute des criteres
        if (isset($_GET['joinquery'])) {
            $joinrequeteSQL = '';
            $tableau = array();
            $tab = explode('|', $_GET['joinquery']);
            //découpe la requete autour des |
            foreach ($tab as $req) {
                $tabdecoup = explode('=', $req, 2);
                $tableau[$tabdecoup[0]] = trim($tabdecoup[1]);
            }
            $first = true;

            foreach ($tableau as $nom => $val) {
                if (!empty($nom) && !empty($val)) {
                    $valcrit = explode(',', $val);
                    if (is_array($valcrit) && count($valcrit) > 1) {
                        foreach ($valcrit as $critere) {
                            if (!$first) {
                                $joinrequeteSQL .= ' AND ';
                            } else {
                                $first = false;
                            }
                            $joinrequeteSQL .=
                                '(body REGEXP \'"'.$nom.'":"[^"]*'.$critere.
                                '[^"]*"\')';
                        }
                        $joinrequeteSQL .= ')';
                    } else {
                        if (!$first) {
                            $joinrequeteSQL .= ' AND ';
                        } else {
                            $first = false;
                        }
                        if (strcmp(substr($nom, 0, 5), 'liste') == 0) {
                            $joinrequeteSQL .=
                                '(body REGEXP \'"'.$nom.'":"'.$val.'"\')';
                        } else {
                            $joinrequeteSQL .=
                                '(body REGEXP \'"'.$nom.'":("'.$val.
                                '"|"[^"]*,'.$val.'"|"'.$val.',[^"]*"|"[^"]*,'
                                .$val.',[^"]*")\')';
                        }
                    }
                }
            }
            if ($requeteSQL != '') {
                $requeteSQL .= ' UNION '.$requete.' AND ('.$joinrequeteSQL.')';
            } else {
                $requeteSQL .= ' AND ('.$joinrequeteSQL.')';
            }
            $requete .= $requeteSQL;
        } elseif ($requeteSQL != '') {
            $requete .= $requeteSQL;
        }

        // debug
        if (isset($_GET['showreq'])) {
            echo '<hr><code style="width:100%;height:100px;">'.$requete.'</code><hr>';
        }

        // systeme de cache des recherches
        // TODO voir si ça sert à quelque chose
        $reqid = 'bazar-search-'.md5($requete);
        if (!isset($GLOBALS['_BAZAR_'][$reqid])) {
            $GLOBALS['_BAZAR_'][$reqid] = array();
            $results = $this->wiki->LoadAll($requete);
            foreach ($results as $page) {
                $json = $this->decode($page['body']);
                $GLOBALS['_BAZAR_'][$reqid][$json['id_fiche']] = $json;
            }
        }
        return $GLOBALS['_BAZAR_'][$reqid];
    }

    /**
     * Validate the fiche's data
     * @param $data
     * @throws \Exception
     */
    public function validate($data)
    {
        if (!isset($data['antispam']) or !$data['antispam'] == 1) {
            throw new \Exception(_t('BAZ_PROTECTION_ANTISPAM'));
        }

        // On teste le titre car ça peut bugguer sérieusement sans
        if (!isset($data['bf_titre'])) {
            throw new \Exception(_t('BAZ_FICHE_NON_SAUVEE_PAS_DE_TITRE'));
        }

        // form metadata
        if (!isset($data['id_typeannonce'])) {
            throw new \Exception(_t('BAZ_NO_FORMS_FOUND'));
        }
    }

    /**
     * Create a new fiche
     * @param $formId
     * @param $data
     * @param false $semantic
     * @param null $sourceUrl
     * @return array
     * @throws \Exception
     */
    public function create($formId, $data, $semantic = false, $sourceUrl = null)
    {
        $data['id_typeannonce'] = "$formId"; // Must be a string

        if( $semantic ) {
            $data = $this->convertFromSemanticData($formId, $data);
        }

        $this->validate($data);

        $data = baz_requete_bazar_fiche($data);

        // on change provisoirement d'utilisateur
        if (isset($GLOBALS['utilisateur_wikini'])) {
            $olduser = $this->wiki->GetUser();
            $this->wiki->LogoutUser();

            // On s'identifie de facon a attribuer la propriete de la fiche a
            // l'utilisateur qui vient d etre cree
            $user = $this->wiki->LoadUser($GLOBALS['utilisateur_wikini']);
            $this->wiki->SetUser($user);
        }

        $ignoreAcls = true;
        if (isset($this->wiki->config['bazarIgnoreAcls'])) {
            $ignoreAcls = $this->wiki->config['bazarIgnoreAcls'];
        }

        // on sauve les valeurs d'une fiche dans une PageWiki, retourne 0 si succès
        $saved = $this->wiki->SavePage(
            $data['id_fiche'],
            json_encode($data),
            '',
            $ignoreAcls // Ignore les ACLs
        );

        // on cree un triple pour specifier que la page wiki creee est une fiche
        // bazar
        if ($saved == 0) {
            $this->wiki->InsertTriple(
                $data['id_fiche'],
                'http://outils-reseaux.org/_vocabulary/type',
                'fiche_bazar',
                '',
                ''
            );
        }

        if ($sourceUrl) {
            $this->wiki->InsertTriple(
                $data['id_fiche'],
                'http://outils-reseaux.org/_vocabulary/sourceUrl',
                $sourceUrl,
                '',
                ''
            );
        }

        // on remet l'utilisateur initial
        if (isset($GLOBALS['utilisateur_wikini'])) {
            $this->wiki->LogoutUser();
            if (!empty($olduser)) {
                $this->wiki->SetUser($olduser, 1);
            }
        }

        // Envoi d'un mail aux administrateurs
        $this->notifyAdmins($data, true);

        return $data;
    }

    /**
     * Update a fiche with the provided data
     * @param $tag
     * @param $data
     * @param false $semantic
     * @param false $replace If true, all the data will be provided
     * @throws \Exception
     */
    public function update($tag, $data, $semantic = false, $replace = false)
    {
        if( !$this->wiki->HasAccess('write', $tag) ) {
            throw new \Exception(_t('BAZ_ERROR_EDIT_UNAUTHORIZED'));
        }

        $previousData = $this->getOne($tag);
        $previousData = $this->assignRestrictedFields($previousData);

        if( $semantic ) {
            $data = $this->convertFromSemanticData($previousData['id_typeannonce'], $data);
        }

        if( $replace ) {
            $data['id_typeannonce'] = $previousData['id_typeannonce'];
        } else {
            // If PATCH, overwrite previous data with new data
            $data = array_merge($previousData, $data);
        }

        $this->validate($data);

        $data = baz_requete_bazar_fiche($data);

        // on sauve les valeurs d'une fiche dans une PageWiki, pour garder l'historique
        $this->wiki->SavePage($data['id_fiche'], json_encode($data));

        // Envoi d'un mail aux administrateurs
        $this->notifyAdmins($data, false);

        return $data;
    }

    /**
     * Delete a fiche
     * @param $tag
     * @throws \Exception
     */
    public function delete($tag)
    {
        if( !$this->wiki->HasAccess('write', $tag) ) {
            throw new \Exception(_t('BAZ_ERROR_DELETE_UNAUTHORIZED'));
        }

        $fiche = $this->getOne($tag);

        // Si besoin, on supprime l'utilisateur associé
        if (isset($fiche['nomwiki'])) {
            $request = 'DELETE FROM `'.$this->wiki->config['table_prefix'].'users` WHERE `name` = "'. $fiche['nomwiki'].'"';
            $this->wiki->query($request);
        }

        $this->wiki->DeleteOrphanedPage($tag);
        $this->wiki->DeleteTriple($tag, 'http://outils-reseaux.org/_vocabulary/type', null, '', '');
        $this->wiki->DeleteTriple($tag, 'http://outils-reseaux.org/_vocabulary/sourceUrl', null, '', '');
        $this->wiki->LogAdministrativeAction($this->wiki->GetUserName(), "Suppression de la page ->\"\"" . $tag . "\"\"");
    }

    public function convertToSemanticData($formId, $data, $isHtmlFormatted = false)
    {
        $form = baz_valeurs_formulaire($formId);
        if (!$form['bn_sem_type']) {
            throw new \Exception(_t('BAZAR_SEMANTIC_TYPE_MISSING'));
        }

        // If context is a JSON decode it, otherwise use the string
        $semanticData['@context'] = (array) json_decode($form['bn_sem_context']) ?: $form['bn_sem_context'];

        // If we have multiple types split by comma, generate an array, otherwise use a string
        $semanticData['@type'] = strpos($form['bn_sem_type'], ',')
            ? array_map(function ($str) {
                return trim($str);
            }, explode(',', $form['bn_sem_type']))
            : $form['bn_sem_type'];

        // Add the ID of the Bazar object
        $semanticData['@id'] = $GLOBALS['wiki']->href('', $data['id_fiche']);

        $fields_infos = bazPrepareFormData($form);
        foreach ($fields_infos as $field_info) {
            // If the file is not semantically defined, ignore it
            if ($field_info['sem_type']) {
                $value = $data[$field_info['id']];
                if ($value) {
                    // We don't want this additional formatting if we are already dealing with HTML-formatted data
                    if (!$isHtmlFormatted) {
                        // If this is a file or image, add the base URL
                        if ($field_info['type'] === 'file') {
                            $value = $GLOBALS['wiki']->getBaseUrl() . "/" . BAZ_CHEMIN_UPLOAD . $value;
                        }

                        // If this is a linked entity (listefiche), use the URL
                        if (startsWith($field_info['id'], 'listefiche')) {
                            $value = $GLOBALS['wiki']->href('', $value);
                        }
                    }

                    if (is_array($field_info['sem_type'])) {
                        // If we have multiple fields, duplicate the data
                        foreach ($field_info['sem_type'] as $sem_type) {
                            $semanticData[$sem_type] = $value;
                        }
                    } else {
                        $semanticData[$field_info['sem_type']] = $value;
                    }
                }
            }
        }

        return $semanticData;
    }

    protected function convertFromSemanticData($formId, $data)
    {
        // Initialize by copying basic information
        $nonSemanticData = ['id_fiche' => $data['id_fiche'], 'antispam' => $data['antispam'], 'id_typeannonce' => $data['id_typeannonce']];

        $form = baz_valeurs_formulaire($formId);

        if( ($data['@type'] && $data['@type'] !== $form['bn_sem_type']) || $data['type'] && $data['type'] !== $form['bn_sem_type'] ) {
            exit('The @type of the sent data must be ' . $form['bn_sem_type']);
        }

        $fields_infos = bazPrepareFormData($form);
        foreach ($fields_infos as $field_info) {
            // If the file is not semantically defined, ignore it
            if ($field_info['sem_type'] && $data[$field_info['sem_type']]) {
                if( $field_info['type'] === 'date') {
                    $date = new \DateTime($data[$field_info['sem_type']]);
                    $nonSemanticData[$field_info['id']] = $date->format('Y-m-d');
                    $nonSemanticData[$field_info['id'] . '_allday'] = 0;
                    $nonSemanticData[$field_info['id'] . '_hour'] = $date->format('H');
                    $nonSemanticData[$field_info['id'] . '_minutes'] = $date->format('i');
                } elseif ($field_info['type'] === 'image') {
                    $nonSemanticData['image'.$field_info['id']] = $data[$field_info['sem_type']];
                } else {
                    $nonSemanticData[$field_info['id']] = $data[$field_info['sem_type']];
                }
            }
        }

        return $nonSemanticData;
    }

    /*
     * Convert body to JSON object
     */
    protected function decode($body)
    {
        $data = json_decode($body, true);
        foreach ($data as $key => $value) {
            $data[$key] = _convert($value, 'UTF-8');
        }
        return $data;
    }

    /*
     * Append data needed for display
     * TODO move this to a class dedicated to display
     */
    public function appendDisplayData(&$fiche, $semantic = false, $correspondance = '')
    {
        // champs correspondants
        if (!empty($correspondance)) {
            $tabcorrespondances = getMultipleParameters($correspondance, ',', '=');
            if ($tabcorrespondances['fail'] != 1) {
                foreach ($tabcorrespondances as $key=>$data) {
                    if (isset($key)) {
                        if (isset($data) && isset($fiche[$data])) {
                            $fiche[$key] = $fiche[$data];
                        } else {
                            $fiche[$key] = '';
                        }
                    } else {
                        echo '<div class="alert alert-danger">action bazarliste : parametre correspondance mal rempli : il doit etre de la forme correspondance="identifiant_1=identifiant_2" ou correspondance="identifiant_1=identifiant_2, identifiant_3=identifiant_4"</div>';
                    }
                }
            } else {
                echo '<div class="alert alert-danger">action bazarliste : le paramètre correspondance est mal rempli.<br />Il doit être de la forme correspondance="identifiant_1=identifiant_2" ou correspondance="identifiant_1=identifiant_2, identifiant_3=identifiant_4"</div>';
            }
        }

        // HTML data
        $fiche['html_data'] = getHtmlDataAttributes($fiche);
        $fiche['datastr'] = $fiche['html_data'];

        // Fiche URL
        $exturl = $GLOBALS['wiki']->GetParameter('url');
        if (isset($exturl)) {
            // WIP concernant l'action bazarlisteexterne
            $arr = explode('/wakka.php', $exturl, 2);
            $exturl = $arr[0];
            $fiche['url'] = $exturl.'/wakka.php?wiki='.$fiche['id_fiche'];
        } else {
            $fiche['url'] = $GLOBALS['wiki']->href('', $fiche['id_fiche']);
        }

        // Données sémantiques
        if( $semantic ) {
            $fiche['semantic'] = $this->convertToSemanticData($fiche['id_typeannonce'], $fiche);
        }
    }

    protected function notifyAdmins($data, $new)
    {
        include_once 'tools/contact/libs/contact.functions.php';

        if (!empty($this->wiki->config['BAZ_ENVOI_MAIL_ADMIN']) && $this->wiki->config['BAZ_ENVOI_MAIL_ADMIN']) {
            $lien = str_replace('/wakka.php?wiki=', '', $this->wiki->config['base_url']);
            $sujet = removeAccents('[' . str_replace('http://', '', $lien) . '] nouvelle fiche ' . ($new ? 'ajoutee' : 'modifiee') . ' : ' . $data['bf_titre']);
            $text = 'Voir la fiche sur le site pour l\'administrer : ' . $this->wiki->href('', $data['id_fiche']);
            $texthtml = '<br /><br /><a href="' . $this->wiki->href('', $data['id_fiche']) . '" title="Voir la fiche">Voir la fiche sur le site pour l\'administrer</a>';
            $fichier = 'tools/bazar/presentation/styles/bazar.css';
            $style = file_get_contents($fichier);
            $style = str_replace('url(', 'url(' . $lien . '/tools/bazar/presentation/', $style);
            $fiche = str_replace(
                    'src="tools',
                    'src="' . $lien . '/tools',
                    baz_voir_fiche(0, $data['id_fiche'])
                ) . $texthtml;
            $html =
                '<html><head><style type="text/css">' . $style .
                '</style></head><body>' . $fiche . '</body></html>';

            // on va chercher les admins
            $requeteadmins = 'SELECT value FROM ' . $this->wiki->config['table_prefix'] . 'triples '
                . 'WHERE resource="ThisWikiGroup:admins" AND property="http://www.wikini.net/_vocabulary/acls" LIMIT 1';
            $ligne = $this->wiki->LoadSingle($requeteadmins);
            $tabadmin = explode("\n", $ligne['value']);
            foreach ($tabadmin as $line) {
                $admin = $this->wiki->LoadUser(trim($line));
                send_mail($this->wiki->config['BAZ_ADRESSE_MAIL_ADMIN'], $this->wiki->config['BAZ_ADRESSE_MAIL_ADMIN'], $admin['email'], $sujet, $text, $html);
            }
        }
    }

    /**
     * Met à jour les valeurs des champs qui sont restreints en écriture
     *
     * @param array $data l'objet contenant les valeurs issues de la saisie du formulaire
     * @return array tableau des valeurs de la fiche à sauver
     */
    protected function assignRestrictedFields(array $data)
    {
        // on regarde si des champs sont restreints en écriture pour l'utilisateur, et pour ceux-ci ont leur assigne la même valeur
        // (un LoadPage qui passe les droits ACLS est nécéssaire)

        // if the field type (index 0) is in the $INDEX_CHELOUS, the name used to identified the field is a concatenation of the index 0, 1 and 6
        $INDEX_CHELOUS = ['radio', 'liste', 'checkbox', 'listefiche', 'checkboxfiche'];
        $template = baz_valeurs_formulaire($data['id_typeannonce'])['template'];
        $protected_fields_index = [];
        for ($i = 0; $i < count($template); ++$i) {
            if (!empty($template[$i][12]) && !$this->wiki->CheckACL($template[$i][12])) {
                $protected_fields_index[] = $i;
            }
        }
        if (!empty($protected_fields_index)) {
            $sql = 'SELECT * FROM ' . $this->wiki->config['table_prefix'] . 'pages' . " WHERE tag = '" . mysqli_real_escape_string($this->wiki->dblink, $data['id_fiche']) . "' AND latest = 'Y'" . " LIMIT 1";
            $valjson = $this->wiki->LoadSingle($sql);
            $old_fiche = json_decode($valjson['body'], true);
            foreach ($old_fiche as $key => $value) {
                $old_fiche[$key] = _convert($value, 'UTF-8');
            }
            foreach ($protected_fields_index as $index) {
                if (in_array($template[$index][0], $INDEX_CHELOUS))
                    $data[$template[$index][0] . $template[$index][1] . $template[$index][6]] = $old_fiche[$template[$index][0] . $template[$index][1] . $template[$index][6]];
                else
                    $data[$template[$index][1]] = $old_fiche[$template[$index][1]];
            }
        }
        return $data;
    }
}
