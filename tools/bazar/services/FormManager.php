<?php

namespace YesWiki\Bazar\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Core\Service\DbService;
use YesWiki\Wiki;

class FormManager
{
    protected $wiki;
    protected $dbService;
    protected $ficheManager;
    protected $fieldFactory;
    protected $params;

    protected $cachedForms;

    private const FIELD_TYPE = 0;
    private const FIELD_ID = 1;
    private const FIELD_LABEL = 2;
    private const FIELD_SIZE = 3;
    private const FIELD_MAX_LENGTH = 4;
    private const FIELD_DEFAULT = 5;
    private const FIELD_PATTERN = 6;
    private const FIELD_SUB_TYPE = 7;
    private const FIELD_REQUIRED = 8;
    private const FIELD_SEARCHABLE = 9;
    private const FIELD_HELP = 10;
    private const FIELD_READ_ACCESS = 11;
    private const FIELD_WRITE_ACCESS = 12;
    private const FIELD_KEYWORDS = 13;
    private const FIELD_SEMANTIC = 14;
    private const FIELD_QUERIES = 15;

    public function __construct(Wiki $wiki, DbService $dbService, FicheManager $ficheManager, FieldFactory $fieldFactory, ParameterBagInterface $params)
    {
        $this->wiki = $wiki;
        $this->dbService = $dbService;
        $this->ficheManager = $ficheManager;
        $this->fieldFactory = $fieldFactory;
        $this->params = $params;

        $this->cachedForms = [];
    }

    public function getOne($formId)
    {
        if (isset($this->cachedForms[$formId])) {
            return $this->cachedForms[$formId];
        }

        $form = $this->dbService->loadSingle('SELECT * FROM '.$this->dbService->prefixTable('nature').'WHERE bn_id_nature='.$formId);

        if (!$form) {
            return false;
        }

        foreach ($form as $key => $value) {
            $form[$key] = _convert($value, 'ISO-8859-15');
        }

        $form['template'] = $this->parseTemplate($form['bn_template']);
        $form['prepared'] = $this->prepareData($form);

        $this->cachedForms[$formId] = $form;

        return $form;
    }

    public function getAll()
    {
        $forms = $this->dbService->loadAll('SELECT * FROM '.$this->dbService->prefixTable('nature') . 'ORDER BY bn_label_nature ASC');

        foreach ($forms as $form) {
            $formId = $form['bn_id_nature'];
            $this->cachedForms[$formId] = $this->getOne($formId);
        }

        return count($this->cachedForms) > 0 ? $this->cachedForms : null;
    }

    public function getMany($formsIds)
    {
        $results = [];

        foreach ($formsIds as $formId) {
            if (!$this->cachedForms[$formId]) {
                $this->cachedForms[$formId] = $this->getOne($formId);
            }
            $results[$formId] = $this->cachedForms[$formId];
        }

        return $results;
    }

    // TODO Pass a Form object instead of a raw array
    public function create($data)
    {
        // If ID is not set or if it is already used, find a new ID
        if( !$data['bn_id_nature'] || $this->getOne($data['bn_id_nature']) ) {
            $data['bn_id_nature'] = $this->findNewId();
        }

        return $this->dbService->query('INSERT INTO '. $this->dbService->prefixTable('nature')
            .'(`bn_id_nature` ,`bn_ce_i18n` ,`bn_label_nature` ,`bn_template` ,`bn_description` ,`bn_sem_context` ,`bn_sem_type` ,`bn_sem_use_template` ,`bn_condition`)'
            .' VALUES ('.$data['bn_id_nature'].', "fr-FR", "'
            .addslashes(_convert($data['bn_label_nature'], YW_CHARSET, true)).'","'
            .addslashes(_convert($data['bn_template'], YW_CHARSET, true)).'", "'
            .addslashes(_convert($data['bn_description'], YW_CHARSET, true)).'", "'
            .addslashes(_convert($data['bn_sem_context'], YW_CHARSET, true)).'", "'
            .addslashes(_convert($data['bn_sem_type'], YW_CHARSET, true)).'", '
            .(isset($data['bn_sem_use_template']) ? '1' : '0').', "'
            .addslashes(_convert($data['bn_condition'], YW_CHARSET, true)).'")');
    }

