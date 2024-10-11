<?php

namespace YesWiki\Bazar\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Bazar\Field\BazarField;
use YesWiki\Bazar\Field\ImageField;
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
    protected $cacheValidatedForAll;
    protected $isAvailableOnlyOneEntryOption;
    protected $isAvailableOnlyOneEntryMessage;
    protected $attach;

    public function __construct(
        Wiki $wiki,
        DbService $dbService,
        EntryManager $entryManager,
        FieldFactory $fieldFactory,
        ParameterBagInterface $params,
        SecurityController $securityController
    ) {
        if (!class_exists('attach')) {
            include 'tools/attach/libs/attach.lib.php';
        }
        $this->wiki = $wiki;
        $this->dbService = $dbService;
        $this->entryManager = $entryManager;
        $this->fieldFactory = $fieldFactory;
        $this->params = $params;

        $this->cachedForms = [];
        $this->cacheValidatedForAll = false;
        $this->securityController = $securityController;
        $this->isAvailableOnlyOneEntryOption = null;
        $this->isAvailableOnlyOneEntryMessage = null;
        $this->attach = new \Attach($this->wiki);
    }

    protected function getBasePath()
    {
        $basePath = $this->attach->GetUploadPath();

        return $basePath . (substr($basePath, -1) != '/' ? '/' : '');
    }

    protected function cleanCacheDefaultImage($prefix)
    {
        $cache_path = $this->attach->GetCachePath();
        $cache_path = $cache_path . (substr($cache_path, -1) != '/' ? '/' : '');
        $scan_cache_files = scandir($cache_path);
        foreach ($scan_cache_files as $scan_cache_file) {
            if (str_starts_with($scan_cache_file, $prefix)) {
                unlink($cache_path . $scan_cache_file);
            }
        }
    }

    protected function convertWithSpecialParameters($template, $id_nature)
    {
        $template = _convert($template, YW_CHARSET, true);
        $template_list = $this->parseTemplate($template);
        $modify = false;
        for ($temp_index = 0; $temp_index < count($template_list); $temp_index++) {
            if ($template_list[$temp_index][0] == 'image') {
                $modify = true;
                $basePath = $this->getBasePath();
                $image_comp = $template_list[$temp_index];
                $default_image_prefix = "defaultimage{$id_nature}_{$image_comp[1]}";
                $this->cleanCacheDefaultImage($default_image_prefix);
                $default_image_filename = $basePath . $default_image_prefix . '.jpg';
                $default_image = explode('|', $image_comp[ImageField::FIELD_IMAGE_DEFAULT]);
                if (count($default_image) == 2) {
                    $image_comp[ImageField::FIELD_IMAGE_DEFAULT] = $default_image[0];
                    $imgext = explode('image/', explode(';', $default_image[1])[0])[1];
                    $tmpFile = tempnam('cache', 'dfltimg');
                    $tempFile = $tmpFile . '.' . $imgext;
                    rename($tmpFile, $tempFile);
                    try {
                        $ifp = fopen($tempFile, 'wb');
                        fwrite($ifp, base64_decode(explode(',', $default_image[1])[1]));
                        fclose($ifp);
                        $this->attach->redimensionner_image($tempFile, $default_image_filename, $image_comp[5], $image_comp[6], 'crop');
                    } finally {
                        unlink($tempFile);
                    }
                } else {
                    $image_comp[ImageField::FIELD_IMAGE_DEFAULT] = '';
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

        return $this->dbService->escape($template);
    }

    protected function prepare_with_special_parameters($form)
    {
        $basePath = $this->getBasePath();
        $template_list = $this->parseTemplate($form['bn_template']);
        $modify = false;
        for ($temp_index = 0; $temp_index < count($template_list); $temp_index++) {
            if ($template_list[$temp_index][0] == 'image') {
                $modify = true;
                $image_comp = $template_list[$temp_index];
                $default_image_filename = $basePath . "defaultimage{$form['bn_id_nature']}_{$image_comp[1]}.jpg";
                if (file_exists($default_image_filename)) {
                    $image_comp[ImageField::FIELD_IMAGE_DEFAULT] = $image_comp[ImageField::FIELD_IMAGE_DEFAULT] . '|data:image/jpg;base64,' . base64_encode(file_get_contents($default_image_filename));
                } else {
                    $image_comp[ImageField::FIELD_IMAGE_DEFAULT] = '';
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
        if (!$this->cacheValidatedForAll) {
            $forms = $this->dbService->loadAll("SELECT * FROM {$this->dbService->prefixTable('nature')} ORDER BY bn_label_nature ASC");
            foreach ($forms as $form) {
                if (!empty($form['bn_id_nature'])) {
                    // save only not empty formId
                    $formId = $form['bn_id_nature'];
                    $this->cachedForms[$formId] = $this->getFromRawData($form);
                }
            }
            $this->cacheValidatedForAll = true;
        }

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

        // reset cache
        $this->cacheValidatedForAll = false;

        return $this->dbService->query('INSERT INTO ' . $this->dbService->prefixTable('nature')
            . '(`bn_id_nature` ,`bn_ce_i18n` ,`bn_label_nature` ,`bn_template` ,`bn_description` ,`bn_sem_context` ,`bn_sem_type` ,`bn_sem_use_template`'
            . ($this->isAvailableOnlyOneEntryOption() ? ',`bn_only_one_entry`' : '')
            . ($this->isAvailableOnlyOneEntryMessage() ? ',`bn_only_one_entry_message`' : '')
            . ',`bn_condition`)'
            . ' VALUES (' . $data['bn_id_nature'] . ', "fr-FR", "'
            . $this->dbService->escape(_convert($data['bn_label_nature'], YW_CHARSET, true)) . '","'
            . $this->dbService->escape(_convert($data['bn_template'], YW_CHARSET, true)) . '", "'
            . $this->dbService->escape(_convert($data['bn_description'], YW_CHARSET, true)) . '", "'
            . $this->dbService->escape(_convert($data['bn_sem_context'], YW_CHARSET, true)) . '", "'
            . $this->dbService->escape(_convert($data['bn_sem_type'], YW_CHARSET, true)) . '", '
            . (isset($data['bn_sem_use_template']) ? '1' : '0') . ', "'
            . ($this->isAvailableOnlyOneEntryOption() ? ((isset($data['bn_only_one_entry']) && $data['bn_only_one_entry'] === 'Y') ? 'Y' : 'N') . '", "' : '')
            . ($this->isAvailableOnlyOneEntryMessage() ? (empty($data['bn_only_one_entry_message']) ? '' : $this->dbService->escape(_convert($data['bn_only_one_entry_message'], YW_CHARSET, true))) . '", "' : '')
            . $this->dbService->escape(_convert($data['bn_condition'], YW_CHARSET, true)) . '")');
    }

    public function update($data)
    {
        if ($this->securityController->isWikiHibernated()) {
            throw new \Exception(_t('WIKI_IN_HIBERNATION'));
        }

        $template = $this->convertWithSpecialParameters($data['bn_template'], $data['bn_id_nature']);

        // reset cache
        $this->cacheValidatedForAll = false;

        return $this->dbService->query('UPDATE' . $this->dbService->prefixTable('nature') . 'SET '
            . '`bn_label_nature`="' . $this->dbService->escape(_convert($data['bn_label_nature'], YW_CHARSET, true)) . '" ,'
            . '`bn_template`="' . $template . '" ,'
            . '`bn_description`="' . $this->dbService->escape(_convert($data['bn_description'], YW_CHARSET, true)) . '" ,'
            . '`bn_sem_context`="' . $this->dbService->escape(_convert($data['bn_sem_context'], YW_CHARSET, true)) . '" ,'
            . '`bn_sem_type`="' . $this->dbService->escape(_convert($data['bn_sem_type'], YW_CHARSET, true)) . '" ,'
            . '`bn_sem_use_template`=' . (isset($data['bn_sem_use_template']) ? '1' : '0') . ' ,'
            . ($this->isAvailableOnlyOneEntryOption() ? '`bn_only_one_entry`="' . ((isset($data['bn_only_one_entry']) && $data['bn_only_one_entry'] === 'Y') ? 'Y' : 'N') . '",' : '')
            . ($this->isAvailableOnlyOneEntryMessage() ? '`bn_only_one_entry_message`="' . (empty($data['bn_only_one_entry_message']) ? '' : $this->dbService->escape(_convert($data['bn_only_one_entry_message'], YW_CHARSET, true))) . '",' : '')
            . '`bn_condition`="' . $this->dbService->escape(_convert($data['bn_condition'], YW_CHARSET, true)) . '"'
            . ' WHERE `bn_id_nature`=' . $this->dbService->escape($data['bn_id_nature']));
    }

    public function clone($id)
    {
        $data = $this->getOne($id);
        if (!empty($data)) {
            unset($data['bn_id_nature']);
            $data['bn_label_nature'] = $data['bn_label_nature'] . ' (' . _t('BAZ_DUPLICATE') . ')';

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
            return null;
        }

        $this->clear($id);

        // reset cache
        $this->cacheValidatedForAll = false;

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
     * Découpe le template et renvoie un tableau structuré.
     *
     * @param string  Template du formulaire
     *
     * @return mixed Le tableau des elements du formulaire et options pour l'element liste
     */
    public function parseTemplate($raw)
    {
        // Parcours du template, pour mettre les champs du formulaire avec leurs valeurs specifiques
        $tableau_template = [];
        $nblignes = 0;

        // on traite le template ligne par ligne
        $chaine = explode("\n", $raw);
        foreach ($chaine as $ligne) {
            $ligne = trim($ligne);
            // on ignore les lignes vides ou commencant par # (commentaire)
            if (!empty($ligne) && !(strrpos($ligne, '#', -strlen($ligne)) !== false)) {
                // on decoupe chaque ligne par le separateur *** (c'est historique)
                $tablignechampsformulaire = array_map('trim', explode('***', $ligne));

                // TODO find another way to check that the field is valid
                if (true /* function_exists($tablignechampsformulaire[self::FIELD_TYPE]) */) {
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
        for ($temp_index = 0; $temp_index < count($template_list); $temp_index++) {
            $new_line = '';
            foreach ($template_list[$temp_index] as $value) {
                if ($value == '') {
                    $new_line .= ' ';
                } elseif ($value == '*') {
                    $new_line .= ' * ';
                } else {
                    $new_line .= $value;
                }
                $new_line .= '***';
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
                $field['functionName'] = $functionName;
                $classField = $this->fieldFactory->create($field);
                if ($classField) {
                    $prepared[$i] = $classField;
                }
            }
            $i++;
        }

        return $prepared;
    }

    /**
     * put a form form External Wiki in cache.
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
     * return field from field name or property name.
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
            if (in_array($name, [$field->getName(), $field->getPropertyName()])) {
                return $field;
            }
        }

        return null;
    }

    /**
     * check if the bn_only_one_entry option is available.
     */
    public function isAvailableOnlyOneEntryOption(): bool
    {
        if (is_null($this->isAvailableOnlyOneEntryOption)) {
            $result = $this->dbService->query("SHOW COLUMNS FROM {$this->dbService->prefixTable('nature')} LIKE 'bn_only_one_entry';");
            $this->isAvailableOnlyOneEntryOption = (@mysqli_num_rows($result) !== 0);
        }

        return $this->isAvailableOnlyOneEntryOption;
    }

    /**
     * check if the bn_only_one_entry_message is available.
     */
    public function isAvailableOnlyOneEntryMessage(): bool
    {
        if (is_null($this->isAvailableOnlyOneEntryMessage)) {
            $result = $this->dbService->query("SHOW COLUMNS FROM {$this->dbService->prefixTable('nature')} LIKE 'bn_only_one_entry_message';");
            $this->isAvailableOnlyOneEntryMessage = (@mysqli_num_rows($result) !== 0);
        }

        return $this->isAvailableOnlyOneEntryMessage;
    }
}
