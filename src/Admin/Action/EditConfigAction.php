<?php

namespace YesWiki\Admin\Action;

use YesWiki\Core\YesWikiAction;
use YesWiki\Identity\Service\AclService;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Kernel\Service\ConfigurationFileProvider;
use YesWiki\Kernel\Service\ConfigurationService;
use YesWiki\Kernel\Service\UrlFormatter;

class EditConfigAction extends YesWikiAction implements RegisteredAction
{
    /** `{{editconfig}}` in page content -- stated, not inferred from the filename. */
    public static function performableName(): string
    {
        return 'editconfig';
    }

    private const SAVE_NAME = 'save_config';
    private const SAVED_NAME = 'saved_config';
    private const CONFIG_POSTFIX = '_editable_config_params';
    // formerly contributed via tools/security's config.yaml through the generic
    // extension _editable_config_params mechanism (see getAuthorizedKeys())
    private const ARCHIVE_KEYS = ['privatePath', 'call_archive_async', 'max_nb_files', 'preupdate_backup_activated'];
    // formerly contributed via tools/templates's config.yaml the same way
    private const META_KEYS = ['robots'];
    // formerly contributed via yeswiki-extension-qrcode's config.yaml's qrcode_editable_config_params
    // (default_user_form added here: it already had an EDIT_CONFIG_HINT_ lang key but was
    // missing from both the old extension's editable-params list and its own config.yaml defaults)
    private const QRCODE_KEYS = [
        'relation_form_id', 'default_relation_type', 'default_entity_type',
        'default_entity_form', 'default_user_form', 'visualisation_refresh_period',
    ];
    // formerly contributed via tools/attach's config.yaml's attach_editable_config_params
    // (max_file_size moved under attach_config, matching where it's actually read from --
    // the original bare top-level key never matched storage and so never worked)
    private const ATTACH_VIDEO_KEYS = ['default_video_service', 'default_peertube_instance'];
    private const ATTACH_CONFIG_KEYS = ['max_file_size'];
    // formerly contributed via tools/contact's config.yaml's contact_editable_config_params
    // (contact_from/mail_custom_message were already merged into AUTHORIZED_KEYS below,
    // predating this ticket)
    private const CONTACT_KEYS = [
        'contact_use_long_wiki_urls_in_emails', 'contact_mail_func', 'contact_smtp_host',
        'contact_smtp_port', 'contact_smtp_user', 'contact_smtp_pass', 'contact_smtp_secure',
        'contact_debug', 'contact_disable_email_for_password',
    ];
    // formerly contributed via tools/bazar's config.yaml's bazar_editable_config_params
    private const BAZAR_KEYS = [
        'baz_map_center_lat', 'baz_map_center_lon', 'baz_map_zoom', 'baz_map_height',
        'BAZ_ADRESSE_MAIL_ADMIN', 'BAZ_ENVOI_MAIL_ADMIN', 'bazarIgnoreAcls',
    ];
    private const BAZAR_EXTERNAL_SERVICE_KEYS = [
        'cache_time_to_check_changes', 'cache_time_to_check_deletion', 'cache_time_to_refresh_forms',
    ];
    private const AUTHORIZED_KEYS = [
        'yeswiki_name' => 'core',
        'root_page' => 'core',
        'default_language' => 'core',
        'favicon' => 'core',
        'debug' => 'core',
        'timezone' => 'core',
        'revisionscount' => 'core',
        'default_comment_avatar' => 'core',
        'favorites_activated' => 'core',
        'preview_before_save' => 'core',

        'default_read_acl' => 'access',
        'default_write_acl' => 'access',
        'default_comment_acl' => 'access',
        'comments_activated' => 'access',
        'comments_handler' => 'access',
        'allow_doubleclic' => 'access',

        // formerly contributed via yeswiki-extension-herse's config.yaml's
        // herse_editable_config_params (ticket 21)
        'herse_id' => 'herse',
        'herse_password' => 'herse',

        'password_for_editing' => 'security',
        'password_for_editing_message' => 'security',
        'htmlPurifierActivated' => 'security',
        'htmlPurifierSafeIframeRegexp' => 'security',
        'allowed_methods_in_iframe' => 'security',
        'signup_email_activation' => 'security',
        'user_activation_key_length' => 'security',
        'use_alerte' => 'security',
        'use_captcha' => 'security',
        'use_hashcash' => 'security',
        'wiki_status' => 'security',

        'contact_from' => 'contact', // merged in contact instead of email to prevent duplication of blocks
        'mail_custom_message' => 'contact',

        'hide_keywords' => 'tags',

        'meta_keywords' => 'templates',
        'meta_description' => 'templates',
    ];