    public function update($data)
    {
        return $this->dbService->query('UPDATE'.$this->dbService->prefixTable('nature').'SET '
            .'`bn_label_nature`="'.addslashes(_convert($data['bn_label_nature'], YW_CHARSET, true)).'" ,'
            .'`bn_template`="'.addslashes(_convert($data['bn_template'], YW_CHARSET, true)).'" ,'
            .'`bn_description`="'.addslashes(_convert($data['bn_description'], YW_CHARSET, true)).'" ,'
            .'`bn_sem_context`="'.addslashes(_convert($data['bn_sem_context'], YW_CHARSET, true)).'" ,'
            .'`bn_sem_type`="'.addslashes(_convert($data['bn_sem_type'], YW_CHARSET, true)).'" ,'
            .'`bn_sem_use_template`='. (isset($data['bn_sem_use_template']) ? '1' : '0') .' ,'
            .'`bn_condition`="'.addslashes(_convert($data['bn_condition'], YW_CHARSET, true)).'"'
            .' WHERE `bn_id_nature`='.$data['bn_id_nature']);
    }

    public function delete($id)
    {
        //TODO : suppression des fiches associees au formulaire

        return $this->dbService->query('DELETE FROM '.$this->dbService->prefixTable('nature').'WHERE bn_id_nature='. $id );
    }

    public function clear($id)
    {
        $this->dbService->query(
            'DELETE FROM'. $this->dbService->prefixTable('acls').
            'WHERE page_tag IN (SELECT tag FROM '.$this->dbService->prefixTable('pages').
            'WHERE tag IN (SELECT resource FROM '.$this->dbService->prefixTable('triples').
            'WHERE property="http://outils-reseaux.org/_vocabulary/type" AND value="fiche_bazar") AND body LIKE \'%"id_typeannonce":"'.$id.'"%\' );');

        // TODO use PageManager
        $this->dbService->query(
            'DELETE FROM'.$this->dbService->prefixTable('pages').
            'WHERE tag IN (SELECT resource FROM '.$this->dbService->prefixTable('triples').
            'WHERE property="http://outils-reseaux.org/_vocabulary/type" AND value="fiche_bazar") AND body LIKE \'%"id_typeannonce":"'.$id.'"%\';');

        // TODO use TripleStore
        $this->dbService->query(
            'DELETE FROM'.$this->dbService->prefixTable('triples').
            'WHERE resource NOT IN (SELECT tag FROM '.$this->dbService->prefixTable('pages').
            'WHERE 1) AND property="http://outils-reseaux.org/_vocabulary/type" AND value="fiche_bazar";');
    }

    public function findNewId()
    {
        $result = $this->dbService->loadSingle('SELECT MAX(bn_id_nature) AS maxi FROM ' . $this->dbService->prefixTable('nature') . 'where bn_id_nature < 1000');

        if (!$result['maxi']) {
            return 1;
        }
        if ($result['maxi'] < 999) {
            return $result['maxi'] + 1;
        }

        $result = $this->dbService->loadSingle('SELECT MAX(bn_id_nature) AS maxi FROM' . $this->dbService->prefixTable('nature') . ' where bn_id_nature > 10000');

        if (!$result['maxi']) {
            return 10001;
        } else {
            return $result['maxi'] + 1;
        }
    }

