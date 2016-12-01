<?php
namespace AutoUpdate;

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

    public function __construct($autoUpdate, $messages)
    {
        $this->autoUpdate = $autoUpdate;
        $this->messages = $messages;
    }

    public function run($get)
    {
        if (!isset($get['autoupdate'])) {
            $get['autoupdate'] = "default";
        }

        if (!$this->autoUpdate->initRepository()) {
            $view = new ViewNoRepo($this->autoUpdate);
            $view->show();
            return;
        }

        if (isset($get['upgrade'])
            and $this->autoUpdate->isAdmin()
        ) {
            $this->upgrade($get['upgrade']);
            $view = new ViewUpdate($this->autoUpdate, $this->messages);
            $view->show();
            return;
        }

        if (isset($get['delete'])
            and $this->autoUpdate->isAdmin()
        ) {
            $this->delete($get['delete']);
            $view = new ViewUpdate($this->autoUpdate, $this->messages);
            $view->show();
            return;
        }

        $view = new ViewStatus($this->autoUpdate, $this->messages);
        $view->show();
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
        $file = $package->getFile();
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
