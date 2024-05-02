<?php

namespace AutoUpdate;

use YesWiki\Core\Service\ArchiveService;
use YesWiki\Security\Controller\SecurityController;

/**
 * Classe Controller
 *
 * gère les entrées ($_POST et $get)
 */
class Controller
{
    protected $archiveService;
    private $autoUpdate;
    private $messages;
    private $wiki;
    private $securityController;

    public function __construct($autoUpdate, $messages, $wiki)
    {
        $this->autoUpdate = $autoUpdate;
        $this->messages = $messages;
        $this->wiki = $wiki;
        $this->archiveService = $this->wiki->services->get(ArchiveService::class);
        $this->securityController = $this->wiki->services->get(SecurityController::class);
    }

    /*	Parameter $requestedVersion contains the name of the YesWiki version
        requested by version parameter of {{update}} action
        if empty, no specifc version is requested
    */
    public function run($get, $requestedVersion = '')
    {
        if (!isset($get['autoupdate'])) {
            $get['autoupdate'] = "default";
        }

        if (!$this->autoUpdate->initRepository($requestedVersion)) {
            return $this->wiki->render("@autoupdate/norepo.twig", []);
        }

        if (
            isset($get['upgrade'])
            and $this->autoUpdate->isAdmin()
            and !$this->securityController->isWikiHibernated()
        ) {
            // Ensure a backup is made before the upgrade
            // User as the option to force the update even without backup
            if (!$this->archiveService->hasValidatedBackup($get['forcedUpdateToken'] ?? "")) {
                return $this->wiki->render("@core/preupdate-backup.twig", [
                    'upgrade' => strval($get['upgrade'])
                ]);
            }

            // Perform the upgrade
            $this->upgrade($get['upgrade']);

            // When upgrading the core (and not extension or theme) we reload the page
            // to perform postInstall operation with the new code
            if ($get['upgrade'] == 'yeswiki') {
                // Store messages into session
                $_SESSION['upgradeMessages'] = json_encode(['messages' => $this->messages]);
                // call the same href to reload wiki in new version (this will call post install code)
                header("Location: " . $this->wiki->Href());
                exit();
            } else {
                return $this->wiki->render("@autoupdate/update-result.twig", [
                    'messages' => $this->messages
                ]);
            }
        }

        if (
            isset($get['delete'])
            and $this->autoUpdate->isAdmin()
            and !$this->securityController->isWikiHibernated()
        ) {
            $this->delete($get['delete']);
            return $this->wiki->render("@autoupdate/update-result.twig", [
                'messages' => $this->messages
            ]);
        }

        return $this->wiki->render("@autoupdate/status.twig", [
            'baseUrl' => $this->autoUpdate->baseUrl(),
            'isAdmin' => $this->autoUpdate->isAdmin(),
            'isHibernated' => $this->securityController->isWikiHibernated(),
            'core' => $this->autoUpdate->repository->getCorePackage(),
            'themes' => $this->autoUpdate->repository->getThemesPackages(),
            'tools' => $this->autoUpdate->repository->getToolsPackages(),
            'showCore' => true,
            'showThemes' => true,
            'showTools' => true,
            'phpVersion' => PHP_VERSION
        ]);
    }

    private function delete($packageName)
    {
        $package = $this->autoUpdate->repository->getPackage($packageName);

        if (false === $package->deletePackage()) {
            $this->messages->add('AU_DELETE', 'AU_ERROR');
            return;
        }
        $this->messages->add('AU_DELETE', 'AU_OK');
    }

    private function upgrade($packageName)
    {
        $package = $this->autoUpdate->repository->getPackage($packageName);

        // Téléchargement de l'archive
        $file = $package ? $package->getFile() : false;
        if (false === $file) {
            $this->messages->add('AU_DOWNLOAD', 'AU_ERROR');
            return;
        }
        $this->messages->add('AU_DOWNLOAD', 'AU_OK');

        // Vérification MD5
        if (!$package->checkIntegrity($file)) {
            $this->messages->add('AU_INTEGRITY', 'AU_ERROR');
            $package->cleanTempFiles();
            return;
        }
        $this->messages->add('AU_INTEGRITY', 'AU_OK');

        // Extraction de l'archive
        $path = $package->extract();
        if (false === $path) {
            $this->messages->add('AU_EXTRACT', 'AU_ERROR');
            $package->cleanTempFiles();
            return;
        }
        $this->messages->add('AU_EXTRACT', 'AU_OK');

        // Vérification des droits sur le fichiers
        if (!$package->checkACL()) {
            $this->messages->add('AU_ACL', 'AU_ERROR');
            $package->cleanTempFiles();
            return;
        }
        $this->messages->add('AU_ACL', 'AU_OK');

        // Mise à jour du paquet
        if (!$package->upgrade()) {
            $this->messages->add(
                _t('AU_UPDATE_PACKAGE') . $packageName,
                'AU_ERROR'
            );
            $package->cleanTempFiles();
            return;
        }
        $this->messages->add(_t('AU_UPDATE_PACKAGE') . $packageName, 'AU_OK');

        if (get_class($package) === PackageCollection::CORE_CLASS) {
            // Mise à jour des tools.
            if (!$package->upgradeTools()) {
                $this->messages->add('AU_UPDATE_TOOL', 'AU_ERROR');
                $package->cleanTempFiles();
                return;
            }
            $this->messages->add('AU_UPDATE_TOOL', 'AU_OK');
        }

        // Mise à jour de la configuration de YesWiki
        if (!$package->upgradeInfos()) {
            $this->messages->add('AU_UPDATE_INFOS', 'AU_ERROR');
            $package->cleanTempFiles();
            return;
        }
        $this->messages->add('AU_UPDATE_INFOS', 'AU_OK');

        $package->cleanTempFiles();
    }
}
