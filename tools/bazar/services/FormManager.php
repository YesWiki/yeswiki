<?php

namespace YesWiki\Bazar\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Bazar\Field\BazarField;
use YesWiki\Bazar\Field\EnumField;
use YesWiki\Core\Service\DbService;
use YesWiki\Wiki;

class FormManager
{
    protected $wiki;
    protected $dbService;
    protected $entryManager;
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

    public function __construct(Wiki $wiki, DbService $dbService, EntryManager $entryManager, FieldFactory $fieldFactory, ParameterBagInterface $params)
    {
        $this->wiki = $wiki;
        $this->dbService = $dbService;
        $this->entryManager = $entryManager;
        $this->fieldFactory = $fieldFactory;
        $this->params = $params;

        $this->cachedForms = [];
    }

    public function getOne($formId): ?array
    {
        if (isset($this->cachedForms[$formId])) {
            return $this->cachedForms[$formId];
        }

        $form = $this->dbService->loadSingle('SELECT * FROM '.$this->dbService->prefixTable('nature').'WHERE bn_id_nature='.$formId);

        if (!$form) {
            return null;
        }

        foreach ($form as $key => $value) {
            $form[$key] = _convert($value, 'ISO-8859-15');
        }

        $form['template'] = $this->parseTemplate($form['bn_template']);
        $form['prepared'] = $this->prepareData($form);

        $this->cachedForms[$formId] = $form;

        return $form;
    }

    public function getAll(): array
    {
        $forms = $this->dbService->loadAll('SELECT * FROM ' . $this->dbService->prefixTable('nature') . 'ORDER BY bn_label_nature ASC');

        foreach ($forms as $form) {
            $formId = $form['bn_id_nature'];
            $this->cachedForms[$formId] = $this->getOne($formId);
        }
        // TODO verify this method : each form is written with the same key in the array

        return $this->cachedForms;
    }

    public function getMany($formsIds): array
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
        if (!$data['bn_id_nature'] || $this->getOne($data['bn_id_nature'])) {
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

        return $this->dbService->query('DELETE FROM '.$this->dbService->prefixTable('nature').'WHERE bn_id_nature='. $id);
    }

