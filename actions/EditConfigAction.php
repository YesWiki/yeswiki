<?php

use YesWiki\Core\YesWikiAction;
use YesWiki\Core\Service\Performer;

class EditConfigAction extends YesWikiAction
{
    private const SAVE_NAME = 'save_config';
    private const CONFIG_POSTFIX = '_editable_config_params';
    private const AUTHORIZED_KEYS = [
        'wakka_name',
        'root_page',
        'meta_keywords',
        'meta_description',
        'meta',

        'default_read_acl',
        'default_write_acl',

        'password_for_editing',
        'password_for_editing_message',

        'debug',
        'default_language',

        'contact_from',
        'mail_custom_message',
        'bazarIgnoreAcls',
    ];

    private $keys ;

    public function formatArguments($arg)
    {
        return [
            'saving' => $this->formatBoolean($_POST, false, self::SAVE_NAME),
            'post' => $_POST,
        ];
    }

    public function run()
    {
        $this->keys = null ;
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
            $output .= $this->save();
        }

        // display form
        list($data, $placeholders) = $this->getDataFromConfigFile();
        return $output . $this->render('@templates/edit-config.twig', [
            'SAVE_NAME' => self::SAVE_NAME,
            'data' => $data,
            'placeholders' => $placeholders,
            'help' => $this->getHelp(),
        ]);
    }

    /**
     * get AUTHORIZED_KEYS
     * return array
     */
    private function getAuthorizedKeys(): array
    {
        if (is_null($this->keys)) {
            $keys = self::AUTHORIZED_KEYS;
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
                            } elseif (is_string($keysToMerge)) {
                                $keys[] = $keysToMerge;
                            }
                        }
                    }
                }
            }
            // remove duplicate
            $this->keys = array_unique($keys);
        }
        return $this->keys;
    }

    /**
     * save data to wakka.config.php
     * @return string|null message to display at the top of the part for editing
     */
    private function save(): ?string
    {
        $config = new Configuration('wakka.config.php');
        $config->load();

        foreach ($this->getAuthorizedKeys() as $key) {
            // some keys could be arrays
            $k = explode('[', $key);
            if (isset($k[1])) {
                $firstLevel = $k[0];
                $secondLevel = str_replace(']', '', $k[1]);
                $new_value = $this->arguments['post'][$firstLevel][$secondLevel] ??  null;
                if (empty($new_value)) {
                    unset($config->$key);
                } else {
                    if (is_array($config->$firstLevel)) {
                        $config->$firstLevel = array_merge($config->$firstLevel, [$secondLevel => $this->strtoarray($new_value)]);
                    } else {
                        $config->$firstLevel = [$secondLevel => $this->strtoarray($new_value)];
                    }
                }
            } else {
                $new_value = $this->arguments['post'][$key] ??  null;
                if (empty($new_value)) {
                    unset($config->$key);
                } else {
                    $config->$key = $this->strtoarray($new_value);
                }
            }
        }

        $config->write();
        return $this->render('@templates/alert-message.twig', [
            'type'=>'info',
            'message'=> _t('EDIT_CONFIG_SAVE')
        ]);
    }

    /**
     * get data from config file
     * @return array [$data,$placeholders] format ['name' => string $value,'name2'=> "['ee'=>'yy',...]"]
     */
    private function getDataFromConfigFile(): array
    {
        $config = new Configuration('wakka.config.php');
        $config->load();

        $data = [];
        $placeholders = [];
        foreach ($this->getAuthorizedKeys() as $key) {
            // some keys could be arrays
            $k = explode('[', $key);
            if (isset($k[1])) {
                $firstLevel = $k[0];
                $secondLevel = str_replace(']', '', $k[1]);
                if (isset($config->$firstLevel[$secondLevel])) {
                    $data[$key] = $this->array2Str($config->$firstLevel[$secondLevel]);
                } else {
                    $data[$key] = '';
                }
            } else {
                if (isset($config->$key)) {
                    $data[$key] = $this->array2Str($config->$key);
                } else {
                    $data[$key] = '';
                }
            }
            if ($this->params->has($key)) {
                $placeholders[$key] = $this->array2Str($this->params->get($key));
            }
        }
        return [$data,$placeholders];
    }

    /**
     * array to string
     * @param mixed $value
     * @return string
     */
    private function array2Str($value): string
    {
        if (is_array($value)) {
            $value = '['
                .implode(
                    ',',
                    array_map(function ($k, $v) {
                        return "'".$k."' => ". (($v === false) ? "false" : (($v=== true) ? "true" : "'".$v."'"));
                    }, array_keys($value), array_values($value))
                )
                .']';
        } elseif (!is_string($value)) {
            try {
                $value = strval($value);
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
                if (count($extract) == 2) {
                    $key = trim($extract[0]);
                    if (preg_match('/^\s*(?:\'|")\s*(.*)\s*(?:\'|")\s*$/', $key, $matches)) {
                        $key = $matches[1];
                    }
                    $val = trim($extract[1]);
                    if (preg_match('/^\s*(?:\'|")\s*(.*)\s*(?:\'|")\s*$/', $val, $matches)) {
                        $val = $matches[1];
                    }
                    $val = ($val == 'true') ? true : (($val == 'false') ? false : $val);
                    $result[$key] = $val;
                }
            }
            if (count($result) > 0) {
                return $result;
            }
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
        foreach ($this->getAuthorizedKeys() as $key) {
            if (isset($GLOBALS['translations']['EDIT_CONFIG_HINT_'.$key])) {
                $help[$key] = _t('EDIT_CONFIG_HINT_'.$key);
            }
        }

        return $help;
    }
}
