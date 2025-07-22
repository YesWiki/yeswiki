<?php

namespace YesWiki;

/*
Classe de gestion des plugins et des thémes
*/

class Plugins
{
    public $location;
    public $type;
    public $p_list = [];

    public function __construct($location, $type = 'plugin')
    {
        if (is_dir($location)) {
            $this->location = $location . '/';
        } else {
            $this->location = null;
        }

        $this->type = $type;
    }

    public function getPlugins($active_only = true): bool
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
            $xml = simplexml_load_file($p);
            $xml = json_decode(json_encode($xml), true);
            if (!empty($xml['@attributes']['name'])) {
                return [
                    'name' => $xml['@attributes']['name'] ?? null,
                    'version' => $xml['@attributes']['version'] ?? null,
                    'active' => $xml['@attributes']['active'] ?? null,
                    'author' => $xml['author'] ?? null,
                    'label' => $xml['label'] ?? null,
                    'desc' => $xml['desc'] ?? null,
                    'callbacks' => [],
                ];
            } else {
                return false;
            }
        }

        return false;
    }
}
