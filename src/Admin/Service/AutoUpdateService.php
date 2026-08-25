<?php

namespace YesWiki\Admin\Service;

use Psr\Container\ContainerInterface;
use YesWiki\Admin\Entity\PackageCore;
use YesWiki\Admin\Entity\PackageExt;
use YesWiki\Admin\Entity\Repository;
use YesWiki\Files\Service\LocalFiles;
use YesWiki\Kernel\Entity\Messages;
use YesWiki\Kernel\Service\ConfigurationService;

class AutoUpdateService
{
    public const DEFAULT_REPO = 'https://repository.yeswiki.net/';
    public const DEFAULT_VERS = 'Cercopitheque';
    /** @var Repository the package index, set by initRepository() before anything else reads it */
    public $repository;

    private ContainerInterface $container;

    public function __construct(
        ContainerInterface $container,
        private readonly LocalFiles $localFiles,
    ) {
        $this->container = $container;
    }

    /** Whether this is the instance allowed to trigger a farm-wide update (ADR-0007). */
    public function isDesignatedUpdateInstance(?string $instanceDir = null, ?string $programDir = null): bool
    {
        return $this->localFiles->realPath($instanceDir ?? YESWIKI_INSTANCE_DIR) === $this->localFiles->realPath($programDir ?? YESWIKI_PROGRAM_DIR);
    }

    /** Whether this instance may install or upgrade this package: only a core update mutates the shared Program. */
    public function mayUpgrade(string $packageName, ?string $instanceDir = null, ?string $programDir = null): bool
    {
        if ($packageName !== PackageCore::CORE_NAME) {
            return true;
        }

        return $this->isDesignatedUpdateInstance($instanceDir, $programDir);
    }

    /** @return bool false when the repository index could not be read */
    public function initRepository(string $requestedVersion = ''): bool
    {
        $this->repository = new Repository(
            $this->repositoryAddress($requestedVersion),
            $requestedVersion,
            $this->container->get(ConfigurationService::class)
        );

        return $this->repository->load();
    }

    private function repositoryAddress(string $requestedVersion = ''): string
    {
        $repositoryAddress = $this::DEFAULT_REPO;

        $configured = $this->container->get(\YesWiki\Kernel\Service\RuntimeConfig::class)['yeswiki_repository'] ?? null;
        if (is_string($configured) && $configured !== '') {
            $repositoryAddress = $configured;
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

    private function getYesWikiVersion(): string
    {
        $version = $this::DEFAULT_VERS;
        $configured = $this->container->get(\YesWiki\Kernel\Service\RuntimeConfig::class)['yeswiki_version'] ?? null;
        if (is_string($configured) && $configured !== '') {
            $version = $configured;
        }

        return strtolower($version);
    }

    public function delete(string $packageName): Messages
    {
        $messages = new Messages();
        $package = $this->repository->getPackage($packageName);

        if (!$package instanceof PackageExt) {
            $messages->add('AU_DELETE', 'AU_ERROR');

            return $messages;
        }

        $vDeleteStatus = $package->deletePackage();

        if ($vDeleteStatus !== true) {
            $messages[] = ['text' => (_t('AU_DELETE') . ' - ' . _t('AU_ERROR') . '\n' . _t('AU_UNABLE_TO_REMOVE_FILES') . implode('\n', $vDeleteStatus)), 'status' => _t('AU_ERROR')];

            return $messages;
        }
        $messages->add('AU_DELETE', 'AU_OK');

        return $messages;
    }

    public function upgrade(string $packageName): Messages
    {
        $messages = new Messages();
        $package = $this->repository->getPackage($packageName);

        $file = $package ? $package->getFile() : false;
        if ($package === null || false === $file) {
            $messages->add('AU_DOWNLOAD', 'AU_ERROR');

            return $messages;
        }
        $messages->add('AU_DOWNLOAD', 'AU_OK');

        if (!$package->checkIntegrity()) {
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

        if ($package instanceof PackageCore) {
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
