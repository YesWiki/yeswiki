<?php

namespace YesWiki;

class Plugins
{
    public ?string $location = null;
    public string $type;
    /** @var array<string, array<string, mixed>> */
    public $p_list = [];

    /**
     * @param string $location
     * @param string $type
     */
    public function __construct($location, $type = 'plugin')
    {
        if (is_dir($location)) {
            $this->location = $location . '/';
        } else {
            $this->location = null;
        }

        $this->type = $type;
    }

    /**
     * @param bool $active_only
     */
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
        }

        return false;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getPluginsList()
    {
        return $this->p_list;
    }

    /**
     * @return array<string, string>|false the desc.xml path of every plugin found, or false when there is no directory to read
     */
    public function _readDir()
    {
        if ($this->location === null) {
            return false;
        }

        $res = [];

        $d = dir($this->location);
        if ($d === false) {
            return false;
        }

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

    /**
     * @param string $p path to the plugin's desc.xml
     *
     * @return array<string, mixed>|false the plugin description, or false when the file says nothing usable
     */
    public function getPluginInfo($p)
    {
        if (file_exists($p)) {
            $xml = simplexml_load_file($p);
            $encoded = json_encode($xml);
            $xml = $encoded === false ? null : json_decode($encoded, true);
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
            }

            return false;
        }

        return false;
    }
}