    private $keys;
    private $associatedExtensions;

    protected $configurationService;

    public function formatArguments($arg)
    {
        return [
            'saving' => $this->formatBoolean($this->getRequest()->request->all(), false, self::SAVE_NAME),
            'saved' => $this->formatBoolean($this->getRequest()->query->all(), false, self::SAVED_NAME),
            'post' => $this->getRequest()->request->all(),
        ];
    }

    public function run()
    {
        $this->keys = null;
        $this->associatedExtensions = null;
        if (!$this->getService(AclService::class)->isAdmin()) {
            return $this->render('@core/alert-message.twig', [
                'type' => 'danger',
                'message' => get_class($this) . ' : ' . _t('BAZ_NEED_ADMIN_RIGHTS'),
            ]);
        }
        if (!is_writable(ConfigurationFileProvider::getConfigFileFromEnv())) {
            return $this->render('@core/alert-message.twig', [
                'type' => 'danger',
                'message' => _t('ERROR_NO_ACCESS') . ' ' . _t('FILE_WRITE_PROTECTED'),
            ]);
        }

        // get services
        $this->configurationService = $this->getService(ConfigurationService::class);

        $output = '';
        if ($this->arguments['saving']) {
            $this->save();
            $this->wiki->Redirect($this->getService(UrlFormatter::class)->href('', '', [self::SAVED_NAME => '1'], false));
        } elseif ($this->arguments['saved']) {
            $output .= $this->render('@core/alert-message.twig', [
                'type' => 'info',
                'message' => _t('EDIT_CONFIG_SAVE'),
            ]);
        }

        // display form
        list($data, $placeholders, $associatedExtensions) = $this->getDataFromConfigFile();
        $keysList = [];
        foreach ($data as $key => $value) {
            if (!empty($associatedExtensions[$key])) {
                $keysList[$associatedExtensions[$key]] = array_merge($keysList[$associatedExtensions[$key]] ?? [], [$key => $value]);
            } else {
                $keysList[''] = array_merge($keysList[''] ?? [], [$key => $value]);
            }
        }

        return $output . $this->render('@core/edit-config.twig', [
            'SAVE_NAME' => self::SAVE_NAME,
            'keysList' => $keysList,
            'placeholders' => $placeholders,
            'help' => $this->getHelp(),
        ]);
    }

