<?php
namespace AutoUpdate;

abstract class Package extends Files
{
    const PREFIX_FILENAME = 'yeswiki_';

    // URL vers le fichier dans le dépôt.
    protected $address;
    // Chemin vers le dossier temporaire ou est décompressé le paquet
    protected $extractionPath = null;
    // Chemin vers le paquet temporaire téléchargé localement
    protected $downloadedFile = null;
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

    abstract public function upgrade();
    abstract public function upgradeInfos();

    abstract protected function localRelease();
    //abstract protected function updateAvailable();

    public function __construct($release, $address, $desc, $doc)
    {
        $this->release = $release;
        $this->address = $address;
        $this->description = $desc;
        $this->documentation = $doc;
        $this->name = $this->name();
        $this->updateLink = '&upgrade=' . $this->name;
        $this->localRelease = $this->localRelease();
    }

    public function checkACL()
    {
        return $this->isWritable($this->localPath);
    }

    public function checkIntegrity()
    {
        if ($this->downloadedFile === null) {
            throw new \Exception("Le paquet n'a pas été téléchargé.", 1);
        }
        $md5Repo = $this->getMD5();
        $md5File = md5_file($this->downloadedFile);
        return ($md5File === $md5Repo);
    }

    public function getFile()
    {
        $this->downloadFile($this->address);

        if (is_file($this->downloadedFile)) {
            return $this->downloadedFile;
        }
        $this->downloadedFile = null;
        return false;
    }

    public function extract()
    {
        if ($this->downloadedFile === null) {
            throw new \Exception("Le paquet n'a pas été téléchargé.", 1);
        }

        $zip = new \ZipArchive;
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
        $this->downloadedFile = null;
        $this->extractionPath = null;
    }


    /****************************************************************************
     * Méthodes privées
     **************************************************************************/
    protected function name()
    {
        return explode('-', basename($this->address, '.zip'))[1];
    }


    private function getMD5()
    {
        $disMd5File = file_get_contents($this->address . '.md5');
        return explode(' ', $disMd5File)[0];
    }

    private function downloadFile($sourceUrl)
    {
        $this->downloadedFile = tempnam(sys_get_temp_dir(), $this::PREFIX_FILENAME);
        file_put_contents($this->downloadedFile, fopen($sourceUrl, 'r'));
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
