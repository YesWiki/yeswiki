<?php

use YesWiki\Core\YesWikiAction;
use YesWiki\Core\Service\Performer;

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
        'allowed_methods_in_iframe' => 'core',
        'revisionscount' => 'core',
        'default_comment_avatar' => 'core',

        'default_read_acl' => 'access',
        'default_write_acl' => 'access',
        'default_comment_acl' => 'access',
        'password_for_editing' => 'access',
        'password_for_editing_message' => 'access',
        'allow_doubleclic' => 'access',

        'contact_from' => 'contact', // merged in contact instead of email to prevent duplication of blocks
        'mail_custom_message' => 'contact',
    ];

    private $keys ;
    private $associatedExtensions ;

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
        $this->keys = null ;
        $this->associatedExtensions = null ;
        if (!$this->wiki->UserIsAdmin()) {
            return $this->render('@templates/alert-message.twig', [
                'type'=>'danger',
                'message'=> get_class($this)." : " . _t('BAZ_NEED_ADMIN_RIGHTS')
            ]) ;
        }
        if (!is_writable('wakka.config.php')) {
            return $this->render('@templates/alert-message.twig', [
                'type'=>'danger',
                'message'=> _t('ERROR_NO_ACCESS'). ' '._t('FILE_WRITE_PROTECTED')
            ]) ;
        }

        include_once 'tools/templates/libs/Configuration.php';

        $output = '';
        if ($this->arguments['saving']) {
            $this->save();
        }
        if ($this->arguments['saved']) {
            $output .= $this->render('@templates/alert-message.twig', [
                'type'=>'info',
                'message'=> _t('EDIT_CONFIG_SAVE')
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
     * return array [$keys,$associatedExtensions]
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
                                foreach ($keysToMerge as $key) {
                                    if (is_array($key)) {
                                        foreach ($key as $keyname => $values) {
                                            foreach ($values as $value) {
                                                $associatedExtensions[$keyname.'['.$value.']'] = $extensionName;
                                            }
                                        }
                                    } else {
                                        $associatedExtensions[$key] = $extensionName;
                                    }
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
        return [$this->keys,$this->associatedExtensions];
    }

    /**
     * save data to wakka.config.php
     * @return string|null message to display at the top of the part for editing
     */
    private function save(): ?string
    {
        $config = new Configuration('wakka.config.php');
        $config->load();

        foreach ($this->getAuthorizedKeys()[0] as $key) {
            // some keys could be arrays
            if (is_array($key)) {
                foreach ($key as $firstLevelKey => $secondLevelKeys) {
                    foreach ($secondLevelKeys as $secondLevelKey) {
                        $new_value = $this->arguments['post'][$firstLevelKey][$secondLevelKey] ??  null;
                        if (is_null($new_value) || $new_value === '') {
                            if (isset($config->$firstLevelKey[$secondLevelKey])) {
                                $tmp = $config->$firstLevelKey;
                                unset($tmp[$secondLevelKey]);
                                if (empty($tmp)) {
                                    unset($config->$firstLevelKey);
                                } else {
                                    $config->$firstLevelKey = $tmp;
                                }
                            }
                        } else {
                            if (isset($config->$firstLevelKey) && is_array($config->$firstLevelKey)) {
                                $config->$firstLevelKey = array_merge($config->$firstLevelKey, [$secondLevelKey => $this->strtoarray($new_value)]);
                            } else {
                                $config->$firstLevelKey = [$secondLevelKey => $this->strtoarray($new_value)];
                            }
                        }
                    }
                }
            } else {
                $new_value = $this->arguments['post'][$key] ??  null;
                if (is_null($new_value) || $new_value === '') {
                    unset($config->$key);
                } else {
                    $config->$key = $this->strtoarray($new_value);
                }
            }
        }

        $config->write();
        $this->wiki->Redirect($this->wiki->Href('', '', [self::SAVED_NAME => "1"], false)) ;
    }

    /**
     * get data from config file
     * @return array [$data,$placeholders,$associatedExtensions] format ['name' => string $value,'name2'=> "['ee'=>'yy',...]"]
     */
    private function getDataFromConfigFile(): array
    {
        $config = new Configuration('wakka.config.php');
        $config->load();

        $data = [];
        $placeholders = [];
        list($keys, $associatedExtensions) = $this->getAuthorizedKeys();
        foreach ($keys as $key) {
            // some keys could be arrays
            if (is_array($key)) {
                foreach ($key as $firstLevelKey => $secondLevelKeys) {
                    foreach ($secondLevelKeys as $secondLevelKey) {
                        if (isset($config->$firstLevelKey[$secondLevelKey])) {
                            $data[$firstLevelKey.'['.$secondLevelKey.']'] = $this->array2Str($config->$firstLevelKey[$secondLevelKey]);
                        } else {
                            $data[$firstLevelKey.'['.$secondLevelKey.']'] = '';
                        }
                        if ($this->params->has($firstLevelKey) && isset($this->params->get($firstLevelKey)[$secondLevelKey])) {
                            $placeholders[$firstLevelKey.'['.$secondLevelKey.']'] = $this->array2Str($this->params->get($firstLevelKey)[$secondLevelKey]);
                        }
                    }
                }
            } else {
                if (isset($config->$key)) {
                    $data[$key] = $this->array2Str($config->$key);
                } else {
                    $data[$key] = '';
                }
                if ($this->params->has($key)) {
                    $placeholders[$key] = $this->array2Str($this->params->get($key));
                }
            }
        }
        return [$data,$placeholders,$associatedExtensions];
    }

    /**
     * array to string
     * @param mixed $value
     * @return string
     */
    private function array2Str($value): string
    {
        if (is_array($value)) {
            if (count($value) > 0 && count(array_diff_key($value, array_fill(0, count($value), " "))) == 0) {
                $value = '['
                    .implode(
                        ',',
                        array_map(function ($k, $v) {
                            return (($v === false) ? "false" : (($v=== true) ? "true" : "'".$v."'"));
                        }, array_keys($value), array_values($value))
                    )
                    .']';
            } else {
                $value = '['
                    .implode(
                        ',',
                        array_map(function ($k, $v) {
                            return "'".$k."' => ". (($v === false) ? "false" : (($v=== true) ? "true" : "'".$v."'"));
                        }, array_keys($value), array_values($value))
                    )
                    .']';
            }
        } elseif (!is_string($value)) {
            try {
                $value = (($value === false) ? "false" : (($value=== true) ? "true" : strval($value)));
            } catch (\Throwable $th) {
                $value = '';
            }
        }
        return $value;
    }

    /**
     * string to array if needed
     * @param string $value
     * @return mixed
     */
    private function strtoarray(string $value)
    {
        $val = trim($value);
        $matches = [];
        if (preg_match('/^\s*\[\s*(.*)\s*\]\s*$/', $val, $matches)) {
            $val = $matches[1];
            $lines= preg_split('/(?<=\'|"|true|false|[0-9])\s*,\s*(?=\'|"|true|false|[0-9])/', $val);
            $result = [];
            foreach ($lines as $line) {
                $extract = explode('=>', $line);
                if (in_array(count($extract), [1,2])) {
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
     * get help from translation
     * @return array
     */
    private function getHelp(): array
    {
        $help = [];
        foreach ($this->getAuthorizedKeys()[0] as $key) {
            if (!is_array($key)) {
                if (isset($GLOBALS['translations']['EDIT_CONFIG_HINT_'.$key])) {
                    $help[$key] = _t('EDIT_CONFIG_HINT_'.$key);
                } elseif (isset($GLOBALS['translations']['EDIT_CONFIG_HINT_'.strtoupper($key)])) {
                    $help[$key] = _t('EDIT_CONFIG_HINT_'.strtoupper($key));
                }
            } else {
                foreach ($key as $firstLevelKey => $secondLevelKeys) {
                    foreach ($secondLevelKeys as $secondLevelKey) {
                        $hintKey = 'EDIT_CONFIG_HINT_'.$firstLevelKey.'['.$secondLevelKey.']';
                        if (isset($GLOBALS['translations'][$hintKey])) {
                            $help[$firstLevelKey.'['.$secondLevelKey.']'] = _t($hintKey);
                        } elseif (isset($GLOBALS['translations'][strtoupper($hintKey)])) {
                            $help[$firstLevelKey.'['.$secondLevelKey.']'] = _t(strtoupper($hintKey));
                        }
                    }
                }
            }
        }

        return $help;
    }
}