    /**
     * get AUTHORIZED_KEYS
     * return array [$keys,$associatedExtensions].
     */
    private function getAuthorizedKeys(): array
    {
        if (is_null($this->keys)) {
            $associatedExtensions = self::AUTHORIZED_KEYS;
            $keys = array_keys(self::AUTHORIZED_KEYS);

            $keys[] = ['archive' => self::ARCHIVE_KEYS];
            foreach (self::ARCHIVE_KEYS as $archiveKey) {
                $associatedExtensions["archive[{$archiveKey}]"] = 'security';
            }

            $keys[] = ['meta' => self::META_KEYS];
            foreach (self::META_KEYS as $metaKey) {
                $associatedExtensions["meta[{$metaKey}]"] = 'templates';
            }

            $keys[] = ['qrcode_config' => self::QRCODE_KEYS];
            foreach (self::QRCODE_KEYS as $qrcodeKey) {
                $associatedExtensions["qrcode_config[{$qrcodeKey}]"] = 'qrcode';
            }

            $keys[] = ['attach-video-config' => self::ATTACH_VIDEO_KEYS];
            foreach (self::ATTACH_VIDEO_KEYS as $attachVideoKey) {
                $associatedExtensions["attach-video-config[{$attachVideoKey}]"] = 'attach';
            }
            $keys[] = ['attach_config' => self::ATTACH_CONFIG_KEYS];
            foreach (self::ATTACH_CONFIG_KEYS as $attachConfigKey) {
                $associatedExtensions["attach_config[{$attachConfigKey}]"] = 'attach';
            }

            // top-level keys, matching tools/contact's own (bare, not nested) config.yaml shape
            foreach (self::CONTACT_KEYS as $contactKey) {
                $keys[] = $contactKey;
                $associatedExtensions[$contactKey] = 'contact';
            }

            // top-level keys, matching tools/bazar's own (bare, not nested) config.yaml shape
            foreach (self::BAZAR_KEYS as $bazarKey) {
                $keys[] = $bazarKey;
                $associatedExtensions[$bazarKey] = 'bazar';
            }

            $keys[] = ['baz_external_service' => self::BAZAR_EXTERNAL_SERVICE_KEYS];
            foreach (self::BAZAR_EXTERNAL_SERVICE_KEYS as $bazarExternalServiceKey) {
                $associatedExtensions["baz_external_service[{$bazarExternalServiceKey}]"] = 'bazar';
            }

            foreach ($this->wiki->extensions as $extensionFolder) {
                $matches = [];
                if (preg_match('/(?:\/?tools\/?)?([^\/]+)\/?/', $extensionFolder, $matches)) {
                    $extensionName = $matches[1];
                    $paramName = $extensionName . self::CONFIG_POSTFIX;
                    if ($this->params->has($paramName)) {
                        $keysToMerge = $this->params->get($paramName);
                        if (!empty($keysToMerge)) {
                            if (is_array($keysToMerge)) {
                                $keys = array_merge($keys, $keysToMerge);
                                $keyNames = $this->prepareKeyNames($keysToMerge, true);
                                foreach ($keyNames as $keyName) {
                                    $associatedExtensions[$keyName] = $extensionName;
                                }
                            } elseif (is_string($keysToMerge)) {
                                $keys[] = $keysToMerge;
                                $associatedExtensions[$keysToMerge] = $extensionName;
                            }
                        }
                    }
                }
            }
            // remove duplicate
            $scannedKeysNames = [];
            $scannedKeys = [];
            foreach ($keys as $key) {
                if (is_array($key)) {
                    foreach ($key as $firstLevel => $secondLevelKeys) {
                        if (!in_array($firstLevel, $scannedKeysNames)) {
                            $scannedKeysNames[] = $firstLevel;
                            $scannedKeys[] = $key;
                            break;
                        }
                    }
                } else {
                    if (!in_array($key, $scannedKeysNames)) {
                        $scannedKeysNames[] = $key;
                        $scannedKeys[] = $key;
                    }
                }
            }
            $this->keys = $scannedKeys;
            $this->associatedExtensions = $associatedExtensions;
        }

        return [$this->keys, $this->associatedExtensions];
    }

    /**
     * prepare array of $keyNames from $keys
     * recursive.
     *
     * @param array|string $keys
     *
     * @return array [$keyName1,$keyName2]
     */
    private function prepareKeyNames($keys, bool $firstLevel = false): array
    {
        if (is_string($keys)) {
            return $firstLevel ? [$keys] : ["[{$keys}]"];
        } elseif (is_array($keys)) {
            $result = [];
            $isList = $this->arrayIsList($keys);
            foreach ($keys as $key => $value) {
                $subLevelKeyNames = $this->prepareKeyNames($value, $firstLevel && $isList);
                foreach ($subLevelKeyNames as $subLevelKeyName) {
                    $result[] = ($isList ? '' : ($firstLevel ? $key : "[{$key}]")) . $subLevelKeyName;
                }
            }

            return $result;
        }

        return [];
    }

