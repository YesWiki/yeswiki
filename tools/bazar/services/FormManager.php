<?php

namespace YesWiki\Bazar\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Bazar\Field\BazarField;
use YesWiki\Bazar\Field\CheckboxEntryField;
use YesWiki\Bazar\Field\EnumField;
use YesWiki\Bazar\Field\SelectEntryField;
use YesWiki\Core\Service\DbService;
use YesWiki\Security\Controller\SecurityController;
use YesWiki\Wiki;

class FormManager
{
    protected $wiki;
    protected $dbService;
    protected $entryManager;
    protected $securityController;
    protected $fieldFactory;
    protected $params;

    protected $cachedForms;

    public function __construct(
        Wiki $wiki,
        DbService $dbService,
        EntryManager $entryManager,
        FieldFactory $fieldFactory,
        ParameterBagInterface $params,
        SecurityController $securityController
    ) {
        $this->wiki = $wiki;
        $this->dbService = $dbService;
        $this->entryManager = $entryManager;
        $this->fieldFactory = $fieldFactory;
        $this->params = $params;

        $this->cachedForms = [];
        $this->securityController = $securityController;
    }

    public function getOne($formId): ?array
    {
        if (isset($this->cachedForms[$formId])) {
            return $this->cachedForms[$formId];
        }

        $form = $this->dbService->loadSingle('SELECT * FROM ' . $this->dbService->prefixTable('nature') . 'WHERE bn_id_nature=\'' . $formId . '\'');

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
            if (empty($this->cachedForms[$formId])) {
                $this->cachedForms[$formId] = $this->getOne($formId);
            }
            $results[$formId] = $this->cachedForms[$formId];
        }

