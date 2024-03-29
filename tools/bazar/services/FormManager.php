<?php

namespace YesWiki\Bazar\Service;

use Attach;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Bazar\Field\BazarField;
use YesWiki\Bazar\Field\EnumField;
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
    protected $isAvailableOnlyOneEntryOption;
    protected $isAvailableOnlyOneEntryMessage;

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
        $this->isAvailableOnlyOneEntryOption = null;
        $this->isAvailableOnlyOneEntryMessage = null;
    }
    
    protected function convert_with_special_parameters($template, $id_nature) {
        $template = $this->dbService->escape(_convert($template, YW_CHARSET, true));
        $template_list = $this->parseTemplate($template);
        $modify = false;
        for ($temp_index = 0; $temp_index < count($template_list); $temp_index++) {
            if ($template_list[$temp_index][0] == 'image') {
                $modify = true;
                $image_comp = $template_list[$temp_index];
                $default_image_filename = "./files/dfltimg_{$id_nature}_{$image_comp[1]}.jpg";
                $default_image = explode('|', $image_comp[8]);
                if (count($default_image)==2) {
                    $image_comp[8] = $default_image[0];
                    $imgext=explode('image/', explode(';', $default_image[1])[0])[1];
                    $tmpFile = tempnam('cache', 'dfltimg');
                    unlink($tmpFile);
                    $tempFile = $tmpFile.'.'.$imgext;
                    try {
                        $ifp = fopen($tempFile, "wb" );
                        fwrite( $ifp, base64_decode(explode(',',$default_image[1])[1]));
                        fclose( $ifp );
                        if (!class_exists('attach')) {
                            include('tools/attach/libs/attach.lib.php');
                        }
                        $attach = new attach($this->wiki);
                        $res=$attach->redimensionner_image($tempFile, $default_image_filename, $image_comp[5], $image_comp[6], "fit");
                        $res=array($res, $imgext, $tempFile, $default_image_filename, $image_comp[5], $image_comp[6]);
                    } finally {
                        unlink($tempFile);
                    }
                } else {
                    $res=Null;
                    $image_comp[8] = '';
                    if (file_exists($default_image_filename)) {
                        unlink($default_image_filename);
                    }
                }
                $template_list[$temp_index] = $image_comp;
            }
        }
        if ($modify) {
            $template = $this->encodeTemplate($template_list);
        }
        return $template;
    }
    
    protected function prepare_with_special_parameters($form) {
        $template_list = $this->parseTemplate($form['bn_template']);
        $modify = false;
        for ($temp_index = 0; $temp_index < count($template_list); $temp_index++) {
            if ($template_list[$temp_index][0] == 'image') {
                $modify = true;
                $image_comp = $template_list[$temp_index];
                $default_image_filename = "./files/dfltimg_{$form['bn_id_nature']}_{$image_comp[1]}.jpg";
                if (file_exists($default_image_filename)) {
                    $image_comp[8] = $image_comp[8].'|data:image/jpg;base64,'.base64_encode(file_get_contents($default_image_filename));
                } else {
                    $image_comp[8] = '';
                }
                $template_list[$temp_index] = $image_comp;
            }
        }
        return [$template_list, $modify];
    }
    
    public function getOne($formId): ?array
    {
        if (isset($this->cachedForms[$formId])) {
            return $this->cachedForms[$formId];
        }

        $form = $this->dbService->loadSingle('SELECT * FROM ' . $this->dbService->prefixTable('nature') . 'WHERE bn_id_nature=\'' . $this->dbService->escape($formId) . '\'');

        if (!$form) {
            return null;
        }

        $form = $this->getFromRawData($form);

        $this->cachedForms[$formId] = $form;

        return $form;
    }

    public function getFromRawData($form)
    {
        foreach ($form as $key => $value) {
            $form[$key] = _convert($value, 'ISO-8859-15');
        }
        list($template_list, $modify) = $this->prepare_with_special_parameters($form);
        $form['template'] = $template_list;
        $form['prepared'] = $this->prepareData($form);
        if ($modify == true) {
            $form['bn_template'] = $this->encodeTemplate($template_list);
        }
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
        if (empty($data['bn_id_nature']) || $this->getOne($data['bn_id_nature'])) {
            $data['bn_id_nature'] = $this->findNewId();
        }

        return $this->dbService->query('INSERT INTO ' . $this->dbService->prefixTable('nature')
            . '(`bn_id_nature` ,`bn_ce_i18n` ,`bn_label_nature` ,`bn_template` ,`bn_description` ,`bn_sem_context` ,`bn_sem_type` ,`bn_sem_use_template`'
            . ($this->isAvailableOnlyOneEntryOption() ? ',`bn_only_one_entry`' : '')
            . ($this->isAvailableOnlyOneEntryMessage() ? ',`bn_only_one_entry_message`' : '')
            .',`bn_condition`)'
            . ' VALUES (' . $data['bn_id_nature'] . ', "fr-FR", "'
            . $this->dbService->escape(_convert($data['bn_label_nature'], YW_CHARSET, true)) . '","'
            . $this->dbService->escape(_convert($data['bn_template'], YW_CHARSET, true)) . '", "'
            . $this->dbService->escape(_convert($data['bn_description'], YW_CHARSET, true)) . '", "'
            . $this->dbService->escape(_convert($data['bn_sem_context'], YW_CHARSET, true)) . '", "'
            . $this->dbService->escape(_convert($data['bn_sem_type'], YW_CHARSET, true)) . '", '
            . (isset($data['bn_sem_use_template']) ? '1' : '0') . ', "'
            . ($this->isAvailableOnlyOneEntryOption() ? ((isset($data['bn_only_one_entry']) && $data['bn_only_one_entry'] === "Y") ? "Y" : "N") . '", "' : '')
            . ($this->isAvailableOnlyOneEntryMessage() ? (empty($data['bn_only_one_entry_message']) ? "" : $this->dbService->escape(_convert($data['bn_only_one_entry_message'], YW_CHARSET, true))) . '", "' : '')
            . $this->dbService->escape(_convert($data['bn_condition'], YW_CHARSET, true)) . '")');
    }

    public function update($data)
    {
        if ($this->securityController->isWikiHibernated()) {
            throw new \Exception(_t('WIKI_IN_HIBERNATION'));
        }
        $template = $this->convert_with_special_parameters($data['bn_template'], $data['bn_id_nature']);
        return $this->dbService->query('UPDATE' . $this->dbService->prefixTable('nature') . 'SET '
            . '`bn_label_nature`="' . $this->dbService->escape(_convert($data['bn_label_nature'], YW_CHARSET, true)) . '" ,'
            . '`bn_template`="' . $template . '" ,'
            . '`bn_description`="' . $this->dbService->escape(_convert($data['bn_description'], YW_CHARSET, true)) . '" ,'
            . '`bn_sem_context`="' . $this->dbService->escape(_convert($data['bn_sem_context'], YW_CHARSET, true)) . '" ,'
            . '`bn_sem_type`="' . $this->dbService->escape(_convert($data['bn_sem_type'], YW_CHARSET, true)) . '" ,'
            . '`bn_sem_use_template`=' . (isset($data['bn_sem_use_template']) ? '1' : '0') . ' ,'
            . ($this->isAvailableOnlyOneEntryOption() ? '`bn_only_one_entry`="' . ((isset($data['bn_only_one_entry']) && $data['bn_only_one_entry'] === "Y") ? "Y" : "N") . '",' : '')
            . ($this->isAvailableOnlyOneEntryMessage() ? '`bn_only_one_entry_message`="' . (empty($data['bn_only_one_entry_message']) ? "" : $this->dbService->escape(_convert($data['bn_only_one_entry_message'], YW_CHARSET, true))) . '",' : '')
            . '`bn_condition`="' . $this->dbService->escape(_convert($data['bn_condition'], YW_CHARSET, true)) . '"'
            . ' WHERE `bn_id_nature`=' . $this->dbService->escape($data['bn_id_nature']));
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
        return $this->dbService->query('DELETE FROM ' . $this->dbService->prefixTable('nature') . 'WHERE bn_id_nature=' . $this->dbService->escape($id));
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
            'WHERE property="http://outils-reseaux.org/_vocabulary/type" AND value="fiche_bazar") AND body LIKE \'%"id_typeannonce":"' . $this->dbService->escape($id) . '"%\' );'
        );

        // TODO use PageManager
        $this->dbService->query(
            'DELETE FROM' . $this->dbService->prefixTable('pages') .
            'WHERE tag IN (SELECT resource FROM ' . $this->dbService->prefixTable('triples') .
            'WHERE property="http://outils-reseaux.org/_vocabulary/type" AND value="fiche_bazar") AND body LIKE \'%"id_typeannonce":"' . $this->dbService->escape($id) . '"%\';'
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
    
    public function encodeTemplate($template_list)
    {
        $new_template_list = [];
        for($temp_index = 0; $temp_index < count($template_list); $temp_index++) {
            $new_line = '';
            foreach ($template_list[$temp_index] as $value) {
                if ($value == '') {
                    $new_line.= ' ';
                }
                else if ($value == '*') {
                    $new_line.= ' * ';                    
                } else {
                    $new_line.=$value;
                }
                $new_line.= '***';
            }
            $new_template_list[] = $new_line;
        }
        $template = implode("\r\n", array_map('trim', $new_template_list));
        return $template;
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
            if (!isset($fields[$entry['id_typeannonce']])) {
                $fields[$entry['id_typeannonce']] = (empty($form['prepared']))
                    ? []
                    : $this->filterFieldsByPropertyName($form['prepared'], $groups);
            }

            foreach ($entry as $key => $value) {
                $facetteasked = (isset($groups[0]) && $groups[0] == 'all') || in_array($key, $groups);

                if (!empty($value) and is_array($fields[$entry['id_typeannonce']]) && $facetteasked) {
                    if (in_array($key, ['id_typeannonce','owner'])) {
                        $fieldPropName = $key;
                        $field = null;
                    } else {
                        $filteredFields = $this->filterFieldsByPropertyName($fields[$entry['id_typeannonce']], [$key]);
                        $field = array_pop($filteredFields);

                        $fieldPropName = null;
                        if ($field instanceof BazarField) {
                            $fieldPropName = $field->getPropertyName();
                            $fieldType = $field->getType();
                        }
                    }

                    if ($fieldPropName) {
                        if ($field instanceof EnumField) {
                            $facetteValue[$fieldPropName]['type'] = ($field->isEnumEntryField()) ? 'fiche' : 'liste';

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

        // remove `id_typeannonce` if only one form
        if (isset($facetteValue['id_typeannonce'])) {
            $nbForms = count(
                array_filter(
                    array_keys($facetteValue['id_typeannonce']),
                    function ($key) {
                        return !in_array($key, ['type','source']);
                    }
                )
            );
            if ($nbForms < 2) {
                unset($facetteValue['id_typeannonce']);
            }
        }
        return $facetteValue;
    }

    /*
     * Filter an array of fields by their potential entry ID
     */
    private function filterFieldsByPropertyName(array $fields, array $id)
    {
        if (count($id) === 1 && $id[0] === 'all') {
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

    /**
     * check if the bn_only_one_entry option is available
     * @return bool
     */
    public function isAvailableOnlyOneEntryOption(): bool
    {
        if (is_null($this->isAvailableOnlyOneEntryOption)) {
            $result = $this->dbService->query("SHOW COLUMNS FROM {$this->dbService->prefixTable("nature")} LIKE 'bn_only_one_entry';");
            $this->isAvailableOnlyOneEntryOption = (@mysqli_num_rows($result) !== 0);
        }
        return $this->isAvailableOnlyOneEntryOption;
    }

    /**
     * check if the bn_only_one_entry_message is available
     * @return bool
     */
    public function isAvailableOnlyOneEntryMessage(): bool
    {
        if (is_null($this->isAvailableOnlyOneEntryMessage)) {
            $result = $this->dbService->query("SHOW COLUMNS FROM {$this->dbService->prefixTable("nature")} LIKE 'bn_only_one_entry_message';");
            $this->isAvailableOnlyOneEntryMessage = (@mysqli_num_rows($result) !== 0);
        }
        return $this->isAvailableOnlyOneEntryMessage;
    }
}