    /**
     * could be replace by array_is_list since php 8.1.
     */
    private function arrayIsList(array $array): bool
    {
        $keys = array_keys($array);
        foreach ($keys as $index => $key) {
            if (strval($index) != strval($key)) {
                return false;
            }
        }

        return true;
    }

    /**
     * save data to yeswiki.config.php.
     *
     * @return bool true if successfull
     */
    private function save(): bool
    {
        $config = $this->configurationService->getConfiguration(ConfigurationFileProvider::getConfigFileFromEnv());
        $config->load();

        $keysAsArray = $this->convertKeysAsArray($this->getAuthorizedKeys()[0]);
        foreach ($keysAsArray as $keyAsArray) {
            if (!empty($keyAsArray)) {
                $length = count($keyAsArray);
                $firstLevelKey = $keyAsArray[0];
                switch ($length) {
                    case 1:
                        $new_value = $this->arguments['post'][$firstLevelKey] ?? null;
                        if (is_null($new_value) || $new_value === '') {
                            unset($config->$firstLevelKey);
                        } else {
                            $config->$firstLevelKey = $this->strtoarray($new_value);
                        }
                        break;
                    case 2:
                        $new_value =
                            isset($this->arguments['post'][$firstLevelKey])
                            && isset($this->arguments['post'][$firstLevelKey][$keyAsArray[1]])
                            ? $this->arguments['post'][$firstLevelKey][$keyAsArray[1]]
                            : null;
                        if (is_null($new_value) || $new_value === '') {
                            if (isset($config->$firstLevelKey) && isset($config->$firstLevelKey[$keyAsArray[1]])) {
                                $tmp = $config->$firstLevelKey;
                                unset($tmp[$keyAsArray[1]]);
                                if (empty($tmp)) {
                                    unset($config->$firstLevelKey);
                                } else {
                                    $config->$firstLevelKey = $tmp;
                                }
                            }
                        } else {
                            if (isset($config->$firstLevelKey) && is_array($config->$firstLevelKey)) {
                                $config->$firstLevelKey = array_merge($config->$firstLevelKey, [$keyAsArray[1] => $this->strtoarray($new_value)]);
                            } else {
                                $config->$firstLevelKey = [$keyAsArray[1] => $this->strtoarray($new_value)];
                            }
                        }
                        break;
                    case 3:
                        $new_value =
                            isset($this->arguments['post'][$firstLevelKey])
                            && isset($this->arguments['post'][$firstLevelKey][$keyAsArray[1]])
                            && isset($this->arguments['post'][$firstLevelKey][$keyAsArray[1]][$keyAsArray[2]])
                            ? $this->arguments['post'][$firstLevelKey][$keyAsArray[1]][$keyAsArray[2]]
                            : null;
                        if (is_null($new_value) || $new_value === '') {
                            if (
                                isset($config->$firstLevelKey)
                                && isset($config->$firstLevelKey[$keyAsArray[1]])
                                && isset($config->$firstLevelKey[$keyAsArray[1]][$keyAsArray[2]])
                            ) {
                                $tmp = $config->$firstLevelKey;
                                unset($tmp[$keyAsArray[1]][$keyAsArray[2]]);
                                if (empty($tmp[$keyAsArray[1]])) {
                                    unset($tmp[$keyAsArray[1]]);
                                }
                                if (empty($tmp)) {
                                    unset($config->$firstLevelKey);
                                } else {
                                    $config->$firstLevelKey = $tmp;
                                }
                            }
                        } else {
                            if (isset($config->$firstLevelKey) && is_array($config->$firstLevelKey)) {
                                if (isset($config->$firstLevelKey[$keyAsArray[1]]) && is_array($config->$firstLevelKey[$keyAsArray[1]])) {
                                    $tmp = $config->$firstLevelKey;
                                    $tmp[$keyAsArray[1]] = array_merge($tmp[$keyAsArray[1]], [$keyAsArray[2] => $this->strtoarray($new_value)]);
                                    $config->$firstLevelKey = $tmp;
                                } else {
                                    $config->$firstLevelKey = array_merge(
                                        $config->$firstLevelKey,
                                        [
                                            $keyAsArray[1] => [
                                                $keyAsArray[2] => $this->strtoarray($new_value),
                                            ],
                                        ]
                                    );
                                }
                            } else {
                                $config->$firstLevelKey = [
                                    $keyAsArray[1] => [
                                        $keyAsArray[2] => $this->strtoarray($new_value),
                                    ],
                                ];
                            }
                        }
                        break;

                    default:
                        break;
                }
            }
        }

        return $config->write();
    }

