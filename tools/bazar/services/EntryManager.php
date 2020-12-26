<?php

namespace YesWiki\Bazar\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Bazar\Field\BazarField;
use YesWiki\Bazar\Field\TitleField;
use YesWiki\Core\Service\DbService;
use YesWiki\Core\Service\Mailer;
use YesWiki\Core\Service\TripleStore;
use YesWiki\Wiki;

class EntryManager
{
    protected $wiki;
    protected $mailer;
    protected $tripleStore;
    protected $dbService;
    protected $semanticTransformer;
    protected $params;

    public const TRIPLES_ENTRY_ID = 'fiche_bazar';

    public function __construct(Wiki $wiki, Mailer $mailer, TripleStore $tripleStore, DbService $dbService, SemanticTransformer $semanticTransformer, ParameterBagInterface $params)
    {
        $this->wiki = $wiki;
        $this->mailer = $mailer;
        $this->tripleStore = $tripleStore;
        $this->dbService = $dbService;
        $this->semanticTransformer = $semanticTransformer;
        $this->params = $params;
    }

    /**
     * Returns true if the provided page is a Bazar fiche
     * @param $tag
     * @return bool
     */
    public function isEntry($tag) : bool
    {
        return !is_null($this->tripleStore->exist($tag, TripleStore::TYPE_URI, self::TRIPLES_ENTRY_ID, '', ''));
    }

