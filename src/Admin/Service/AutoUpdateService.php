<?php

namespace YesWiki\Admin\Service;

use YesWiki\Kernel\Entity\Messages;
use YesWiki\Admin\Entity\PackageCollection;
use YesWiki\Admin\Entity\Repository;
use YesWiki\Wiki;

class AutoUpdateService
{
    public const DEFAULT_REPO = 'https://repository.yeswiki.net/';
    public const DEFAULT_VERS = 'Cercopitheque'; // Pour gérer les vielles version de YesWiki
    public $repository;

    private $wiki;

    public function __construct(
        Wiki $wiki
    ) {
        $this->wiki = $wiki;
    }

    /**
     * Whether this is the instance allowed to trigger a farm-wide update (ADR-0007).
     * A farm satellite instance's own index.php redefines YESWIKI_SOURCE_DIR to point at
     * the shared source tree elsewhere (see src/commands/CreateInstanceCommand.php),
     * while YESWIKI_INSTANCE_DIR always stays that instance's own directory -- so the two
     * being equal means this IS the source tree being run directly (standalone install,
     * or the shared source checkout itself in a farm), the only place package upgrades
     * should ever be triggered from. No new config to set: this is structural, not
     * admin-configured, so it can't be misconfigured into an unsafe state.
     *
     * $instanceDir/$sourceDir default to the real YESWIKI_INSTANCE_DIR/YESWIKI_SOURCE_DIR
     * constants -- overridable so tests can exercise the "different" (simulated farm
     * satellite) branch without needing an actual second instance on disk.
     */
    public function isDesignatedUpdateInstance(?string $instanceDir = null, ?string $sourceDir = null): bool
    {
        return realpath($instanceDir ?? YESWIKI_INSTANCE_DIR) === realpath($sourceDir ?? YESWIKI_SOURCE_DIR);
    }

    /*	Parameter $requestedVersion contains the name of the YesWiki version
        requested by version parameter of {{update}} action
        if empty, no specifc version is requested
    */
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

    public function upgrade($packageName)
    {
        $messages = new Messages();
        $package = $this->repository->getPackage($packageName);

        // Téléchargement de l'archive
        $file = $package ? $package->getFile() : false;
        if (false === $file) {
            $messages->add('AU_DOWNLOAD', 'AU_ERROR');

            return $messages;
        }
        $messages->add('AU_DOWNLOAD', 'AU_OK');

        // Vérification MD5
        if (!$package->checkIntegrity($file)) {
            $messages->add('AU_INTEGRITY', 'AU_ERROR');
            $package->cleanTempFiles();

            return $messages;
        }
        $messages->add('AU_INTEGRITY', 'AU_OK');

        // Extraction de l'archive
        $path = $package->extract();
        if (false === $path) {
            $messages->add('AU_EXTRACT', 'AU_ERROR');
            $package->cleanTempFiles();

            return $messages;
        }
        $messages->add('AU_EXTRACT', 'AU_OK');

        // Vérification des droits sur le fichiers

        $vNotGoods = $package->checkACL();

        if (count($vNotGoods) > 0) {
            $vMaxLength = 100;
            $vFilesList = implode(', ', $vNotGoods);

            $messages[] = ['text' => (_t('AU_ACL') . '\n' . _t('AU_NOT_WRITABLE_FILES') . substr($vFilesList, 0, 100) . (strlen($vFilesList) > $vMaxLength ? '...' : '')), 'status' => _t('AU_ERROR')];

            $package->cleanTempFiles();

            return $messages;
        }
        $messages->add('AU_ACL', 'AU_OK');

        // Mise à jour du paquet
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

            // Mise à jour des tools.
            if (!$package->upgradeTools()) {
                $messages->add('AU_UPDATE_TOOL', 'AU_ERROR');
                $package->cleanTempFiles();

                return $messages;
            }
            $messages->add('AU_UPDATE_TOOL', 'AU_OK');
        }

        // Mise à jour de la configuration de YesWiki
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
