<?php

use YesWiki\Core\YesWikiAction;
use YesWiki\Core\Service\Performer;

class EditWakkaConfigAction extends YesWikiAction
{
    private const SAVE_NAME = 'save_wakka';
    private const AUTHORIZED_KEYS_HINT = [
        'wakka_name',
        'root_page',
        'meta_keywords',
        'meta_description',
        'meta',

        'default_read_acl',
        'default_write_acl',

        'use_alerte',
        'use_captcha',
        'password_for_editing',
        'password_for_editing_message',

        'baz_map_center_lat',
        'baz_map_center_lon',
        'baz_map_zoom',
        'baz_map_height',

        'debug',
        'default_language',

        'contact_from',
        'BAZ_ADRESSE_MAIL_ADMIN',
        'BAZ_ENVOI_MAIL_ADMIN',
        'mail_custom_message',
        'bazarIgnoreAcls',
    ];

    public function formatArguments($arg)
    {
        return [
            'saving' => $this->formatBoolean($_POST, false, self::SAVE_NAME),
            'post' => $_POST,
        ];
    }

    public function run()
    {
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
        return $output . $this->render('@templates/edit-wakka-config.twig', [
            'SAVE_NAME' => self::SAVE_NAME,
            'data' => $data,
            'placeholders' => $placeholders,
            'help' => $this->getHelp(),
        ]);
    }

    /**
     * save data to wakka.config.php
     * @return string|null message to display at the top of the part for editing
     */
    private function save():?string
    {
        $config = new Configuration('wakka.config.php');
        $config->load();
        var_dump($config);

        foreach (self::AUTHORIZED_KEYS_HINT as $key) {
            $new_value = $this->arguments['post'][$key] ??  null;

            if (empty($new_value)) {
                unset($config->$key);
            } else {
                $config->$key = $this->strtoarray($new_value);
            }
        }

        $config->write();
        return $this->render('@templates/alert-message.twig', [
            'type'=>'info',
            'message'=> _t('EDIT_WAKKA_CONFIG_SAVE')
        ]);
    }

    /**
     * get data from config file
     * @return array [$data,$placeholders] format ['name' => string $value,'name2'=> "['ee'=>'yy',...]"]
     */
    private function getDataFromConfigFile():array
    {
        $config = new Configuration('wakka.config.php');
        $config->load();

        $data = [];
        $placeholders = [];
        foreach (self::AUTHORIZED_KEYS_HINT as $key) {
            if (isset($config->$key)) {
                $data[$key] = $this->array2Str($config->$key);
            } else {
                $data[$key] = '';
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
    private function array2Str($value):string
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
        if (preg_match('/^\s*\[\s*(.*)\s*\]\s*$/',$val,$matches)){
            $val = $matches[1];
            $lines= preg_split('/(?<=\'|"|true|false|[0-9])\s*,\s*(?=\'|"|true|false|[0-9])/',$val);
            $result = [];
            foreach($lines as $line){
                $extract = explode('=>',$line);
                if (count($extract) == 2){
                    $key = trim($extract[0]);
                    if (preg_match('/^\s*(?:\'|")\s*(.*)\s*(?:\'|")\s*$/',$key,$matches)){
                        $key = $matches[1];
                    }
                    $val = trim($extract[1]);
                    if (preg_match('/^\s*(?:\'|")\s*(.*)\s*(?:\'|")\s*$/',$val,$matches)){
                        $val = $matches[1];
                    }
                    $val = ($val == 'true') ? true : (($val == 'false') ? false : $val );
                    $result[$key] = $val;
                }
            }
            if (count($result) > 0){
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
        foreach (self::AUTHORIZED_KEYS_HINT as $key) {
            if (isset($GLOBALS['translations']['EDIT_WAKKA_CONFIG_HINT_'.$key])) {
                $help[$key] = _t('EDIT_WAKKA_CONFIG_HINT_'.$key);
            }
        }

        return $help;
    }
}