    /**
     * Get one specified fiche
     * @param $tag
     * @param bool $semantic
     * @param string $time pour consulter une fiche dans l'historique
     * @return mixed|null
     */
    public function getOne($tag, $semantic = false, $time = null) : ?array
    {
        if (!$this->isEntry($tag)) {
            return null;
        }

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
    public function search($params = []) : array
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
        // TODO refactor to use the TripleStore service
        $requete_pages_wiki_bazar_fiches =
            'SELECT DISTINCT resource FROM '.$this->dbService->prefixTable('triples').
            'WHERE value = "fiche_bazar" AND property = "http://outils-reseaux.org/_vocabulary/type" '.
            'ORDER BY resource ASC';

        $requete =
            'SELECT DISTINCT * FROM '.$this->dbService->prefixTable('pages').
            'WHERE latest="Y" AND comment_on = \'\'';

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
            $params['user'] = $this->dbService->escape(
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
            $this->dbService->query("SET sql_mode = 'NO_BACKSLASH_ESCAPES';");
            $search = str_replace(array('["', '"]'), '', json_encode(array(removeAccents($params['keywords']))));
            $recherche = explode(' ', $search);
            $nbmots = count($recherche);
            $requeteSQL .= ' AND (';
            for ($i = 0; $i < $nbmots; ++$i) {
                if ($i > 0) {
                    $requeteSQL .= ' OR ';
                }
                $requeteSQL .= ' body LIKE \'%'.$this->dbService->escape($recherche[$i]).'%\'';
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
            $results = $this->dbService->loadAll($requete);
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

        if ($semantic) {
            $data = $this->semanticTransformer->convertFromSemanticData($formId, $data);
        }

        $this->validate($data);

        $data = $this->formatDataBeforeSave($data);

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
        if ($this->params->has('bazarIgnoreAcls')) {
            $ignoreAcls = $this->params->get('bazarIgnoreAcls');
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
            $this->tripleStore->create(
                $data['id_fiche'],
                TripleStore::TYPE_URI,
                self::TRIPLES_ENTRY_ID,
                '',
                ''
            );
        }

        if ($sourceUrl) {
            $this->tripleStore->create(
                $data['id_fiche'],
                TripleStore::SOURCE_URL_URI,
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

        if ($this->params->get('BAZ_ENVOI_MAIL_ADMIN')) {
            // Envoi d'un mail aux administrateurs
            $this->mailer->notifyAdmins($data, true);
        }

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
        if (!$this->wiki->HasAccess('write', $tag)) {
            throw new \Exception(_t('BAZ_ERROR_EDIT_UNAUTHORIZED'));
        }

        $previousData = $this->getOne($tag);
        $previousData = $this->assignRestrictedFields($previousData);

        if ($semantic) {
            $data = $this->semanticTransformer->convertFromSemanticData($previousData['id_typeannonce'], $data);
        }

        if ($replace) {
            $data['id_typeannonce'] = $previousData['id_typeannonce'];
        } else {
            // If PATCH, overwrite previous data with new data
            $data = array_merge($previousData, $data);
        }

        $this->validate($data);

        $data = $this->formatDataBeforeSave($data);

        // on sauve les valeurs d'une fiche dans une PageWiki, pour garder l'historique
        $this->wiki->SavePage($data['id_fiche'], json_encode($data));

        if ($this->params->get('BAZ_ENVOI_MAIL_ADMIN')) {
            // Envoi d'un mail aux administrateurs
            $this->mailer->notifyAdmins($data, false);
        }

        return $data;
    }

    /**
     * Delete a fiche
     * @param $tag
     * @throws \Exception
     */
    public function delete($tag)
    {
        if (!$this->wiki->HasAccess('write', $tag)) {
            throw new \Exception(_t('BAZ_ERROR_DELETE_UNAUTHORIZED'));
        }

        $fiche = $this->getOne($tag);

        // Si besoin, on supprime l'utilisateur associé
        if (isset($fiche['nomwiki'])) {
            $request = 'DELETE FROM '.$this->dbService->prefixTable('users').' WHERE `name` = "'. $fiche['nomwiki'].'"';
            $this->dbService->query($request);
        }

        $this->wiki->DeleteOrphanedPage($tag);
        $this->tripleStore->delete($tag, TripleStore::TYPE_URI, null, '', '');
        $this->tripleStore->delete($tag, TripleStore::SOURCE_URL_URI, null, '', '');
        $this->wiki->LogAdministrativeAction($this->wiki->GetUserName(), "Suppression de la page ->\"\"" . $tag . "\"\"");
    }

    /*
     * Convert body to JSON object
     */
    public function decode($body)
    {
        $data = json_decode($body, true);
        foreach ($data as $key => $value) {
            $data[$key] = _convert($value, 'UTF-8');
        }
        return $data;
    }
    
    /*
     * prepare la requete d'insertion ou de MAJ de la fiche en supprimant
     * de la valeur POST les valeurs inadequates et en formattant les champs.
     */
    public function formatDataBeforeSave($data)
    {
        $form = baz_valeurs_formulaire($data['id_typeannonce']);

        // If there is a title field, compute the entry's title
        for ($i = 0; $i < count($form['template']); ++$i) {
            if ($form['prepared'][$i] instanceof TitleField) {
                $data = array_merge($data, $form['prepared'][$i]->formatValuesBeforeSave($data));
            }
        }

        // Entry ID
        if (!isset($data['id_fiche'])) {
            // Generate the ID from the title
            $data['id_fiche'] = genere_nom_wiki($data['bf_titre']);
            // TODO see if we can remove this
            $_POST['id_fiche'] = $data['id_fiche'];
        }

        // Entry creator
        if ($GLOBALS['wiki']->GetPageOwner($data['id_fiche'])) {
            $data['createur'] = $GLOBALS['wiki']->GetPageOwner($data['id_fiche']);
        } elseif ($user = $GLOBALS['wiki']->GetUser()) {
            $data['createur'] = $user['name'];
        } else {
            $data['createur'] = _t('BAZ_ANONYME');
        }

        $data['id_typeannonce'] = isset($data['id_typeannonce']) ? $data['id_typeannonce'] : $_REQUEST['id_typeannonce'];

        // Get creation date if it exists, initialize it otherwise
        $result = $this->dbService->loadSingle('SELECT MIN(time) as firsttime FROM '.$this->dbService->prefixTable('pages')."WHERE tag='".$data['id_fiche']."'");
        $data['date_creation_fiche'] = $result['firsttime'] ? $result['firsttime'] : date('Y-m-d H:i:s', time());

        // Entry status
        if ($GLOBALS['wiki']->UserIsAdmin()) {
            $data['statut_fiche'] = '1';
        } else {
            $data['statut_fiche'] = $this->params->get('BAZ_ETAT_VALIDATION');
        }

        for ($i = 0; $i < count($form['template']); ++$i) {
            if( $form['prepared'][$i] instanceof BazarField) {
                $tab = $form['prepared'][$i]->formatValuesBeforeSave($data);
            } else if (function_exists($form['template'][$i][0])){
                $tab = $form['template'][$i][0](
                    $formtemplate,
                    $form['template'][$i],
                    'requete',
                    $data
                );
            }

            if (is_array($tab)) {
                if (isset($tab['fields-to-remove']) and is_array($tab['fields-to-remove'])) {
                    foreach ($tab['fields-to-remove'] as $field) {
                        if (isset($data[$field])) {
                            unset($data[$field]);
                        }
                    }
                    unset($tab['fields-to-remove']);
                }
                $data = array_merge($data, $tab);
            }
        }
        $data['date_maj_fiche'] = date('Y-m-d H:i:s', time());

        // If sendmail field exist, send an email
        if (isset($data['sendmail'])) {
            if ($data[$data['sendmail']] != '') {
                $this->mailer->notifyEmail($data[$data['sendmail']]);
            }
            unset($data['sendmail']);
        }

        // on enleve les champs hidden pas necessaires a la fiche
        unset($data['valider']);
        unset($data['MAX_FILE_SIZE']);
        unset($data['antispam']);
        unset($data['mot_de_passe_wikini']);
        unset($data['mot_de_passe_repete_wikini']);
        unset($data['html_data']);

        // on encode en utf-8 pour reussir a encoder en json
        if (YW_CHARSET != 'UTF-8') {
            $data = array_map('utf8_encode', $data);
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
        if ($semantic) {
            $fiche['semantic'] = $this->semanticTransformer->convertToSemanticData($fiche['id_typeannonce'], $fiche);
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
            $sql = 'SELECT * FROM ' . $this->dbService->prefixTable('pages') . " WHERE tag = '" . $this->dbService->escape($data['id_fiche']) . "' AND latest = 'Y'" . " LIMIT 1";
            $valjson = $this->dbService->loadSingle($sql);
            $old_fiche = json_decode($valjson['body'], true);
            foreach ($old_fiche as $key => $value) {
                $old_fiche[$key] = _convert($value, 'UTF-8');
            }
            foreach ($protected_fields_index as $index) {
                if (in_array($template[$index][0], $INDEX_CHELOUS)) {
                    $data[$template[$index][0] . $template[$index][1] . $template[$index][6]] = $old_fiche[$template[$index][0] . $template[$index][1] . $template[$index][6]];
                } else {
                    $data[$template[$index][1]] = $old_fiche[$template[$index][1]];
                }
            }
        }
        return $data;
    }
}
