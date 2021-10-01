<?php
namespace AutoUpdate;

use YesWiki\Security\Controller\SecurityController;

/**
 * Classe Controller
 *
 * gère les entrées ($_POST et $get)
 * @package AutoUpload
 * @author  Florestan Bredow <florestan.bredow@supagro.fr>
 * @version 0.0.1 (Git: $Id$)
 * @copyright 2015 Florestan Bredow
 */
class Controller
{
    private $autoUpdate;
    private $messages;
    private $wiki;
    private $securityController;

    public function __construct($autoUpdate, $messages, $wiki)
    {
        $this->autoUpdate = $autoUpdate;
        $this->messages = $messages;
        $this->wiki = $wiki;
        $this->securityController = $this->wiki->services->get(SecurityController::class);
    }

    /*	Parameter $requestedVersion contains the name of the YesWiki version
        requested by version parameter of {{update}} action
        if empty, no specifc version is requested
    */
    public function run($get, $requestedVersion='')
    {
        if (!isset($get['autoupdate'])) {
            $get['autoupdate'] = "default";
        }

        if (!$this->autoUpdate->initRepository($requestedVersion)) {
            return $this->wiki->render("@autoupdate/norepo.twig", []);
        }

        if (isset($get['upgrade'])
            and $this->autoUpdate->isAdmin()
            and !$this->securityController->isWikiHibernated()
            ) {
            $this->upgrade($get['upgrade']);
            if ($get['upgrade'] == 'yeswiki') {
                // reload wiki to prevent missing files' error due to upgrade.
                // prepare data
                $data = [];
                foreach ($this->messages as $message) {
                    $data_message = [];
                    $data_message['status'] = $message['status'];
                    $data_message['text'] = $message['text'];
                    $data['messages'][] = $data_message;
                }
                $data['baseURL'] = $this->autoUpdate->baseUrl();
                $_SESSION['updateMessage'] = json_encode($data);
                
                // call the same href to reload wiki in new doryphore version
                // give $data by $_SESSION['updateMessage']
                $newAdress = $this->wiki->Href();
                header("Location: ".$newAdress);
                exit();
            } else {
                return $this->wiki->render("@autoupdate/update.twig", [
                    'messages' => $this->messages,
                    'baseUrl' => $this->autoUpdate->baseUrl(),
                ]);
            }
        }

        if (isset($get['delete'])
            and $this->autoUpdate->isAdmin()
            and !$this->securityController->isWikiHibernated()
            ) {
            $this->delete($get['delete']);
            return $this->wiki->render("@autoupdate/update.twig", [
                'messages' => $this->messages,
                'baseUrl' => $this->autoUpdate->baseUrl(),
            ]);
        }

        return $this->wiki->render("@autoupdate/status.twig", [
            'baseUrl' => $this->autoUpdate->baseUrl(),
            'isAdmin' => $this->autoUpdate->isAdmin() && !$this->securityController->isWikiHibernated(),
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
        $this->messages->reset();
        $package = $this->autoUpdate->repository->getPackage($packageName);

        if (false === $package->deletePackage()) {
            $this->messages->add('AU_DELETE', 'AU_ERROR');
            return;
        }
        $this->messages->add('AU_DELETE', 'AU_OK');
    }

    private function upgrade($packageName)
    {
        // Remise a zéro des messages
        $this->messages->reset();

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
            return;
        }
        $this->messages->add('AU_INTEGRITY', 'AU_OK');

        // Extraction de l'archive
        $path = $package->extract();
        if (false === $path) {
            $this->messages->add('AU_EXTRACT', 'AU_ERROR');
            return;
        }
        $this->messages->add('AU_EXTRACT', 'AU_OK');

        // Vérification des droits sur le fichiers
        if (!$package->checkACL()) {
            $this->messages->add('AU_ACL', 'AU_ERROR');
            return;
        }
        $this->messages->add('AU_ACL', 'AU_OK');

        // Mise à jour du paquet
        if (!$package->upgrade()) {
            $this->messages->add(
                _t('AU_UPDATE_PACKAGE') . $packageName,
                'AU_ERROR'
            );
            return;
        }
        $this->messages->add(_t('AU_UPDATE_PACKAGE') . $packageName, 'AU_OK');

        if (get_class($package) === PackageCollection::CORE_CLASS) {
            // Mise à jour des tools.
            if (!$package->upgradeTools()) {
                $this->messages->add('AU_UPDATE_TOOL', 'AU_ERROR');
                return;
            }
            $this->messages->add('AU_UPDATE_TOOL', 'AU_OK');
        }

        // Mise à jour de la configuration de YesWiki
        if (!$package->upgradeInfos()) {
            $this->messages->add('AU_UPDATE_INFOS', 'AU_ERROR');
            return;
        }
        $this->messages->add('AU_UPDATE_INFOS', 'AU_OK');

        $package->cleanTempFiles();
    }
}