    /**
     * get data from config file.
     *
     * @return array [$data,$placeholders,$associatedExtensions] format ['name' => string $value,'name2'=> "['ee'=>'yy',...]"]
     */
    private function getDataFromConfigFile(): array
    {
        $config = $this->configurationService->getConfiguration(ConfigurationFileProvider::getConfigFileFromEnv());
        $config->load();
        $data = [];
        $placeholders = [];
        list($keys, $associatedExtensions) = $this->getAuthorizedKeys();
        $keysAsArray = $this->convertKeysAsArray($keys);
        foreach ($keysAsArray as $keyAsArray) {
            if (!empty($keyAsArray)) {
                $length = count($keyAsArray);
                $firstLevelKey = $keyAsArray[0];
                $keyName = $firstLevelKey . ($length > 1 ? '[' . implode('][', array_slice($keyAsArray, 1)) . ']' : '');
                switch ($length) {
                    case 1:
                        if (isset($config->$firstLevelKey)) {
                            $data[$keyName] = $this->array2Str($config->$firstLevelKey);
                        } else {
                            $data[$keyName] = '';
                        }
                        if ($this->params->has($firstLevelKey)) {
                            $placeholders[$keyName] = $this->array2Str($this->params->get($firstLevelKey));
                        }
                        break;
                    case 2:
                        if (
                            isset($config->$firstLevelKey)
                            && isset($config->$firstLevelKey[$keyAsArray[1]])
                        ) {
                            $data[$keyName] = $this->array2Str($config->$firstLevelKey[$keyAsArray[1]]);
                        } else {
                            $data[$keyName] = '';
                        }
                        if (
                            $this->params->has($firstLevelKey)
                            && isset($this->params->get($firstLevelKey)[$keyAsArray[1]])
                        ) {
                            $placeholders[$keyName] = $this->array2Str($this->params->get($firstLevelKey)[$keyAsArray[1]]);
                        }
                        break;
                    case 3:
                        if (
                            isset($config->$firstLevelKey)
                            && isset($config->$firstLevelKey[$keyAsArray[1]])
                            && isset($config->$firstLevelKey[$keyAsArray[1]][$keyAsArray[2]])
                        ) {
                            $data[$keyName] = $this->array2Str($config->$firstLevelKey[$keyAsArray[1]][$keyAsArray[2]]);
                        } else {
                            $data[$keyName] = '';
                        }
                        if (
                            $this->params->has($firstLevelKey)
                            && isset($this->params->get($firstLevelKey)[$keyAsArray[1]])
                            && isset($this->params->get($firstLevelKey)[$keyAsArray[1]][$keyAsArray[2]])
                        ) {
                            $placeholders[$keyName] = $this->array2Str($this->params->get($firstLevelKey)[$keyAsArray[1]][$keyAsArray[2]]);
                        }
                        break;

                    default:
                        $data[$keyName] = '';
                        break;
                }
            }
        }

        return [$data, $placeholders, $associatedExtensions];
    }

