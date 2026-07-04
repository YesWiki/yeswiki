<?php

namespace YesWiki\Bazar\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Bazar\Service\ActivityPubService;
use YesWiki\Bazar\Field\BazarField;
use YesWiki\Bazar\Field\ImageField;
use YesWiki\Core\Service\DbService;
use YesWiki\Security\Controller\SecurityController;
use YesWiki\Wiki;

class FormManager
{
    protected $wiki;
    protected $dbService;
    protected $activityPubService;
    protected $httpSignatureService;
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
        SecurityController $securityController,
        ActivityPubService $activityPubService,
        HttpSignatureService $httpSignatureService,
    ) {
        if (!class_exists('attach')) {
            include 'tools/attach/libs/attach.lib.php';
        }
        $this->wiki = $wiki;
        $this->dbService = $dbService;
        $this->activityPubService = $activityPubService;
        $this->httpSignatureService = $httpSignatureService;
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

        if (intval($formId) . '' === $formId . '') {
            $form = $this->dbService->loadSingle('SELECT * FROM ' . $this->dbService->prefixTable('nature') . 'WHERE bn_id_nature=\'' . $this->dbService->escape($formId) . '\'');

            if (!$form) {
                return null;
            }

            $form = $this->getFromRawData($form);
        }

        $this->cachedForms[$formId] = $form ?? [];

        return $form ?? [];
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

        return array_filter(
            $this->cachedForms,
            function ($pKey) {
                return intval($pKey) . '' === $pKey . '';
            },
            ARRAY_FILTER_USE_KEY,
        );
    }

    public function getAllIds(): array
    {
        if ($this->cacheValidatedForAll) {
            return array_keys($this->getAll());
        }

        $rows = $this->dbService->loadAll("SELECT bn_id_nature FROM {$this->dbService->prefixTable('nature')}");

        return array_column($rows, 'bn_id_nature');
    }

    public function getMany($formsIds): array
    {
        if (count($formsIds) == 0) {
            return $this->getAll();
        }

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

        $activitypubEnabled = (int) $this->activityPubService->isEnabled($data);

        if ($activitypubEnabled) {
            $keyPair = $this->httpSignatureService->generateKeyPair();
            $privateKey = $keyPair[0];
            $publicKey = $keyPair[1];
        }

        // reset cache
        $this->cacheValidatedForAll = false;

        $query = 'INSERT INTO ' . $this->dbService->prefixTable('nature')
                    . '(`bn_id_nature` ,`bn_ce_i18n` ,`bn_label_nature` ,`bn_template` ,`bn_description` ,`bn_sem_template` ,`bn_sem_reverse_template`, `bn_activitypub_enable`, `bn_activitypub_username`, `bn_activitypub_private_key`, `bn_activitypub_public_key`'
                    . ($this->isAvailableOnlyOneEntryOption() ? ',`bn_only_one_entry`' : '')
                    . ($this->isAvailableOnlyOneEntryMessage() ? ',`bn_only_one_entry_message`' : '')
                    . ',`bn_condition`)'
                    . ' VALUES (' . intval($data['bn_id_nature']) . ', "fr-FR", "'
                    . $this->dbService->escape(_convert($data['bn_label_nature'] ?? '', YW_CHARSET, true)) . '", "'
                    . $this->dbService->escape(_convert($data['bn_template'] ?? '', YW_CHARSET, true)) . '", "'
                    . $this->dbService->escape(_convert($data['bn_description'] ?? '', YW_CHARSET, true)) . '", "'
                    . $this->dbService->escape(_convert($data['bn_sem_template'] ?? '', YW_CHARSET, true)) . '", "'
                    . $this->dbService->escape(_convert($data['bn_sem_reverse_template'] ?? '', YW_CHARSET, true)) . '", "'
                    . $activitypubEnabled . '", "'
                    . $this->dbService->escape(_convert($data['bn_activitypub_username'] ?? '', YW_CHARSET, true)) . '", "'
                    . (isset($privateKey) ? $privateKey . '", "' : '", "')
                    . (isset($publicKey) ? $publicKey . '", "' : '", "')
                    . ($this->isAvailableOnlyOneEntryOption() ? ((isset($data['bn_only_one_entry']) && $data['bn_only_one_entry'] === 'Y') ? 'Y' : 'N') . '", "' : '", "')
                    . ($this->isAvailableOnlyOneEntryMessage() ? (empty($data['bn_only_one_entry_message']) ? '' : $this->dbService->escape(_convert($data['bn_only_one_entry_message'], YW_CHARSET, true))) . '", "' : '", "')
            . $this->dbService->escape(_convert($data['bn_condition'], YW_CHARSET, true)) . '")';
        return $this->dbService->query($query);
    }

    public function update($data)
    {
        if ($this->securityController->isWikiHibernated()) {
            throw new \Exception(_t('WIKI_IN_HIBERNATION'));
        }

        $template = $this->convertWithSpecialParameters($data['bn_template'], $data['bn_id_nature']);

        // reset cache
        $this->cacheValidatedForAll = false;

        $activitypubEnabled = (int) $this->activityPubService->isEnabled($data);

        if ($activitypubEnabled && $data['bn_activitypub_private_key'] === null) {
            $keyPair = $this->httpSignatureService->generateKeyPair();
            $privateKey = $keyPair[0];
            $publicKey = $keyPair[1];
        }

        return $this->dbService->query('UPDATE' . $this->dbService->prefixTable('nature') . 'SET '
            . '`bn_label_nature`="' . $this->dbService->escape(_convert($data['bn_label_nature'], YW_CHARSET, true)) . '" ,'
            . '`bn_template`="' . $template . '" ,'
            . '`bn_description`="' . $this->dbService->escape(_convert($data['bn_description'], YW_CHARSET, true)) . '" ,'
            . '`bn_sem_template`="' . $this->dbService->escape(_convert($data['bn_sem_template'] ?? '', YW_CHARSET, true)) . '" ,'
            . '`bn_sem_reverse_template`="' . $this->dbService->escape(_convert($data['bn_sem_reverse_template'] ?? '', YW_CHARSET, true)) . '" ,'
            . '`bn_activitypub_enable`=' . $activitypubEnabled . ' ,'
            . '`bn_activitypub_username`="' . $this->dbService->escape(_convert($data['bn_activitypub_username'], YW_CHARSET, true)) . '" ,'
            . (isset($privateKey) ? '`bn_activitypub_private_key`="' . $privateKey . '" ,' : '')
            . (isset($publicKey) ? '`bn_activitypub_public_key`="' . $publicKey . '" ,' : '')
            . ($this->isAvailableOnlyOneEntryOption() ? '`bn_only_one_entry`="' . ((isset($data['bn_only_one_entry']) && $data['bn_only_one_entry'] === 'Y') ? 'Y' : 'N') . '",' : '')
            . ($this->isAvailableOnlyOneEntryMessage() ? '`bn_only_one_entry_message`="' . (empty($data['bn_only_one_entry_message']) ? '' : $this->dbService->escape(_convert($data['bn_only_one_entry_message'], YW_CHARSET, true))) . '",' : '')
            . '`bn_condition`="' . $this->dbService->escape(_convert($data['bn_condition'], YW_CHARSET, true)) . '"'
            . ' WHERE `bn_id_nature`=' . intval($data['bn_id_nature']));
    }

    public function clone($id)
    {
        $data = $this->getOne($id);
        if (!empty($data)) {
            unset($data['bn_id_nature']);
            $data['bn_label_nature'] = $data['bn_label_nature'] . ' (' . _t('BAZ_DUPLICATE') . ')';

            return $this->create($data);
        }

        // raise error?
        return false;
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
                "WHERE property='http://outils-reseaux.org/_vocabulary/type' AND value='fiche_bazar') AND body LIKE '%\"id_typeannonce\":\"" . $this->dbService->escape($id) . "\"%\' );"
        );

        // TODO use PageManager
        $this->dbService->query(
            'DELETE FROM' . $this->dbService->prefixTable('pages') .
                'WHERE tag IN (SELECT resource FROM ' . $this->dbService->prefixTable('triples') .
                "WHERE property='http://outils-reseaux.org/_vocabulary/type' AND value='fiche_bazar') AND body LIKE '%\"id_typeannonce\":\"" . $this->dbService->escape($id) . "\"%\';"
        );

        // TODO use TripleStore
        $this->dbService->query(
            'DELETE FROM' . $this->dbService->prefixTable('triples') .
                'WHERE resource NOT IN (SELECT tag FROM ' . $this->dbService->prefixTable('pages') .
                "WHERE 1) AND property='http://outils-reseaux.org/_vocabulary/type' AND value='fiche_bazar';"
        );
    }

    public function findNewId()
    {
        $vArrayKeys = array_keys($this->cachedForms);

        $vArrayKeys = array_map(function ($Key) {
            return intval($Key);
        }, array_filter($vArrayKeys, function ($vKey) {
            return intval($vKey) . '' === $vKey . '';
        }));

        $vMaxCachedFormId = (count($vArrayKeys) > 0) ? max($vArrayKeys) : 0;

        $vResult = $this->dbService->loadSingle('SELECT MAX(bn_id_nature) AS maxi FROM ' . $this->dbService->prefixTable('nature') . 'where bn_id_nature < 1000');

        if (!empty($vResult) && isset($vResult['maxi'])) {
            $vMaxDBFormIdLowerThan1000 = $vResult['maxi'];
        } else {
            $vMaxDBFormIdLowerThan1000 = 1;
        }

        $vCandidate = max($vMaxCachedFormId, $vMaxDBFormIdLowerThan1000) + 1;

        if ($vCandidate < 999) {
            return $vCandidate;
        }

        $vResult = $this->dbService->loadSingle('SELECT MAX(bn_id_nature) AS maxi FROM' . $this->dbService->prefixTable('nature') . ' where bn_id_nature > 10000');

        if (!empty($vResult) && isset($vResult['maxi'])) {
            $vMaxDBFormIdHigherThan10000 = $vResult['maxi'];
        } else {
            $vMaxDBFormIdHigherThan10000 = 10001;
        }

        $vCandidate = max($vMaxCachedFormId, $vMaxDBFormIdHigherThan10000) + 1;

        return $vCandidate;
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

    /*
        Add a form to the cache if it is not existing
    */

    public function cacheForm($pFormId, $pForm)
    {
        $this->cachedForms[$pFormId] = $pForm;
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
            $this->isAvailableOnlyOneEntryOption = $this->dbService->columnExists('nature', 'bn_only_one_entry');
        }

        return $this->isAvailableOnlyOneEntryOption;
    }

    /**
     * check if the bn_only_one_entry_message is available.
     */
    public function isAvailableOnlyOneEntryMessage(): bool
    {
        if (is_null($this->isAvailableOnlyOneEntryMessage)) {
            $this->isAvailableOnlyOneEntryMessage = $this->dbService->columnExists('nature', 'bn_only_one_entry_message');
        }

        return $this->isAvailableOnlyOneEntryMessage;
    }

    public function findTypeOfFields($formId, array $fieldTypes): array
    {
        $res = [];
        $form = $this->getOne($formId);
        if (empty($form)) {
            return $res;
        }

        foreach ($form['prepared'] as $field) {
            $class = get_class($field);
            $class = explode('\\', $class);
            $class = array_pop($class);
            if (in_array($class, $fieldTypes)) {
                $res[] = $field;
            }
        }

        return $res;
    }

    public function findFieldWithId(array $formId, $fieldId)
    {
        $res = [];
        foreach ($formId as $fId) {
            $form = $this->getOne($fId);
            if (empty($form)) {
                continue;
            }
            foreach ($form['prepared'] as $field) {
                if ($field->getPropertyName() === $fieldId) {
                    return $field;
                }
            }
        }

        return $res;
    }

    public function findByActivityPubUsername($username)
    {
        $forms = $this->getAll();
        foreach ($forms as $form) {
            if ($form['bn_activitypub_username'] === $username) {
                return $form;
            }
        }

        return null;
    }
}
