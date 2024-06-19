<?php

use YesWiki\Core\Service\ConfigurationService;
use YesWiki\Core\YesWikiAction;

class EditConfigAction extends YesWikiAction
{
    private const SAVE_NAME = 'save_config';
    private const SAVED_NAME = 'saved_config';
    private const CONFIG_POSTFIX = '_editable_config_params';
    private const AUTHORIZED_KEYS = [
        'wakka_name' => 'core',
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

        'password_for_editing' => 'security',
        'password_for_editing_message' => 'security',
        'htmlPurifierActivated' => 'security',
        'allowed_methods_in_iframe' => 'security',

        'contact_from' => 'contact', // merged in contact instead of email to prevent duplication of blocks
        'mail_custom_message' => 'contact',
    ];

    private $keys;
    private $associatedExtensions;

    protected $configurationService;

    public function formatArguments($arg)
    {
        return [
            'saving' => $this->formatBoolean($_POST, false, self::SAVE_NAME),
            'saved' => $this->formatBoolean($_GET, false, self::SAVED_NAME),
            'post' => $_POST,
        ];
    }

    public function run()
    {
        $this->keys = null;
        $this->associatedExtensions = null;
        if (!$this->wiki->UserIsAdmin()) {
            return $this->render('@templates/alert-message.twig', [
                'type' => 'danger',
                'message' => get_class($this) . ' : ' . _t('BAZ_NEED_ADMIN_RIGHTS'),
            ]);
        }
        if (!is_writable('wakka.config.php')) {
            return $this->render('@templates/alert-message.twig', [
                'type' => 'danger',
                'message' => _t('ERROR_NO_ACCESS') . ' ' . _t('FILE_WRITE_PROTECTED'),
            ]);
        }

        // get services
        $this->configurationService = $this->getService(ConfigurationService::class);

        $output = '';
        if ($this->arguments['saving']) {
            $this->save();
            $this->wiki->Redirect($this->wiki->Href('', '', [self::SAVED_NAME => '1'], false));
        } elseif ($this->arguments['saved']) {
            $output .= $this->render('@templates/alert-message.twig', [
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
        } else {
            return [];
        }
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
     * save data to wakka.config.php.
     *
     * @return bool true if successfull
     */
    private function save(): bool
    {
        $config = $this->configurationService->getConfiguration('wakka.config.php');
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
        $config = $this->configurationService->getConfiguration('wakka.config.php');
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
     *
     * @param Configuration $config
     * @param string        $firstLevelKey
     */

    /**
     * array to string.
     *
     * @param mixed $value
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
     *
     * @return mixed
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
