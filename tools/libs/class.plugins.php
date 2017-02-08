<?php

# namespace YesWiki;

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
                if (($info = $this->_getPluginInfo($pfile)) !== false) {
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

    public function getFunctions($f = 'functions.php')
    {
        $res = array();

        if (($list_files = $this->_readDir()) !== false) {
            foreach ($list_files as $entry => $pfile) {
                if (file_exists(dirname($pfile).'/'.$f)) {
                    $res[] = dirname($pfile).'/'.$f;
                }
            }
        }

        return $res;
    }

    public function loadCallbacks()
    {
        $res['onPost'] = array();

        $ires = array_keys($res);

        foreach ($this->p_list as $k => $v) {
            # Chargement des fichiers events.php

            if (file_exists($this->location.$k.'/events.php')) {
                require_once $this->location.$k.'/events.php';

                foreach ($v['callbacks'] as $f) {
                    if (in_array($f[0], $ires)) {
                        $pf = explode('::', $f[1]);
                        if (count($pf) == 2 && is_callable($pf)) {
                            $res[$f[0]][] = $pf;
                        }
                    }
                }
            }
        }

        return $res;
    }

    public function loadl10n($p)
    {
        if (defined('DC_LANG')) {
            if (dc_encoding == 'UTF-8') {
                l10n::set($this->location.$p.'/l10n/'.DC_LANG.'-utf8/main');
            } else {
                l10n::set($this->location.$p.'/l10n/'.DC_LANG.'/main');
            }
        }
    }

    public function switchStatus($p)
    {
        $xml_path = $this->location.$p.'/desc.xml';
        $p_info = $this->_getPluginInfo($xml_path);
        $xml = implode('', file($xml_path));

        $active = (integer) !$p_info['active'];

        $xml = preg_replace(
            '|(<'.$this->type.'[^>]*?active=)"([^"]+)([^>]*>)|ms',
            '$1"'.$active.'$3',
            $xml
        );

        if (!files::putContent($xml_path, $xml)) {
            return false;
        }

        return true;
    }

    /* Installation d'un plugin */
    public function install($url)
    {
        $dest = $this->location.'/'.basename($url);
        if ((!file_exists($dest)) && ($err = files::copyRemote($url, $dest) !== true)) {
            return $err;
        } else {
            if (($content = @implode('', @gzfile($dest))) === false) {
                return __('Cannot open file');
            } else {
                if (($list = unserialize($content)) === false) {
                    return __('Plugin not valid');
                } else {
                    if (is_dir($this->location.'/'.$list['name'])) {
                        /*if (files::deltree($this->location.'/'.$list['name']) === false)
                        {
                            return 'Impossible de supprimer le plugin existant';
                        }*/
                        unlink($dest);

                        return __('This plugin still exists. Delete it before.');
                    }

                    foreach ($list['dirs'] as $d) {
                        mkdir($this->location.'/'.$d, fileperms($this->location));
                        chmod($this->location.'/'.$d, fileperms($this->location));
                    }

                    foreach ($list['files'] as $f => $v) {
                        $v = base64_decode($v);
                        $fp = fopen($this->location.'/'.$f, 'w');
                        fwrite($fp, $v, strlen($v));
                        fclose($fp);
                        chmod($this->location.'/'.$f, fileperms($this->location) & ~0111);
                    }

                    unlink($dest);
                }
            }
        }

        return true;
    }

    /* Lecture d'un répertoire é la recherche des desc.xml */
    public function _readDir()
    {
        if ($this->location === null) {
            return false;
        }

        $res = array();

        $d = dir($this->location);

        # Liste du répertoire des plugins
        while (($entry = $d->read()) !== false) {
            if ($entry != '.' && $entry != '..' &&
            is_dir($this->location.$entry) && file_exists($this->location.$entry.'/desc.xml')) {
                $res[$entry] = $this->location.$entry.'/desc.xml';
            }
        }

        return $res;
    }

    public function _getPluginInfo($p)
    {
        if (file_exists($p)) {
            $this->_current_tag_cdata = '';
            $this->_p_info = array('name' => null, 'version' => null,
                        'active' => null, 'author' => null, 'label' => null,
                        'desc' => null, 'callbacks' => array(), );

            $this->_xml = xml_parser_create('ISO-8859-1');
            xml_parser_set_option($this->_xml, XML_OPTION_CASE_FOLDING, false);
            xml_set_object($this->_xml, $this);
            xml_set_element_handler($this->_xml, '_openTag', '_closeTag');
            xml_set_character_data_handler($this->_xml, '_cdata');

            xml_parse($this->_xml, implode('', file($p)));
            xml_parser_free($this->_xml);

            if (!empty($this->_p_info['name'])) {
                return $this->_p_info;
            } else {
                return false;
            }
        }
    }

    public function _openTag($p, $tag, $attr)
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

    public function _closeTag($p, $tag)
    {
        switch ($tag) {
            case 'author':
            case 'label':
            case 'desc':
                $this->_p_info[$tag] = $this->_current_tag_cdata;
                break;
        }
    }

    public function _cdata($p, $cdata)
    {
        $this->_current_tag_cdata = $cdata;
    }
}
