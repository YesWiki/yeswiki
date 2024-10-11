<?php

namespace YesWiki;

/*
Classe de gestion des plugins et des thémes
*/

class Plugins
{
    public $location;
    public $type;
    public $_xml;
    public $p_list = [];

    private $_current_tag_cdata;
    private $_p_info;

    public function __construct($location, $type = 'plugin')
    {
        if (is_dir($location)) {
            $this->location = $location . '/';
        } else {
            $this->location = null;
        }

        $this->type = $type;
    }

    public function getPlugins($active_only = true)
    {
        if (($list_files = $this->_readDir()) !== false) {
            $this->p_list = [];
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

        $res = [];

        $d = dir($this->location);

        // Liste du répertoire des plugins
        while (($entry = $d->read()) !== false) {
            if ($entry != '.' && $entry != '..'
                && is_dir($this->location . $entry)
                && file_exists($this->location . $entry . '/desc.xml')
            ) {
                $res[$entry] = $this->location . $entry . '/desc.xml';
            }
        }

        return $res;
    }

    public function getPluginInfo($p)
    {
        if (file_exists($p)) {
            $this->_current_tag_cdata = '';
            $this->_p_info = ['name' => null, 'version' => null,
                'active' => null, 'author' => null, 'label' => null,
                'desc' => null, 'callbacks' => [], ];

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
            $this->_p_info['active'] = (!empty($attr['active'])) ? (bool)$attr['active'] : false;
        }

        if ($tag == 'callback') {
            $this->_p_info['callbacks'][] = [$attr['event'], $attr['function']];
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
