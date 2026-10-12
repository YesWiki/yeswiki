<?php

namespace YesWiki\AutoUpdate\Service;

use YesWiki\AutoUpdate\Entity\Messages;
use YesWiki\AutoUpdate\Entity\PackageCollection;
use YesWiki\AutoUpdate\Entity\PackageTool;
use YesWiki\AutoUpdate\Entity\Repository;
use YesWiki\Wiki;

class AutoUpdateService
{
    public const DEFAULT_REPO = 'https://repository.yeswiki.net/';
    public const DEFAULT_VERS = 'Cercopitheque';
    public $repository;

    private $wiki;

    public function __construct(
        Wiki $wiki
    ) {
        $this->wiki = $wiki;
    }

    /** Loads the repository of the requested YesWiki version, or of this wiki's version when none is requested. */
    public function initRepository($requestedVersion = '')
    {
        $this->repository = new Repository($this->repositoryAddress($requestedVersion));

        return $this->repository->load();
    }

    private function repositoryAddress($requestedVersion = '')
    {
        $repositoryAddress = $this::DEFAULT_REPO;

        if (isset($this->wiki->config['yeswiki_repository'])) {
            $repositoryAddress = $this->wiki->config['yeswiki_repository'];
        }

        if (substr($repositoryAddress, -1, 1) !== '/') {
            $repositoryAddress .= '/';
        }

        if ($requestedVersion != '') {
            $repositoryAddress .= strtolower($requestedVersion);
        } else {
            $repositoryAddress .= $this->getYesWikiVersion();
        }

        return $repositoryAddress;
    }

    private function getYesWikiVersion()
    {
        $version = $this::DEFAULT_VERS;
        if (isset($this->wiki->config['yeswiki_version'])) {
            $version = $this->wiki->config['yeswiki_version'];
        }

        return strtolower($version);
    }

    public function delete($packageName)
    {
        $messages = new Messages();
        $package = $this->repository->getPackage($packageName);

        $vDeleteStatus = $package->deletePackage();

        if ($vDeleteStatus !== true) {
            $messages[] = ['text' => (_t('AU_DELETE') . ' - ' . _t('AU_ERROR') . '\n' . _t('AU_UNABLE_TO_REMOVE_FILES') . implode('\n', $vDeleteStatus)), 'status' => _t('AU_ERROR')];

            return $messages;
        }
        $messages->add('AU_DELETE', 'AU_OK');

        return $messages;
    }

    /** Extensions this wiki's configuration forbids installing or upgrading, from `forbidden_extensions`. */
    public function forbiddenExtensions(): array
    {
        $listed = $this->wiki->config['forbidden_extensions'] ?? [];

        return is_array($listed) ? array_values(array_diff(array_map('strval', $listed), ['yeswiki'])) : [];
    }

    public function upgrade($packageName)
    {
        $messages = new Messages();
        $package = $this->repository->getPackage($packageName);
        if ($package instanceof PackageTool && in_array($package->name, $this->forbiddenExtensions(), true)) {
            $messages[] = ['text' => _t('AU_FORBIDDEN_EXTENSION', ['name' => $package->name]), 'status' => _t('AU_ERROR')];

            return $messages;
        }

        $file = $package ? $package->getFile() : false;
        if (false === $file) {
            $messages->add('AU_DOWNLOAD', 'AU_ERROR');

            return $messages;
        }
        $messages->add('AU_DOWNLOAD', 'AU_OK');

        if (!$package->checkIntegrity($file)) {
            $messages->add('AU_INTEGRITY', 'AU_ERROR');
            $package->cleanTempFiles();

            return $messages;
        }
        $messages->add('AU_INTEGRITY', 'AU_OK');

        $path = $package->extract();
        if (false === $path) {
            $messages->add('AU_EXTRACT', 'AU_ERROR');
            $package->cleanTempFiles();

            return $messages;
        }
        $messages->add('AU_EXTRACT', 'AU_OK');

        $vNotGoods = $package->checkACL();

        if (count($vNotGoods) > 0) {
            $vMaxLength = 100;
            $vFilesList = implode(', ', $vNotGoods);

            $messages[] = ['text' => (_t('AU_ACL') . '\n' . _t('AU_NOT_WRITABLE_FILES') . substr($vFilesList, 0, 100) . (strlen($vFilesList) > $vMaxLength ? '...' : '')), 'status' => _t('AU_ERROR')];

            $package->cleanTempFiles();

            return $messages;
        }
        $messages->add('AU_ACL', 'AU_OK');

        if (!$package->upgrade()) {
            $messages->add(
                _t('AU_UPDATE_PACKAGE') . $packageName,
                'AU_ERROR'
            );
            $package->cleanTempFiles();

            return $messages;
        }
        $messages->add(_t('AU_UPDATE_PACKAGE') . $packageName, 'AU_OK');

        if (get_class($package) === PackageCollection::CORE_CLASS) {
            if (!$package->upgradeDefaultTheme()) {
                $messages->add('AU_UPDATE_THEME', 'AU_ERROR');
                $package->cleanTempFiles();

                return $messages;
            }
            $messages->add('AU_UPDATE_THEME', 'AU_OK');

            if (!$package->upgradeTools()) {
                $messages->add('AU_UPDATE_TOOL', 'AU_ERROR');
                $package->cleanTempFiles();

                return $messages;
            }
            $messages->add('AU_UPDATE_TOOL', 'AU_OK');
        }

        if (!$package->upgradeInfos()) {
            $messages->add('AU_UPDATE_INFOS', 'AU_ERROR');
            $package->cleanTempFiles();

            return $messages;
        }
        $messages->add('AU_UPDATE_INFOS', 'AU_OK');

        $package->cleanTempFiles();

        return $messages;
    }
}
