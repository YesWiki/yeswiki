<?php

namespace AutoUpdate;

abstract class Package extends Files
{
    public const PREFIX_FILENAME = 'yeswiki_';

    // URL vers le fichier dans le dépôt.
    protected $address;
    // Chemin vers le dossier temporaire ou est décompressé le paquet
    protected $extractionPath = null;
    // Chemin vers le paquet temporaire téléchargé localement
    protected $downloadedFile = null;
    // md5 du paquet temporaire téléchargé localement
    protected $md5File = null;
    // nom du tool
    public $name = null;
    // Version du paquet
    public $release;
    public $localRelease;
    public $installed = false;
    public $updateAvailable = false;
    public $updateLink;
    public $description = "";
    public $documentation = "";
    protected $minimalPhpVersion;

    abstract public function upgrade();
    abstract public function upgradeInfos();

    abstract protected function localRelease();
    //abstract protected function updateAvailable();

    public function __construct($release, $address, $desc, $doc, $minimalPhpVersion = null)
    {
        $this->release = $release;
        $this->address = $address;
        $this->description = $desc;
        $this->documentation = $doc;
        $this->name = $this->name();
        $this->updateLink = $this->name;
        $this->localRelease = $this->localRelease();
        $this->minimalPhpVersion = $minimalPhpVersion;
    }

    public function checkACL()
    {
        $file2check = [
            'index.php',
            'composer.json',
            'composer.lock',
            'Dockerfile',
            'docker-compose.yml',
            'INSTALL.md',
            'interwiki.conf',
            'LICENSE',
            'README.md',
            'robots.txt',
            'wakka.basic.css',
            'wakka.css',
            'wakka.php',
            'actions',
            'docs',
            'formatters',
            'handlers',
            'includes',
            'lang',
            'setup',
            'templates',
            'themes',
            'tools',
            'vendor',
        ];
        $allGood = true;
        foreach ($file2check as $f) {
            $path = $this->localPath.DIRECTORY_SEPARATOR.$f;
            if (file_exists($path) and !$this->isWritable($path)) {
                return false;
            }
        }
        return $allGood;
    }

    public function checkIntegrity()
    {
        if ($this->downloadedFile === null) {
            throw new \Exception(_t('AU_PACKAGE_NOT_DOWNLOADED'), 1);
        }
        $md5Repo = $this->getMD5();
        $md5File = md5_file($this->downloadedFile);
        return ($md5File === $md5Repo);
    }

    public function getFile()
    {
        $this->downloadedFile = $this->download($this->address);

        if (is_file($this->downloadedFile)) {
            return $this->downloadedFile;
        }
        $this->downloadedFile = null;
        return false;
    }

    public function extract()
    {
        if ($this->downloadedFile === null) {
            throw new \Exception(_t('AU_PACKAGE_NOT_DOWNLOADED'), 1);
        }

        $zip = new \ZipArchive();
        if (true !== $zip->open($this->downloadedFile)) {
            return false;
        }

        $this->extractionPath = $this->tmpdir();
        if (true !== $zip->extractTo($this->extractionPath)) {
            return false;
        }
        $zip->close();

        return $this->extractionPath;
    }

    public function cleanTempFiles()
    {
        $this->delete($this->downloadedFile);
        $this->delete($this->extractionPath);
        $this->delete($this->md5File);
        $this->downloadedFile = null;
        $this->extractionPath = null;
    }


    /****************************************************************************
     * Méthodes privées
     **************************************************************************/
    protected function name()
    {
        $namePlusDate =  explode('-', basename($this->address, '.zip'), 2)[1];

        return preg_replace('/-'.SEMVER.'$/', '', preg_replace('/-\d*-\d*-\d*-\d*$/', '', $namePlusDate));
    }


    private function getMD5()
    {
        $this->md5File = $this->download($this->address . '.md5');
        return explode(' ', file_get_contents($this->md5File))[0];
    }

    protected function updateAvailable()
    {
        if ($this->installed) {
            if ($this->release->compare($this->localRelease()) > 0) {
                return true;
            }
        }
        return false;
    }
}
