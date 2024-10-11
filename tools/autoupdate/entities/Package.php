<?php

namespace YesWiki\AutoUpdate\Entity;

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
    public $description = '';
    public $documentation = '';
    protected $minimalPhpVersion;

    abstract public function upgrade();

    abstract public function upgradeInfos();

    abstract protected function localRelease();
    //abstract protected function updateAvailable();

    protected $localPath;

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
            $path = $this->localPath . DIRECTORY_SEPARATOR . $f;
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

        return $md5File === $md5Repo;
    }

    public function getFile()
    {
        $this->downloadedFile = $this->download($this->address, null, 30);

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

    /**
     * get needed PHP version from json file from repository.
     *
     * @return string formatted as '7.3.0', '7.3.0' is the wanted version in case of error
     */
    public function getNeededPHPversion(): string
    {
        // check format of JSON package 99.99.99
        $matches = [];
        if (is_string($this->minimalPhpVersion) && preg_match('/^([0-9]*)\.([0-9]*)\.([0-9]*)$/', $this->minimalPhpVersion, $matches)) {
            return $this->minimalPhpVersion;
        }

        return MINIMUM_PHP_VERSION_FOR_CORE; // just in case of error give a number
    }

    /**
     * get needed PHP version from json file from extracted folder.
     *
     * @return string formatted as '7.3.0', '7.3.0' is the wanted version in case of error
     */
    public function getNeededPHPversionFromExtractedFolder(): string
    {
        $jsonPath = $this->extractionPath . 'composer.json';
        if (file_exists($jsonPath)) {
            $jsonFile = file_get_contents($jsonPath);
            if (!empty($jsonFile)) {
                $composerData = json_decode($jsonFile, true);
                if (!empty($composerData['require']['php'])) {
                    $rawNeededPHPRevision = $composerData['require']['php'];
                    $matches = [];
                    // accepted format '7','7.3','7.*','7.3.0','7.3.*
                    // and these with '^', '>' or '>=' before
                    if (preg_match('/^(\^|>=|>)?([0-9]*)(?:\.([0-9\*]*))?(?:\.([0-9\*]*))?/', $rawNeededPHPRevision, $matches)) {
                        $major = $matches[2];
                        $minor = $matches[3] ?? 0;
                        $minor = ($minor == '*') ? 0 : $minor;
                        $fix = $matches[4] ?? 0;
                        $fix = ($fix == '*') ? 0 : $fix;

                        return $major . '.' . $minor . '.' . $fix;
                    }
                }
            }
        }

        return $this->getNeededPHPversion();
    }

    /**
     * check if current PHP version enough high.
     *
     * @param string $neededRevision
     *
     * @return bool
     */
    public function PHPVersionEnoughHigh(?string $neededRevision = null)
    {
        return version_compare(
            PHP_VERSION,
            (empty($neededRevision))
                ? $this->getNeededPHPversion()
                : $neededRevision,
            '>='
        );
    }

    /****************************************************************************
     * Méthodes privées
     **************************************************************************/
    protected function name()
    {
        $namePlusDate = explode('-', basename($this->address, '.zip'), 2)[1];

        return preg_replace('/-' . SEMVER . '$/', '', preg_replace('/-\d*-\d*-\d*-\d*$/', '', $namePlusDate));
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