    /**
     * Découpe le template et renvoie un tableau structuré
     *
     * @param  string  Template du formulaire
     * @return  mixed   Le tableau des elements du formulaire et options pour l'element liste
     */
    public function parseTemplate($raw)
    {
        //Parcours du template, pour mettre les champs du formulaire avec leurs valeurs specifiques
        $tableau_template = array();
        $nblignes = 0;

        //on traite le template ligne par ligne
        $chaine = explode("\n", $raw);
        foreach ($chaine as $ligne) {
            $ligne = trim($ligne);
            // on ignore les lignes vides ou commencant par # (commentaire)
            if (!empty($ligne) && !(strrpos($ligne, '#', -strlen($ligne)) !== false)) {
                //on decoupe chaque ligne par le separateur *** (c'est historique)
                $tablignechampsformulaire = array_map("trim", explode("***", $ligne));

                // TODO find another way to check that the field is valid
                if ( true /*function_exists($tablignechampsformulaire[self::FIELD_TYPE])*/) {
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

    public function prepareData($form)
    {
        $i = 0;
        $prepared = $result = [];

        $form['template'] = _convert($form['template'], 'ISO-8859-15');

        foreach ($form['template'] as $field) {

            $classField = $this->fieldFactory->create($field);

            if( $classField ) {
                $prepared[$i] = $classField;
                $i++;
                continue;
            }

            /*
             * DEFAULT VALUES
             */

            // champs obligatoire
            if ($field[self::FIELD_REQUIRED]==1) {
                $prepared[$i]['required'] = true;
            } else {
                $prepared[$i]['required'] = false;
            }

            // texte d'invitation à la saisie
            $prepared[$i]['label'] = $field[self::FIELD_LABEL];

            // attributs html du champs
            $prepared[$i]['attributes'] = '';

            // valeurs associées
            $prepared[$i]['values'] = '';

            // texte d'aide
            $prepared[$i]['helper'] = $field[self::FIELD_HELP];

            /*
             * TYPES-SPECIFIC VALUES
             */

            switch($field[self::FIELD_TYPE]) {
                case 'radio':
                case 'liste':
                case 'checkbox':
                case 'listefiche':
                case 'checkboxfiche':
                    $prepared[$i]['id'] = $field[self::FIELD_TYPE] . $field[self::FIELD_ID] . $field[6];
                    $prepared[$i]['values'] = [];
                    // type de champ
                    if (in_array($field[self::FIELD_TYPE], array('listefiche', 'liste'))) {
                        $prepared[$i]['type'] = 'select';
                    } elseif (in_array($field[self::FIELD_TYPE], array('checkboxfiche', 'checkbox'))) {
                        $prepared[$i]['type'] = 'checkbox';
                    } else {
                        $prepared[$i]['type'] = 'radio';
                    }

                    // valeurs associées
                    if (in_array($field[self::FIELD_TYPE], array('radio', 'liste', 'checkbox'))) {
                        // TRANSFERED INTO ListListField
                        $prepared[$i]['values'] = baz_valeurs_liste($field[self::FIELD_ID]);
                        $prepared[$i]['values']['id'] = $field[self::FIELD_ID];
                    } else {
                        // TRANSFERED INTO EntryListField
                        $tabquery = array();
                        if (!empty($field[self::FIELD_QUERIES])) {
                            $tableau = array();
                            $tab = explode('|', $field[self::FIELD_QUERIES]);
                            //découpe la requete autour des |
                            foreach ($tab as $req) {
                                $tabdecoup = explode('=', $req, 2);
                                $tableau[$tabdecoup[0]] = isset($tabdecoup[1]) ? trim($tabdecoup[1]) : '';
                            }
                            $tabquery = array_merge($tabquery, $tableau);
                        } else {
                            $tabquery = '';
                        }
                        $hash = md5($field[self::FIELD_ID] . serialize($tabquery));
                        if (!isset($result[$hash])) {
                            $result[$hash] = $this->ficheManager->search([
                                'queries' => $tabquery,
                                'formsIds' => $field[self::FIELD_ID],
                                'keywords' => (!empty($field[self::FIELD_KEYWORDS])) ? $field[self::FIELD_KEYWORDS] : ''
                            ]);
                        }
                        $prepared[$i]['values']['titre_liste'] = $field[self::FIELD_LABEL];
                        foreach ($result[$hash] as $values) {
                            $prepared[$i]['values']['label'][$values['id_fiche']] = $values['bf_titre'];
                        }
                    }
                    break;

                case 'champs_cache':
                case 'titre':
                    if ($field[self::FIELD_TYPE] == 'titre') {
                        $prepared[$i]['id'] = 'bf_titre';
                        $prepared[$i]['values'] = $field[self::FIELD_ID];
                    } else {
                        $prepared[$i]['id'] = $field[self::FIELD_ID];
                        $prepared[$i]['values'] = $field[self::FIELD_LABEL];
                    }
                    $prepared[$i]['type'] = 'hidden';
                    $prepared[$i]['label'] = '';
                    $prepared[$i]['required'] = '';
                    $prepared[$i]['helper'] = '';
                    break;

                case 'carte_google':
                    $prepared[$i]['id'] = '';
                    $prepared[$i]['type'] = 'map';
                    $prepared[$i]['label'] = '';
                    $prepared[$i]['helper'] = '';
                    break;

                case 'inscriptionliste':
                    $prepared[$i]['id'] = str_replace(['@', '.'], ['', ''], $field[self::FIELD_ID]);
                    $prepared[$i]['type'] = 'listsubscribe';
                    $prepared[$i]['required'] = '';
                    $prepared[$i]['values'] = $field[self::FIELD_ID];
                    $prepared[$i]['helper'] = '';
                    break;
            }

            // traitement sémantique
            // TODO move to BazarField
            if (!empty($field[self::FIELD_SEMANTIC])) {
                $prepared[$i]['sem_type'] = strpos($field[self::FIELD_SEMANTIC], ',')
                    ? array_map(function ($str) {
                        return trim($str);
                    }, explode(',', $field[self::FIELD_SEMANTIC]))
                    : $field[self::FIELD_SEMANTIC];
            }

            $i++;
        }
        return $prepared;
    }
}
