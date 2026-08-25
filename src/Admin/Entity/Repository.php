<?php

namespace YesWiki\Admin\Entity;

use YesWiki\Admin\Service\PackageTree;
use YesWiki\Kernel\Service\ConfigurationService;

class Repository extends PackageCollection
{
    public const INDEX_FILENAME = 'packages.json';

    /** @var string base URL of the repository, with a trailing slash */
    private $address;

    /** @var PackageTree */
    private $fileHandler;

    public function __construct(string $address, string $requestedVersion = '', ?ConfigurationService $configurationService = null)
    {
        $this->address = $address . '/';
        $this->fileHandler = new PackageTree();
        $this->requestedVersion = $requestedVersion;
        $this->configurationService = $configurationService;
    }

    /** @return bool false when the index could not be reached or did not read as a package list */
    public function load(): bool
    {
        $this->list = [];

        if (filter_var($this->address, FILTER_VALIDATE_URL) === false) {
            return false;
        }
        $repoInfosFile = $this->address . $this::INDEX_FILENAME;
        $file = $this->fileHandler->download($repoInfosFile);
        $json = $this->fileHandler->read($file);

        $this->fileHandler->remove($file);

        if ($json === '') {
            return false;
        }
        $data = json_decode($json, true);
        if (!is_array($data)) {
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