    public function clear($id)
    {
        $this->dbService->query(
            'DELETE FROM'. $this->dbService->prefixTable('acls').
            'WHERE page_tag IN (SELECT tag FROM '.$this->dbService->prefixTable('pages').
            'WHERE tag IN (SELECT resource FROM '.$this->dbService->prefixTable('triples').
            'WHERE property="http://outils-reseaux.org/_vocabulary/type" AND value="fiche_bazar") AND body LIKE \'%"id_typeannonce":"'.$id.'"%\' );'
        );

        // TODO use PageManager
        $this->dbService->query(
            'DELETE FROM'.$this->dbService->prefixTable('pages').
            'WHERE tag IN (SELECT resource FROM '.$this->dbService->prefixTable('triples').
            'WHERE property="http://outils-reseaux.org/_vocabulary/type" AND value="fiche_bazar") AND body LIKE \'%"id_typeannonce":"'.$id.'"%\';'
        );

        // TODO use TripleStore
        $this->dbService->query(
            'DELETE FROM'.$this->dbService->prefixTable('triples').
            'WHERE resource NOT IN (SELECT tag FROM '.$this->dbService->prefixTable('pages').
            'WHERE 1) AND property="http://outils-reseaux.org/_vocabulary/type" AND value="fiche_bazar";'
        );
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
                if (true /*function_exists($tablignechampsformulaire[self::FIELD_TYPE])*/) {
                    if (count($tablignechampsformulaire) > 3) {
                        $tableau_template[$nblignes] = $tablignechampsformulaire;
                        for ($i=0; $i < 16; $i++) {
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
        
            // default values for read acl
            if (empty(trim($field[self::FIELD_READ_ACCESS]))) {
                $field[self::FIELD_READ_ACCESS] = '*' ;
            }
            // default values for write  acl
            if (empty(trim($field[self::FIELD_WRITE_ACCESS]))) {
                $field[self::FIELD_WRITE_ACCESS] = '*' ;
            }

            $classField = $this->fieldFactory->create($field);

            if ($classField) {
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

            // values for read acl
            $prepared[$i]['read_acl'] = $field[self::FIELD_READ_ACCESS];

            // values for write acl
            $prepared[$i]['write_acl'] = $field[self::FIELD_WRITE_ACCESS];

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

    public function scanAllFacettable($entries, $groups = ['all'], $onlyLists = false)
    {
        $facetteValue = $fields = [];

        foreach ($entries as $entry) {
            $form = $this->getOne($entry['id_typeannonce']);

            // on filtre pour n'avoir que les liste, checkbox, listefiche ou checkboxfiche
            $fields[$entry['id_typeannonce']] = isset($fields[$entry['id_typeannonce']])
                ? $fields[$entry['id_typeannonce']]
                : $this->filterFieldsByPropertyName($form['prepared'], $groups);

            foreach ($entry as $key => $value) {
                $facetteasked = (isset($groups[0]) && $groups[0] == 'all') || in_array($key, $groups);

                if (!empty($value) and is_array($fields[$entry['id_typeannonce']]) && $facetteasked) {
                    $filteredFields = $this->filterFieldsByPropertyName($fields[$entry['id_typeannonce']], [$key]);
                    $field = array_pop($filteredFields);

                    $fieldPropName = null;
                    if( $field instanceof BazarField ) {
                        $fieldPropName = $field->getPropertyName();
                        $fieldType = $field->getType();
                    } else if ( is_array($field)) {
                        $fieldPropName = $field['id'];
                        $fieldType = $field['type'];
                    }

                    if ($fieldPropName) {
                        $islistforeign = (strpos($fieldPropName, 'listefiche')===0) || (strpos($fieldPropName, 'checkboxfiche')===0);
                        $islist = in_array($fieldType, array('checkbox', 'select', 'scope', 'radio', 'liste')) && !$islistforeign;
                        $istext = (!in_array($fieldType, array('checkbox', 'select', 'scope', 'radio', 'liste', 'checkboxfiche', 'listefiche')));

                        if ($islistforeign) {
                            // listefiche ou checkboxfiche
                            $facetteValue[$fieldPropName]['type'] = 'fiche';
                            $facetteValue[$fieldPropName]['source'] = $key;
                            $tabval = explode(',', $value);
                            foreach ($tabval as $tval) {
                                if (isset($facetteValue[$fieldPropName][$tval])) {
                                    ++$facetteValue[$fieldPropName][$tval];
                                } else {
                                    $facetteValue[$fieldPropName][$tval] = 1;
                                }
                            }
                        } elseif ($islist) {
                            // liste ou checkbox
                            $facetteValue[$fieldPropName]['type'] = 'liste';
                            $facetteValue[$fieldPropName]['source'] = $key;
                            $tabval = explode(',', $value);
                            foreach ($tabval as $tval) {
                                if (isset($facetteValue[$fieldPropName][$tval])) {
                                    ++$facetteValue[$fieldPropName][$tval];
                                } else {
                                    $facetteValue[$fieldPropName][$tval] = 1;
                                }
                            }
                        } elseif ($istext and !$onlyLists) {
                            // texte
                            $facetteValue[$key]['type'] = 'form';
                            $facetteValue[$key]['source'] = $key;
                            if (isset($facetteValue[$key][$value])) {
                                ++$facetteValue[$key][$value];
                            } else {
                                $facetteValue[$key][$value] = 1;
                            }
                        }
                    }
                }
            }
        }
        return $facetteValue;
    }

    /*
     * Filter an array of fields by their potential entry ID
     */
    private function filterFieldsByPropertyName(array $fields, array $id)
    {
        if( count($id)===1 && $id[0]==='all') {
            return array_filter($fields, function($field) use ($id) {
                if( $field instanceof EnumField ) {
                    return true;
                }
            });
        } else {
            return array_filter($fields, function($field) use ($id) {
                if( $field instanceof BazarField ) {
                    return $id[0] === 'all' || in_array($field->getPropertyName(), $id);
                } elseif( is_array($field) && isset($field['id']) ) {
                    return $id[0] === 'all' || in_array($field['id'], $id);
                }
            });
        }
    }
}
