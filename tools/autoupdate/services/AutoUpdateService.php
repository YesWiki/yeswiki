<?php

namespace YesWiki\AutoUpdate\Service;

use YesWiki\AutoUpdate\Entity\Messages;
use YesWiki\AutoUpdate\Entity\PackageCollection;
use YesWiki\AutoUpdate\Entity\Repository;
use YesWiki\Wiki;

class AutoUpdateService
{
    public const DEFAULT_REPO = 'https://repository.yeswiki.net/';
    public const DEFAULT_VERS = 'Cercopitheque'; // Pour gérer les vielles version de YesWiki
    public $repository = null;

    private $wiki;

    public function __construct(
        Wiki $wiki
    ) {
        $this->wiki = $wiki;
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

        if (false === $package->deletePackage()) {
            $messages->add('AU_DELETE', 'AU_ERROR');

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
        if (!$package->checkACL()) {
            $messages->add('AU_ACL', 'AU_ERROR');
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