        return $results;
    }

    // TODO Pass a Form object instead of a raw array
    public function create($data)
    {
        if ($this->securityController->isWikiHibernated()) {
            throw new \Exception(_t('WIKI_IN_HIBERNATION'));
        }
        // If ID is not set or if it is already used, find a new ID
        if (!$data['bn_id_nature'] || $this->getOne($data['bn_id_nature'])) {
            $data['bn_id_nature'] = $this->findNewId();
        }

        return $this->dbService->query('INSERT INTO ' . $this->dbService->prefixTable('nature')
            . '(`bn_id_nature` ,`bn_ce_i18n` ,`bn_label_nature` ,`bn_template` ,`bn_description` ,`bn_sem_context` ,`bn_sem_type` ,`bn_sem_use_template` ,`bn_condition`)'
            . ' VALUES (' . $data['bn_id_nature'] . ', "fr-FR", "'
            . addslashes(_convert($data['bn_label_nature'], YW_CHARSET, true)) . '","'
            . addslashes(_convert($data['bn_template'], YW_CHARSET, true)) . '", "'
            . addslashes(_convert($data['bn_description'], YW_CHARSET, true)) . '", "'
            . addslashes(_convert($data['bn_sem_context'], YW_CHARSET, true)) . '", "'
            . addslashes(_convert($data['bn_sem_type'], YW_CHARSET, true)) . '", '
            . (isset($data['bn_sem_use_template']) ? '1' : '0') . ', "'
            . addslashes(_convert($data['bn_condition'], YW_CHARSET, true)) . '")');
    }

    public function update($data)
    {
        if ($this->securityController->isWikiHibernated()) {
            throw new \Exception(_t('WIKI_IN_HIBERNATION'));
        }
        return $this->dbService->query('UPDATE' . $this->dbService->prefixTable('nature') . 'SET '
            . '`bn_label_nature`="' . addslashes(_convert($data['bn_label_nature'], YW_CHARSET, true)) . '" ,'
            . '`bn_template`="' . addslashes(_convert($data['bn_template'], YW_CHARSET, true)) . '" ,'
            . '`bn_description`="' . addslashes(_convert($data['bn_description'], YW_CHARSET, true)) . '" ,'
            . '`bn_sem_context`="' . addslashes(_convert($data['bn_sem_context'], YW_CHARSET, true)) . '" ,'
            . '`bn_sem_type`="' . addslashes(_convert($data['bn_sem_type'], YW_CHARSET, true)) . '" ,'
            . '`bn_sem_use_template`=' . (isset($data['bn_sem_use_template']) ? '1' : '0') . ' ,'
            . '`bn_condition`="' . addslashes(_convert($data['bn_condition'], YW_CHARSET, true)) . '"'
            . ' WHERE `bn_id_nature`=' . $data['bn_id_nature']);
    }

    public function clone($id)
    {
        $data = $this->getOne($id);
        if (!empty($data)) {
            unset($data['bn_id_nature']);
            $data['bn_label_nature'] = $data['bn_label_nature'].' ('._t('BAZ_DUPLICATE').')';
            return $this->create($data);
        } else {
            // raise error?
            return false;
        }
    }

    public function delete($id)
    {
        if ($this->securityController->isWikiHibernated()) {
            throw new \Exception(_t('WIKI_IN_HIBERNATION'));
        }

        // tests of if $formId is int
        if (strval(intval($id)) != strval($id)) {
            return null ;
        }

        $this->clear($id);
        return $this->dbService->query('DELETE FROM ' . $this->dbService->prefixTable('nature') . 'WHERE bn_id_nature=' . $id);
    }

    public function clear($id)
    {
        if ($this->securityController->isWikiHibernated()) {
            throw new \Exception(_t('WIKI_IN_HIBERNATION'));
        }
        $this->dbService->query(
            'DELETE FROM' . $this->dbService->prefixTable('acls') .
            'WHERE page_tag IN (SELECT tag FROM ' . $this->dbService->prefixTable('pages') .
            'WHERE tag IN (SELECT resource FROM ' . $this->dbService->prefixTable('triples') .
            'WHERE property="http://outils-reseaux.org/_vocabulary/type" AND value="fiche_bazar") AND body LIKE \'%"id_typeannonce":"' . $id . '"%\' );'
        );

        // TODO use PageManager
        $this->dbService->query(
            'DELETE FROM' . $this->dbService->prefixTable('pages') .
            'WHERE tag IN (SELECT resource FROM ' . $this->dbService->prefixTable('triples') .
            'WHERE property="http://outils-reseaux.org/_vocabulary/type" AND value="fiche_bazar") AND body LIKE \'%"id_typeannonce":"' . $id . '"%\';'
        );

        // TODO use TripleStore
        $this->dbService->query(
            'DELETE FROM' . $this->dbService->prefixTable('triples') .
            'WHERE resource NOT IN (SELECT tag FROM ' . $this->dbService->prefixTable('pages') .
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
     * @param string  Template du formulaire
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
                        for ($i = 0; $i < 16; $i++) {
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

            if ($classField) {
                $prepared[$i] = $classField;
            } elseif (function_exists($field[0])) {
                $functionName = $field[0];
                $field[0] = 'old'; // field name
                $field['functionName'] = $functionName ;
                $classField = $this->fieldFactory->create($field);
                if ($classField) {
                    $prepared[$i] = $classField;
                }
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
                    if ($field instanceof BazarField) {
                        $fieldPropName = $field->getPropertyName();
                        $fieldType = $field->getType();
                    }

                    if ($fieldPropName) {
                        if ($field instanceof EnumField) {
                            if ($field instanceof SelectEntryField || $field instanceof CheckboxEntryField) {
                                // listefiche ou checkboxfiche
                                $facetteValue[$fieldPropName]['type'] = 'fiche';
                            } else {
                                $facetteValue[$fieldPropName]['type'] = 'liste';
                            }

                            $facetteValue[$fieldPropName]['source'] = $key;

                            $tabval = explode(',', $value);
                            foreach ($tabval as $tval) {
                                if (isset($facetteValue[$fieldPropName][$tval])) {
                                    ++$facetteValue[$fieldPropName][$tval];
                                } else {
                                    $facetteValue[$fieldPropName][$tval] = 1;
                                }
                            }
                        } elseif (!$onlyLists) {
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
        if (count($id)===1 && $id[0]==='all') {
            return array_filter($fields, function ($field) use ($id) {
                if ($field instanceof EnumField) {
                    return true;
                }
            });
        } else {
            return array_filter($fields, function ($field) use ($id) {
                if ($field instanceof BazarField) {
                    return $id[0] === 'all' || in_array($field->getPropertyName(), $id);
                }
            });
        }
    }

    /**
     * put a form form External Wiki in cache
     * @param int $localFormId
     * @return bool
     */
    public function putInCacheFromExternalBazarService(int $localFormId): bool
    {
        if (empty($localFormId) || !empty($this->getOne($localFormId))) {
            // error
            return false;
        }
        $form = $this->wiki->services->get(ExternalBazarService::class)->getTmpForm();
        if (empty($form)) {
            return false;
        } else {
            $this->cachedForms[$localFormId] = $form;
            return true;
        }
    }

    /**
     * return field from field name or property name
     * @param null|string $name
     * @param null|string $formId
     * @return null|BazarField
     */
    public function findFieldFromNameOrPropertyName(?string $name, ?string $formId): ?BazarField
    {
        // check params
        if (empty($name) || empty($formId) || strval(intval($formId)) != strval($formId)) {
            return null;
        }

        $form = $this->getOne($formId);
        if (empty($form) || !is_array($form['prepared'])) {
            return null;
        }

        foreach ($form['prepared'] as $field) {
            if (in_array($name, [$field->getName(),$field->getPropertyName()])) {
                return $field;
            }
        }
        return null;
    }
}
