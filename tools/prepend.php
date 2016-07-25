<?php

// Vérification de sécurité
if (! defined('WIKINI_VERSION')) {
    die('acc&egrave;s direct interdit');
}

// Meme nom : remplace
// _Meme nom : avant
// Meme nom : _apres

require_once ('libs/class.plugins.php');

class WikiTools extends Wiki
{

    function Format($text, $formatter = 'wakka')
    {
        return $this->IncludeBuffered($formatter . '.php', "<i>Impossible de trouver le formateur \"$formatter\"</i>", compact("text"), $this->config['formatter_path']);
    }

    function IncludeBuffered($filename, $notfoundText = '', $vars = '', $path = '')
    {
        if ($path)
            $dirs = explode(':', $path);
        else
            $dirs = array(
                ''
            );
        
        $included['before'] = array();
        $included['new'] = array();
        $included['after'] = array();
        
        foreach ($dirs as $dir) {
            if ($dir)
                $dir .= '/';
            $fullfilename = $dir . $filename;
            if (strstr($filename, 'page/')) {
                list ($file, $extension) = explode('page/', $filename);
                $beforefullfilename = $dir . $file . 'page/__' . $extension;
            } else {
                $beforefullfilename = $dir . '__' . $filename;
            }
            
            list ($file, $extension) = explode('.', $filename);
            $afterfullfilename = $dir . $file . '__.' . $extension;
            
            if (file_exists($beforefullfilename)) {
                $included['before'][] = $beforefullfilename;
            }
            
            if (file_exists($fullfilename)) {
                $included['new'][] = $fullfilename;
            }
            
            if (file_exists($afterfullfilename)) {
                $included['after'][] = $afterfullfilename;
            }
        }
        
        $plugin_output_new = '';
        $found = 0;
        
        if (is_array($vars))
            extract($vars);
        
        foreach ($included['before'] as $before) {
            $found = 1;
            ob_start();
            include ($before);
            $plugin_output_new .= ob_get_contents();
            ob_end_clean();
        }
        foreach ($included['new'] as $new) {
            $found = 1;
            ob_start();
            require ($new);
            $plugin_output_new = ob_get_contents();
            ob_end_clean();
            break;
        }
        foreach ($included['after'] as $after) {
            $found = 1;
            ob_start();
            include ($after);
            $plugin_output_new .= ob_get_contents();
            ob_end_clean();
        }
        if ($found)
            return $plugin_output_new;
        if ($notfoundText)
            return $notfoundText;
        else
            return false;
    }

    /**
     * Retrieves the list of existing actions
     *
     * @return array An unordered array of all the available actions.
     */
    function GetActionsList()
    {
        $action_path = $this->GetConfigValue('action_path');
        $dirs = explode(":", $action_path);
        $list = array();
        foreach ($dirs as $dir) {
            if ($dh = opendir($dir)) {
                while (($file = readdir($dh)) !== false) {
                    if (preg_match('/^([a-zA-Z-0-9]+)(.class)?.php$/', $file, $matches)) {
                        $list[] = $matches[1];
                    }
                }
            }
        }
        
        return array_unique($list);
    }

    /**
     * Retrieves the list of existing handlers
     *
     * @return array An unordered array of all the available handlers.
     */
    function GetHandlersList()
    {
        $handler_path = $this->GetConfigValue('handler_path');
        $dirs = explode(":", $handler_path);
        $list = array();
        foreach ($dirs as $dir) {
            $dir .= '/page';
            if ($dh = opendir($dir)) {
                while (($file = readdir($dh)) !== false) {
                    if (preg_match('/^([a-zA-Z-0-9]+)(.class)?.php$/', $file, $matches)) {
                        $list[] = $matches[1];
                    }
                }
            }
        }
        return array_unique($list);
    }
}

$plugins_root = 'tools/';

$objPlugins = new plugins($plugins_root);
$objPlugins->getPlugins(true);
$plugins_list = $objPlugins->getPluginsList();

$wakkaConfig['formatter_path'] = 'formatters';
$wikiClasses[] = 'WikiTools';
$wikiClassesContent[] = '';

foreach ($plugins_list as $k => $v) {
    
    $pluginBase = $plugins_root . $k . '/';
    
    if (file_exists($pluginBase . 'wiki.php')) {
        include ($pluginBase . 'wiki.php');
    }
    
    // language files : first default language, then preferred language
    if (file_exists($pluginBase . 'lang/' . $k . '_fr.inc.php')) {
        include ($pluginBase . 'lang/' . $k . '_fr.inc.php');
    }
    if ($GLOBALS['prefered_language'] != 'fr' && file_exists($pluginBase . 'lang/' . $k . '_' . $GLOBALS['prefered_language'] . '.inc.php')) {
        include ($pluginBase . 'lang/' . $k . '_' . $GLOBALS['prefered_language'] . '.inc.php');
    }
    
    if (file_exists($pluginBase . 'actions')) {
        $wakkaConfig['action_path'] = $pluginBase . 'actions/' . ':' . $wakkaConfig['action_path'];
    }
    if (file_exists($pluginBase . 'handlers')) {
        $wakkaConfig['handler_path'] = $pluginBase . 'handlers/' . ':' . $wakkaConfig['handler_path'];
    }
    if (file_exists($pluginBase . 'formatters')) {
        $wakkaConfig['formatter_path'] = $pluginBase . 'formatters/' . ':' . $wakkaConfig['formatter_path'];
    }
}

for ($iw = 0; $iw < count($wikiClasses); $iw ++) {
    if ($wikiClasses[$iw] != 'WikiTools') {
        eval('Class ' . $wikiClasses[$iw] . ' extends ' . $wikiClasses[$iw - 1] . ' { ' . $wikiClassesContent[$iw] . ' }; ');
    }
}

// $wiki = new WikiTools($wakkaConfig);
eval('$wiki  = new ' . $wikiClasses[count($wikiClasses) - 1] . '($wakkaConfig);');

