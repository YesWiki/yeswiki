<?php

namespace YesWiki\AutoUpdate\Entity;

class Repository extends PackageCollection
{
    public const INDEX_FILENAME = 'packages.json';

    private $address;
    private $fileHandler;

    public function __construct($address)
    {
        $this->address = $address . '/';
        $this->fileHandler = new Files();
    }

    public function load()
    {
        $this->list = [];

        if (filter_var($this->address, FILTER_VALIDATE_URL) === false) {
            return false;
        }
        $repoInfosFile = $this->address . $this::INDEX_FILENAME;
        $file = $this->fileHandler->download($repoInfosFile);
        $data = json_decode(file_get_contents($file), true);
        // release tmp file
        unlink($file);

        if (is_null($data)) {
            return false;
        }

        foreach ($data as $packageInfos) {
            if (!isset($packageInfos['description'])) {
                $packageInfos['description'] = _t('AU_NO_DESCRIPTION');
            }
            $release = new Release($packageInfos['version']);
            $this->add(
                $release,
                $this->address,
                $packageInfos['file'],
                $packageInfos['description'],
                $packageInfos['documentation'],
                $packageInfos['minimal_php_version'] ?? null
            );
        }

        return true;
    }
}
