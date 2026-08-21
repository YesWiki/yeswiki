<?php

namespace YesWiki\Admin\Entity;

use YesWiki\Content\Entity\Files;
use YesWiki\Kernel\Service\ConfigurationService;

class Repository extends PackageCollection
{
    public const INDEX_FILENAME = 'packages.json';

    private $address;
    private $fileHandler;

    public function __construct($address, string $requestedVersion = '', ?ConfigurationService $configurationService = null)
    {
        $this->address = $address . '/';
        $this->fileHandler = new Files();
        $this->requestedVersion = $requestedVersion;
        $this->configurationService = $configurationService;
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
