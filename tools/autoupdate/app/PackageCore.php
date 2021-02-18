<?php
namespace AutoUpdate;

class PackageCore extends Package
{
    const CORE_NAME = 'yeswiki';
    public $ignoredFiles = array('.', '..', 'custom', 'templates','tools', 'files', 'cache', 'themes', 'wakka.config.php');

    public function __construct($release, $address, $desc, $doc)
    {
        parent::__construct($release, $address, $desc, $doc);
        $this->installed = true;
        $this->localPath = realpath(dirname($_SERVER["SCRIPT_FILENAME"]));
        $this->name = $this::CORE_NAME;
        $this->updateAvailable = $this->updateAvailable();
    }

    public function upgrade()
    {
        $desPath = $this->localPath;
        if ($this->extractionPath === null) {
            throw new \Exception("Le paquet n'a pas été décompressé.", 1);
        }
        if (substr($this->extractionPath, -1) != '/') {
            $this->extractionPath .= '/';
        }
        // get the first subfolder extracted from the zip (it contains everything)
        $dirs = array_filter(glob($this->extractionPath.'*'), 'is_dir');
        $this->extractionPath = $dirs[0].'/';
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
        $configuration['yeswiki_version'] = $this->requestedVersion();
        return $configuration->write();
    }

    public function name()
    {
        return $this::CORE_NAME;
    }

    public function localVersion()
    {
        $configuration = new Configuration('wakka.config.php');
        $configuration->load();

        $version = AutoUpdate::DEFAULT_VERS;
        if (isset($this->wiki->config['yeswiki_version'])) {
            $version = $this->wiki->config['yeswiki_version'];
        }
        return strtolower($version);
    }

    public function requestedVersion()
    {
        $configuration = new Configuration('wakka.config.php');
        $configuration->load();

        $version = AutoUpdate::DEFAULT_VERS;
        if (isset($this->wiki->config['yeswiki_version'])) {
            $version = $this->wiki->config['yeswiki_version'];
        }
        $requestedVersion = $GLOBALS['wiki']->getParameter('version');
        if (isset($requestedVersion) && $requestedVersion != '') {
            $version = $requestedVersion;
        }
        return strtolower($version);
    }

    public function newVersionRequested()
    {
        $result = false;
        $localVersion = $this->localVersion();
        $requestedVersion = $this->requestedVersion();
        if ($localVersion != $requestedVersion) {
            $result = true;
        }
        return $result;
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
