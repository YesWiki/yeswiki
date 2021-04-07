<?php

namespace YesWiki\Bazar\Service;

use Exception;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Bazar\Field\BazarField;
use YesWiki\Bazar\Field\TitleField;
use YesWiki\Core\Service\AclService;
use YesWiki\Core\Service\DbService;
use YesWiki\Core\Service\Mailer;
use YesWiki\Core\Service\PageManager;
use YesWiki\Core\Service\TripleStore;
use YesWiki\Core\Service\UserManager;
use YesWiki\Wiki;

class EntryManager
{
    protected $wiki;
    protected $mailer;
    protected $pageManager;
    protected $tripleStore;
    protected $aclService;
    protected $userManager;
    protected $dbService;
    protected $semanticTransformer;
    protected $params;

    public const TRIPLES_ENTRY_ID = 'fiche_bazar';

    public function __construct(
        Wiki $wiki,
        Mailer $mailer,
        PageManager $pageManager,
        TripleStore $tripleStore,
        AclService $aclService,
        UserManager $userManager,
        DbService $dbService,
        SemanticTransformer $semanticTransformer,
        ParameterBagInterface $params
    ) {
        $this->wiki = $wiki;
        $this->mailer = $mailer;
        $this->pageManager = $pageManager;
        $this->tripleStore = $tripleStore;
        $this->aclService = $aclService;
        $this->userManager = $userManager;
        $this->dbService = $dbService;
        $this->semanticTransformer = $semanticTransformer;
        $this->params = $params;
    }

    /**
     * Returns true if the provided page is a Bazar fiche
     * @param $tag
     * @return bool
     */
    public function isEntry($tag): bool
    {
        return !is_null($this->tripleStore->exist($tag, TripleStore::TYPE_URI, self::TRIPLES_ENTRY_ID, '', ''));
    }

    /**
     * Get one specified fiche
     * @param $tag
     * @param bool $semantic
     * @param string $time pour consulter une fiche dans l'historique
     * @param bool $cache if false, don't use the page cache
     * @param bool $bypassAcls if true, all fields are loaded regardless of acls
     * @return mixed|null
     * @throws Exception
     */
    public function getOne($tag, $semantic = false, $time = null, $cache = true, $bypassAcls = false): ?array
    {
        if (!$this->isEntry($tag)) {
            return null;
        }

        $page = $this->pageManager->getOne($tag, $time || null, $cache, $bypassAcls);
        $debug = ($this->wiki->GetConfigValue('debug') == 'yes');
        $data = $this->getDataFromPage($page, $semantic, $debug);

        return $data;
    }

    /** getDataFromPage
     * @param array $page , content of page from sql
     * @param bool $semantic
     * @param bool $debug, to throw exception in case of error
     *
     * @return array data formated
     */
    private function getDataFromPage($page, bool $semantic = false, bool $debug = false): array
    {
        if (!empty($page['body'])) {
            $data = $this->decode($page['body']);

            if ($debug) {
                if (empty($data['id_fiche'])) {
                    trigger_error('empty \'id_fiche\' in EntryManager::getDataFromPage in body of page \''
                        . $page['tag'].'\'. Edit it to create id_fiche', E_USER_WARNING);
                }
                if (empty($page['tag'])) {
                    trigger_error('empty $page[\'tag\'] in EntryManager::getDataFromPage! ', E_USER_WARNING);
                }
            }

            // cas ou on ne trouve pas les valeurs id_fiche
            if (!isset($data['id_fiche'])) {
                $data['id_fiche'] = $page['tag'];
            }
            // TODO call this function only when necessary
            $this->appendDisplayData($data, $semantic);
        } elseif ($debug) {
            trigger_error('empty \'body\'  in EntryManager::getDataFromPage for page \''. $page['tag'] .'\'', E_USER_WARNING);
        }

        return $data;
    }

