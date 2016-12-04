<?php
namespace AutoUpdate;

class PackageCore extends Package
{
    const CORE_NAME = 'yeswiki';
    public $ignoredFiles = array('.', '..', 'tools', 'files', 'cache', 'themes',
        'wakka.config.php');

    public function __construct($release, $address, $desc, $doc)
    {
        parent::__construct($release, $address, $desc, $doc);
        $this->installed = true;
        $this->localPath = dirname(dirname(dirname(__DIR__)));
        $this->name = $this::CORE_NAME;
        $this->updateAvailable = $this->updateAvailable();
    }

    public function upgrade()
    {
        $desPath = $this->localPath;
        if ($this->extractionPath === null) {
            throw new \Exception("Le paquet n'a pas été décompressé.", 1);
        }
        $this->extractionPath .= '/';
        if ($res = opendir($this->extractionPath)) {
            while (($file = readdir($res)) !== false) {
                // Ignore les fichiers de la liste
                if (!in_array($file, $this->ignoredFiles)) {
                    $this->copy(
                        $this->extractionPath . '/' . $file,
                        $desPath . '/' . $file
                    );
                }
            }
            closedir($res);
        }
        return true;
    }

    public function upgradeTools()
    {
        $src = $this->extractionPath . '/tools';
        $desPath = $this->localPath . '/tools';
        $file2ignore = array('.', '..');

        if ($res = opendir($src)) {
            while (($file = readdir($res)) !== false) {
                // Ignore les fichiers de la liste
                if (!in_array($file, $file2ignore)) {
                    $this->copy($src . '/' . $file, $desPath . '/' . $file);
                }
            }
            closedir($res);
        }
        return true;
    }

    public function upgradeInfos()
    {
        $configuration = new Configuration('wakka.config.php');
        $configuration->load();
        $configuration['yeswiki_release'] = $this->release;
        return $configuration->write();
    }

    public function name()
    {
        return $this::CORE_NAME;
    }

    /***************************************************************************
     * Méthodes privée
     **************************************************************************/

    protected function localRelease()
    {
        $configuration = new Configuration('wakka.config.php');
        $configuration->load();

        $release = Release::UNKNOW_RELEASE;
        if (isset($configuration['yeswiki_release'])) {
            $release = $configuration['yeswiki_release'];
        }
        $release = new Release($release);
        return $release;
    }

    protected function updateAvailable()
    {
        if ($this->release->compare($this->localRelease()) > 0) {
            return true;
        }
        return false;
    }
}
