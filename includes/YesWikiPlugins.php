<?php
namespace YesWiki;

# ***** BEGIN LICENSE BLOCK *****
# This file is part of DotClear.
# Copyright (c) 2004 Olivier Meunier and contributors. All rights
# reserved.
#
# DotClear is free software; you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation; either version 2 of the License, or
# (at your option) any later version.
#
# DotClear is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with DotClear; if not, write to the Free Software
# Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
#
# ***** END LICENSE BLOCK *****

/*
Classe de gestion des plugins et des thémes
*/

class Plugins
{
    public $location;
    public $type;
    public $_xml;
    public $p_list = array();

    public function __construct($location, $type = 'plugin')
    {
        if (is_dir($location)) {
            $this->location = $location.'/';
        } else {
            $this->location = null;
        }

        $this->type = $type;
    }

    public function getPlugins($active_only = true)
    {
        if (($list_files = $this->_readDir()) !== false) {
            $this->p_list = array();
            foreach ($list_files as $entry => $pfile) {
                if (($info = $this->getPluginInfo($pfile)) !== false) {
                    if (($active_only && $info['active']) || !$active_only) {
                        $this->p_list[$entry] = $info;
                    }
                }
            }
            ksort($this->p_list);

            return true;
        } else {
            return false;
        }
    }

    public function getPluginsList()
    {
        return $this->p_list;
    }

    /* Lecture d'un répertoire é la recherche des desc.xml */
    public function _readDir()
    {
        if ($this->location === null) {
            return false;
        }

        $res = array();

        $d = dir($this->location);

        // Liste du répertoire des plugins
        while (($entry = $d->read()) !== false) {
            if ($entry != '.' && $entry != '..'
                && is_dir($this->location.$entry)
                && file_exists($this->location.$entry.'/desc.xml')
            ) {
                $res[$entry] = $this->location.$entry.'/desc.xml';
            }
        }

        return $res;
    }

    public function getPluginInfo($p)
    {
        if (file_exists($p)) {
            $this->_current_tag_cdata = '';
            $this->_p_info = array('name' => null, 'version' => null,
                        'active' => null, 'author' => null, 'label' => null,
                        'desc' => null, 'callbacks' => array(), );

            $this->_xml = xml_parser_create('ISO-8859-1');
            xml_parser_set_option($this->_xml, XML_OPTION_CASE_FOLDING, false);
            xml_set_object($this->_xml, $this);
            xml_set_element_handler($this->_xml, 'openTag', 'closeTag');
            xml_set_character_data_handler($this->_xml, 'cdata');

            xml_parse($this->_xml, implode('', file($p)));
            xml_parser_free($this->_xml);

            if (!empty($this->_p_info['name'])) {
                return $this->_p_info;
            } else {
                return false;
            }
        }
    }

    public function openTag($p, $tag, $attr)
    {
        if ($tag == $this->type && !empty($attr['name'])) {
            $this->_p_info['name'] = $attr['name'];
            $this->_p_info['version'] = (!empty($attr['version'])) ? $attr['version'] : null;
            $this->_p_info['active'] = (!empty($attr['active'])) ? (boolean) $attr['active'] : false;
        }

        if ($tag == 'callback') {
            $this->_p_info['callbacks'][] = array($attr['event'], $attr['function']);
        }
    }

    public function closeTag($p, $tag)
    {
        switch ($tag) {
            case 'author':
            case 'label':
            case 'desc':
                $this->_p_info[$tag] = $this->_current_tag_cdata;
                break;
        }
    }

    public function cdata($p, $cdata)
    {
        $this->_current_tag_cdata = $cdata;
    }
}

class WikiTools extends \YesWiki\Wiki
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