    /**
     * convert $keys to array of arrays.
     *
     * @return array $conertedKeys
     */
    private function convertKeysAsArray(array $keys): array
    {
        $convertedKeys = [];
        $isList = $this->arrayIsList($keys);
        foreach ($keys as $key => $subKey) {
            if (is_string($subKey)) {
                $convertedKeys[] = $isList ? [$subKey] : [$key, $subKey];
            } elseif (is_array($subKey)) {
                $result = $this->convertKeysAsArray($subKey);
                foreach ($result as $value) {
                    if ($isList) {
                        $convertedKeys[] = $value;
                    } else {
                        $newValue = array_values($value);
                        array_unshift($newValue, $key);
                        $convertedKeys[] = $newValue;
                    }
                }
            }
        }

        return $convertedKeys;
    }

    /**
     * extract associated values from config second level.
     */

    /**
     * array to string.
     */
    private function array2Str($value): string
    {
        if (is_array($value)) {
            if (count($value) > 0 && $this->arrayIsList($value)) {
                $value = '['
                    . implode(
                        ',',
                        array_map(function ($k, $v) {
                            return ($v === false) ? 'false' : (($v === true) ? 'true' : "'" . $v . "'");
                        }, array_keys($value), array_values($value))
                    )
                    . ']';
            } else {
                $value = '['
                    . implode(
                        ',',
                        array_map(function ($k, $v) {
                            return "'" . $k . "' => " . (($v === false) ? 'false' : (($v === true) ? 'true' : "'" . $v . "'"));
                        }, array_keys($value), array_values($value))
                    )
                    . ']';
            }
        } elseif (!is_string($value)) {
            try {
                $value = (($value === false) ? 'false' : (($value === true) ? 'true' : strval($value)));
            } catch (\Throwable $th) {
                $value = '';
            }
        }

        return $value;
    }

    /**
     * string to array if needed.
     */
    private function strtoarray(string $value)
    {
        $val = trim($value);
        $matches = [];
        if (preg_match('/^\s*\[\s*(.*)\s*\]\s*$/', $val, $matches)) {
            $val = $matches[1];
            $lines = preg_split('/(?<=\'|"|true|false|[0-9])\s*,\s*(?=\'|"|true|false|[0-9])/', $val);
            $result = [];
            foreach ($lines as $line) {
                $extract = explode('=>', $line);
                if (in_array(count($extract), [1, 2])) {
                    if (count($extract) == 2) {
                        $key = trim($extract[0]);
                        if (preg_match('/^\s*(?:\'|")\s*(.*)\s*(?:\'|")\s*$/', $key, $matches)) {
                            $key = $matches[1];
                        }
                        $val = trim($extract[1]);
                    } else {
                        $val = trim($extract[0]);
                    }
                    if (preg_match('/^\s*(?:\'|")\s*(.*)\s*(?:\'|")\s*$/', $val, $matches)) {
                        $val = $matches[1];
                    }
                    $val = ($val == 'true') ? true : (($val == 'false') ? false : $val);
                    if (count($extract) == 2) {
                        $result[$key] = $val;
                    } else {
                        $result[] = $val;
                    }
                }
            }
            if (count($result) > 0) {
                return $result;
            }
        } else {
            $value = ($value == 'true') ? true : (($value == 'false') ? false : $value);
        }

        return $value;
    }

    /**
     * get help from translation.
     */
    private function getHelp(): array
    {
        $help = [];
        foreach ($this->convertKeysAsArray($this->getAuthorizedKeys()[0]) as $keyAsArray) {
            $length = count($keyAsArray);
            $firstLevelKey = $keyAsArray[0];
            $keyName = $firstLevelKey . ($length > 1 ? '[' . implode('][', array_slice($keyAsArray, 1)) . ']' : '');
            if (isset($GLOBALS['translations']['EDIT_CONFIG_HINT_' . $keyName])) {
                $help[$keyName] = _t('EDIT_CONFIG_HINT_' . $keyName);
            } elseif (isset($GLOBALS['translations']['EDIT_CONFIG_HINT_' . strtoupper($keyName)])) {
                $help[$keyName] = _t('EDIT_CONFIG_HINT_' . strtoupper($keyName));
            }
        }

        return $help;
    }
}