    /**
     * Return an array of fiches based on search parameters
     * @param array $params
     * @return mixed
     */
    public function search($params = []): array
    {
        // Merge les paramètres passé avec des paramètres par défaut
        $params = array_merge(
            [
                'queries' => '', // Sélection par clé-valeur
                'formsIds' => [], // Types de fiches (par ID de formulaire)
                'user' => '', // N'affiche que les fiches d'un utilisateur
                'keywords' => '', // Mots-clés pour la recherche fulltext
                'searchOperator' => 'OR', // Opérateur à appliquer aux mots-clés
                'minDate' => '' // Date minimale des fiches
            ],
            $params
        );

        // requete pour recuperer toutes les PageWiki etant des fiches bazar
        // TODO refactor to use the TripleStore service
        $requete_pages_wiki_bazar_fiches =
            'SELECT DISTINCT resource FROM ' . $this->dbService->prefixTable('triples') .
            'WHERE value = "fiche_bazar" AND property = "http://outils-reseaux.org/_vocabulary/type" ' .
            'ORDER BY resource ASC';

        $requete =
            'SELECT DISTINCT * FROM ' . $this->dbService->prefixTable('pages') .
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
        if (!empty($params['minDate'])) {
            $requete .= ' AND time >= "' . $params['minDate'] . '"';
        }

        // si une personne a ete precisee, on limite la recherche sur elle
        if (!empty($params['user'])) {
            $requete .= ' AND owner = _utf8\'' . mysqli_real_escape_string($this->wiki->dblink, $params['user']) . '\'';
        }

        $requete .= ' AND tag IN (' . $requete_pages_wiki_bazar_fiches . ')';

        $requeteSQL = '';

        //preparation de la requete pour trouver les mots cles
        if (trim($params['keywords']) != '' && $params['keywords'] != _t('BAZ_MOT_CLE')) {
            $this->dbService->query("SET sql_mode = 'NO_BACKSLASH_ESCAPES';");
            $search = str_replace(array('["', '"]'), '', json_encode(array(removeAccents($params['keywords']))));
            $recherche = explode(' ', $search);
            $nbmots = count($recherche);
            $requeteSQL .= ' AND (';
            for ($i = 0; $i < $nbmots; ++$i) {
                if ($i > 0) {
                    $requeteSQL .= ' OR ';
                }
                $requeteSQL .= ' body LIKE \'%' . $this->dbService->escape($recherche[$i]) . '%\'';
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

        foreach ($params['queries'] as $nom => $val) {
            if (!empty($nom) && !empty($val)) {
                $valcrit = explode(',', $val);
                if (is_array($valcrit) && count($valcrit) > 1) {
                    $requeteSQL .= ' AND (';
                    $first = true;
                    foreach ($valcrit as $critere) {
                        if (!$first) {
                            $requeteSQL .= ' ' . $params['searchOperator'] . ' ';
                        }

                        if (strcmp(substr($nom, 0, 5), 'liste') == 0) {
                            $requeteSQL .=
                                'body REGEXP \'"' . $nom . '":"' . $critere . '"\'';
                        } else {
                            $requeteSQL .=
                                'body REGEXP \'"' . $nom . '":("' . $critere .
                                '"|"[^"]*,' . $critere . '"|"' . $critere . ',[^"]*"|"[^"]*,'
                                . $critere . ',[^"]*")\'';
                        }

                        $first = false;
                    }
                    $requeteSQL .= ')';
                } else {
                    if (strcmp(substr($nom, 0, 5), 'liste') == 0) {
                        $requeteSQL .=
                            ' AND (body REGEXP \'"' . $nom . '":"' . $val . '"\')';
                    } else {
                        $requeteSQL .=
                            ' AND (body REGEXP \'"' . $nom . '":("' . $val .
                            '"|"[^"]*,' . $val . '"|"' . $val . ',[^"]*"|"[^"]*,'
                            . $val . ',[^"]*")\')';
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
                                '(body REGEXP \'"' . $nom . '":"[^"]*' . $critere .
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
                                '(body REGEXP \'"' . $nom . '":"' . $val . '"\')';
                        } else {
                            $joinrequeteSQL .=
                                '(body REGEXP \'"' . $nom . '":("' . $val .
                                '"|"[^"]*,' . $val . '"|"' . $val . ',[^"]*"|"[^"]*,'
                                . $val . ',[^"]*")\')';
                        }
                    }
                }
            }
            if ($requeteSQL != '') {
                $requeteSQL .= ' UNION ' . $requete . ' AND (' . $joinrequeteSQL . ')';
            } else {
                $requeteSQL .= ' AND (' . $joinrequeteSQL . ')';
            }
            $requete .= $requeteSQL;
        } elseif ($requeteSQL != '') {
            $requete .= $requeteSQL;
        }

        // debug
        if (isset($_GET['showreq'])) {
            echo '<hr><code style="width:100%;height:100px;">' . $requete . '</code><hr>';
        }

        // systeme de cache des recherches
        // TODO voir si ça sert à quelque chose
        $reqid = 'bazar-search-' . md5($requete);
        if (!isset($GLOBALS['_BAZAR_'][$reqid])) {
            $GLOBALS['_BAZAR_'][$reqid] = array();
            $results = $this->dbService->loadAll($requete);
            $debug = ($this->wiki->GetConfigValue('debug') == 'yes');
            foreach ($results as $page) {
                $data = $this->getDataFromPage($page, false, $debug);
                $GLOBALS['_BAZAR_'][$reqid][$data['id_fiche']] = $data;
            }
        }
        return $GLOBALS['_BAZAR_'][$reqid];
    }

    /**
     * Validate the fiche's data
     * @param $data
     * @throws Exception
     */
    public function validate($data)
    {
        if (!isset($data['antispam']) or !$data['antispam'] == 1) {
            throw new Exception(_t('BAZ_PROTECTION_ANTISPAM'));
        }

        // On teste le titre car ça peut bugguer sérieusement sans
        if (!isset($data['bf_titre'])) {
            throw new Exception(_t('BAZ_FICHE_NON_SAUVEE_PAS_DE_TITRE'));
        }

        // form metadata
        if (!isset($data['id_typeannonce'])) {
            throw new Exception(_t('BAZ_NO_FORMS_FOUND'));
        }
    }

    /**
     * Create a new fiche
     * @param $formId
     * @param $data
     * @param false $semantic
     * @param null $sourceUrl
     * @return array
     * @throws Exception
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
            $olduser = $this->userManager->getLoggedUser();
            $this->userManager->logout();

            // On s'identifie de facon a attribuer la propriete de la fiche a
            // l'utilisateur qui vient d etre cree
            $user = $this->userManager->getOneByName($GLOBALS['utilisateur_wikini']);
            $this->userManager->login($user);
        }

        $ignoreAcls = true;
        if ($this->params->has('bazarIgnoreAcls')) {
            $ignoreAcls = $this->params->get('bazarIgnoreAcls');
        }

        // get the sendmail and remove it before saving
        $sendmail = $this->removeSendmail($data);

        // on sauve les valeurs d'une fiche dans une PageWiki, retourne 0 si succès
        $saved = $this->pageManager->save(
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
            $this->userManager->logout();
            if (!empty($olduser)) {
                $this->userManager->login($olduser, 1);
            }
        }

        // if sendmail has referenced email fields, send an email to their adresses
        $this->sendMailToNotifiedEmails($sendmail, $data);

        if ($this->params->get('BAZ_ENVOI_MAIL_ADMIN')) {
            // Envoi d'un mail aux administrateurs
            $this->mailer->notifyAdmins($data, true);
        }

        return $data;
    }

    /**
     * Update an entry with the provided data
     * @param $tag
     * @param $data
     * @param false $semantic
     * @param false $replace If true, all the data will be provided (no merge with the previous data)
     * @return array
     * @throws Exception
     */
    public function update($tag, $data, $semantic = false, $replace = false)
    {
        if (!$this->aclService->hasAccess('write', $tag)) {
            throw new Exception(_t('BAZ_ERROR_EDIT_UNAUTHORIZED'));
        }

        // replace id_fiche with $tag to prevent errors before getOne
        $data['id_fiche'] = $tag;
        // if there are some restricted fields, load the previous data by bypassing the rights
        $previousData = $this->getOne($data['id_fiche'], false, null, false, true);
        $data['id_typeannonce'] = $previousData['id_typeannonce'];

        // not possible to init the formManager in the constructor because of circular reference problem
        $form = $this->wiki->services->get(FormManager::class)->getOne($data['id_typeannonce']);
        
        // replace the field values which are restricted at reading and writing
        $data = $this->assignRestrictedFields($data, $previousData, $form);

        if (!$replace) {
            // merge the field values which match to the actual form and which are not in $data
            $data = $this->mergeFields($previousData, $data, $form);
        }

        if ($semantic) {
            $data = $this->semanticTransformer->convertFromSemanticData($data['id_typeannonce'], $data);
        }

        $this->validate($data);

        $data = $this->formatDataBeforeSave($data);

        // get the sendmail and remove it before saving
        $sendmail = $this->removeSendmail($data);
        // on sauve les valeurs d'une fiche dans une PageWiki, pour garder l'historique
        $this->pageManager->save($data['id_fiche'], json_encode($data));

        // if sendmail has referenced email fields, send an email to their adresses
        $this->sendMailToNotifiedEmails($sendmail, $data);

        if ($this->params->get('BAZ_ENVOI_MAIL_ADMIN')) {
            // Envoi d'un mail aux administrateurs
            $this->mailer->notifyAdmins($data, false);
        }

        return $data;
    }

    /**
     * Replace the field values which are restricted at reading and writing. These values must be loaded to save them
     * without user modification.
     * As the fields are rectricted at reading, the right must be bypassed to load them.
     *
     * @param array $data the provided data to update
     * @param array $previousData the provided previousData to update
     * @param array $form the entry form
     * @return array the data with the restricted values added
     */
    protected function assignRestrictedFields(array $data, array $previousData, array $form)
    {
        // check if there are some restricted fields at writing
        $restrictedFields = [];
        foreach ($form['prepared'] as $field) {
            if ($field instanceof BazarField) {
                $propName = $field->getPropertyName();
                // be carefull : BazarField's objects, that do not save data (as ACL, Label, Hidden), do not have propertyName
                // see BazarField->formatValuesBeforeSave() for details
                // so do not save the previous data even if existing
                if (!empty($propName) && !$field->canEdit($data)) {
                    $restrictedFields[] = $propName;
                }
            }
        }

        if (!empty($restrictedFields)) {

            // get the value of the restricted fields in the previous data
            foreach ($restrictedFields as $propName) {
                if (isset($previousData[$propName])) {
                    $data[$propName] = $previousData[$propName] ;
                } elseif (isset($data[$propName])) {
                    // only for cases when a field is maliciously injected in $_POST (so in $data) and the key doesn't
                    // exist in $previousData
                    unset($data[$propName]);
                }
            }
        }
        return $data;
    }

    /**
     * Add the $previousData attributes which match the actual form and which are not in $data
     * @param array $previousData the data saved in the entry
     * @param array $form the entry form
     * @param array $data the provided data to update
     * @return array the data with the merged values
     * @throws Exception
     */
    protected function mergeFields(array $previousData, array $data, array $form)
    {
        foreach ($form['prepared'] as $field) {
            if ($field instanceof BazarField) {
                $propName = $field->getPropertyName();
                if (!empty($propName) && !isset($data[$propName]) && isset($previousData[$propName])) {
                    $data[$propName] = $previousData[$propName];
                }
            }
        }
        return $data;
    }

    /**
     * @param $entryId
     * @param $accepted
     * @throws Exception
     */
    public function publish($entryId, $accepted)
    {
        // not possible to init the Guard in the constructor because of circular reference problem
        if ($this->wiki->services->get(Guard::class)->isAllowed('valider_fiche')) {
            if ($accepted) {
                $this->dbService->query('UPDATE' . $this->dbService->prefixTable('fiche') . 'SET bf_statut_fiche=1 WHERE bf_id_fiche="' . $this->dbService->escape($entryId) . '"');
            } else {
                $this->dbService->query('UPDATE' . $this->dbService->prefixTable('fiche') . 'SET bf_statut_fiche=2 WHERE bf_id_fiche="' . $this->dbService->escape($entryId) . '"');
            }
            //TODO envoie mail annonceur
        }
    }

    /**
     * Delete a fiche
     * @param $tag
     * @throws Exception
     */
    public function delete($tag)
    {
        if (!$this->aclService->hasAccess('write', $tag)) {
            throw new Exception(_t('BAZ_ERROR_DELETE_UNAUTHORIZED'));
        }

        $fiche = $this->getOne($tag);

        // Si besoin, on supprime l'utilisateur associé
        if (isset($fiche['nomwiki'])) {
            $request = 'DELETE FROM ' . $this->dbService->prefixTable('users') . ' WHERE `name` = "' . $fiche['nomwiki'] . '"';
            $this->dbService->query($request);
        }

        $this->pageManager->deleteOrphaned($tag);
        $this->tripleStore->delete($tag, TripleStore::TYPE_URI, null, '', '');
        $this->tripleStore->delete($tag, TripleStore::SOURCE_URL_URI, null, '', '');
        $this->wiki->LogAdministrativeAction(
            $this->userManager->getLoggedUserName(),
            "Suppression de la page ->\"\"" . $tag . "\"\""
        );
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

    /**
     * prepare la requete d'insertion ou de MAJ de la fiche en supprimant
     * de la valeur POST les valeurs inadequates et en formattant les champs.
     * @param $data
     * @return array
     * @throws Exception
     */
    public function formatDataBeforeSave($data)
    {
        // not possible to init the formManager in the constructor because of circular reference problem
        $form = $this->wiki->services->get(FormManager::class)->getOne($data['id_typeannonce']);

        // If there is a title field, compute the entry's title
        foreach ($form['prepared'] as $field) {
            if ($field instanceof TitleField) {
                $data = array_merge($data, $field->formatValuesBeforeSave($data));
            }
        }

        // Entry ID
        if (!isset($data['id_fiche'])) {
            // Generate the ID from the title
            if (empty($data['id_fiche'] = genere_nom_wiki($data['bf_titre']))) {
                throw new Exception('$data[\'id_fiche\'] can not be generated from $data[\'bf_titre\'] !');
            }
            // TODO see if we can remove this
            $_POST['id_fiche'] = $data['id_fiche'];
        } elseif (empty($data['id_fiche'])) {
            throw new Exception('$data[\'id_fiche\'] is set but with empty value !');
        }

        $data['id_typeannonce'] = isset($data['id_typeannonce']) ? $data['id_typeannonce'] : $_REQUEST['id_typeannonce'];

        // Get creation date if it exists, initialize it otherwise
        $result = $this->dbService->loadSingle('SELECT MIN(time) as firsttime FROM ' . $this->dbService->prefixTable('pages') . "WHERE tag='" . $data['id_fiche'] . "'");
        $data['date_creation_fiche'] = $result['firsttime'] ? $result['firsttime'] : date('Y-m-d H:i:s', time());

        // Entry status
        if ($this->wiki->UserIsAdmin()) {
            $data['statut_fiche'] = '1';
        } else {
            $data['statut_fiche'] = $this->params->get('BAZ_ETAT_VALIDATION');
        }

        foreach ($form['prepared'] as $bazarField) {
            if ($bazarField instanceof BazarField) {
                $tab = $bazarField->formatValuesBeforeSave($data);
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
        // $data['id_fiche'] can not be empty
        if (empty($data['id_fiche'])) {
            throw new Exception('$data[\'id_fiche\'] is empty !');
        }

        $data['date_maj_fiche'] = date('Y-m-d H:i:s', time());

        // on enleve les champs hidden pas necessaires a la fiche
        unset($data['valider']);
        unset($data['MAX_FILE_SIZE']);
        unset($data['antispam']);
        unset($data['mot_de_passe_wikini']);
        unset($data['mot_de_passe_repete_wikini']);
        unset($data['html_data']);
        unset($data['url']);

        // on nettoie le champ owner qui n'est pas sauvegardé (champ owner de la page)
        if (isset($data['owner'])) {
            unset($data['owner']);
        }

        // on encode en utf-8 pour reussir a encoder en json
        if (YW_CHARSET != 'UTF-8') {
            $data = array_map('utf8_encode', $data);
        }

        return $data;
    }

    /**
     * Append data needed for display
     * TODO move this to a class dedicated to display
     * @param $fiche
     * @param bool $semantic
     * @param string $correspondance
     * @throws Exception
     */
    public function appendDisplayData(&$fiche, $semantic = false, $correspondance = '')
    {
        // champs correspondants
        if (!empty($correspondance)) {
            $tabcorrespondances = getMultipleParameters($correspondance, ',', '=');
            if ($tabcorrespondances['fail'] != 1) {
                foreach ($tabcorrespondances as $key => $data) {
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

        // owner
        $fiche['owner'] = $this->pageManager->getOwner($fiche['id_fiche']);

        // Fiche URL
        $exturl = $this->wiki->GetParameter('url');
        if (isset($exturl)) {
            // WIP concernant l'action bazarlisteexterne
            $arr = explode('/wakka.php', $exturl, 2);
            $exturl = $arr[0];
            $fiche['url'] = $exturl . '/wakka.php?wiki=' . $fiche['id_fiche'];
        } else {
            $fiche['url'] = $this->wiki->href('', $fiche['id_fiche']);
        }

        // Données sémantiques
        if ($semantic) {
            // not possible to init the formManager in the constructor because of circular reference problem
            $form = $this->wiki->services->get(FormManager::class)->getOne($fiche['id_typeannonce']);
            $fiche['semantic'] = $this->semanticTransformer->convertToSemanticData($form, $fiche);
        }
    }

    private function removeSendmail(array &$data): ?string
    {
        $sendmail = null;
        if (isset($data['sendmail'])) {
            $sendmail = $data['sendmail'];
            unset($data['sendmail']);
        }
        return $sendmail;
    }

    private function sendMailToNotifiedEmails(?string $sendmail, ?array $data)
    {
        if ($sendmail) {
            $emailsFieldnames = array_unique(explode(',', $sendmail));
            foreach ($emailsFieldnames as $emailFieldName) {
                if (!empty($data[$emailFieldName])) {
                    $this->mailer->notifyEmail($data[$emailFieldName], $data);
                }
            }
        }
    }
}
