<?php

namespace YesWiki\Admin\Service;

use Psr\Container\ContainerInterface;
use YesWiki\Admin\Entity\PackageCollection;
use YesWiki\Admin\Entity\Repository;
use YesWiki\Kernel\Entity\Messages;

class AutoUpdateService
{
    public const DEFAULT_REPO = 'https://repository.yeswiki.net/';
    public const DEFAULT_VERS = 'Cercopitheque';
    public $repository;

    private ContainerInterface $container;

    public function __construct(
        ContainerInterface $container
    ) {
        $this->container = $container;
    }

    /** Whether this is the instance allowed to trigger a farm-wide update (ADR-0007). */
    public function isDesignatedUpdateInstance(?string $instanceDir = null, ?string $sourceDir = null): bool
    {
        return realpath($instanceDir ?? YESWIKI_INSTANCE_DIR) === realpath($sourceDir ?? YESWIKI_SOURCE_DIR);
    }

    public function initRepository($requestedVersion = '')
    {
        $this->repository = new Repository($this->repositoryAddress($requestedVersion));

        return $this->repository->load();
    }

    private function repositoryAddress($requestedVersion = '')
    {
        $repositoryAddress = $this::DEFAULT_REPO;

        if (isset($this->container->get(\YesWiki\Kernel\Service\RuntimeConfig::class)['yeswiki_repository'])) {
            $repositoryAddress = $this->container->get(\YesWiki\Kernel\Service\RuntimeConfig::class)['yeswiki_repository'];
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
        if (isset($this->container->get(\YesWiki\Kernel\Service\RuntimeConfig::class)['yeswiki_version'])) {
            $version = $this->container->get(\YesWiki\Kernel\Service\RuntimeConfig::class)['yeswiki_version'];
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

    public function upgrade($packageName)
    {
        $messages = new Messages();
        $package = $this->repository->getPackage($packageName);

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
