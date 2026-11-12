<?php

namespace YesWiki\Admin\Service;

use Psr\Container\ContainerInterface;
use YesWiki\Admin\Entity\PackageCore;
use YesWiki\Admin\Entity\PackageExt;
use YesWiki\Admin\Entity\PackageTool;
use YesWiki\Admin\Entity\Repository;
use YesWiki\Files\Service\LocalFiles;
use YesWiki\Kernel\Entity\Messages;
use YesWiki\Kernel\Service\ConfigurationService;
use YesWiki\Kernel\Service\Journal;
use YesWiki\Kernel\Service\Replication;

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

    /** Whether the program is built into an image, so nothing installs, updates or removes code or themes once it runs (replicas 05). */
    public function isImmutable(): bool
    {
        return Replication::isOn();
    }

    /** Whether this Instance's `forbidden_extensions` keeps the extension $packageName off it. */
    public function isForbiddenExtension(string $packageName): bool
    {
        return $this->container->get(ExtensionActivation::class)->isForbidden($packageName);
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
        if ($this->isImmutable()) {
            $messages->add('AU_PROGRAM_IMMUTABLE', 'AU_ERROR');

            return $messages;
        }
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
        $this->container->get(Journal::class)->audit('package.delete', $packageName);

        return $messages;
    }

    public function upgrade(string $packageName): Messages
    {
        $messages = new Messages();
        if ($this->isImmutable()) {
            $messages->add('AU_PROGRAM_IMMUTABLE', 'AU_ERROR');

            return $messages;
        }
        $package = $this->repository->getPackage($packageName);

        if ($package instanceof PackageTool && $this->isForbiddenExtension($packageName)) {
            $messages->add(_t('AU_EXTENSION_FORBIDDEN', ['name' => $packageName]), 'AU_ERROR');

            return $messages;
        }

        $messages = (new PackageInstaller())->install($package, $packageName);
        if (!$messages->hasError()) {
            $this->container->get(Journal::class)->audit('package.upgrade', $packageName, ['release' => (string)$package?->release]);
        }

        return $messages;
    }
}
