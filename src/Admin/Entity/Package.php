<?php

namespace YesWiki\Admin\Entity;

use YesWiki\Content\Entity\Files;

abstract class Package extends Files
{
    public const PREFIX_FILENAME = 'yeswiki_';

    /** @var string URL the package archive is downloaded from */
    protected $address;

    /** @var string|null folder the archive was extracted into, null while nothing is extracted */
    protected $extractionPath;

    /** @var string|null path of the downloaded archive, null while nothing is downloaded */
    protected $downloadedFile;

    /** @var string|null path of the downloaded .md5 checksum file */
    protected $md5File;

    /** @var string */
    public $name;

    /** @var Release release offered by the repository */
    public $release;

    /** @var Release|string release currently installed, Release::UNKNOW_RELEASE when none is */
    public $localRelease;

    /** @var bool */
    public $installed = false;

    /** @var bool */
    public $updateAvailable = false;

    /** @var string */
    public $updateLink;

    /** @var string */
    public $description = '';

    /** @var string */
    public $documentation = '';

    /** @var string|null minimum PHP version the repository declares, null when it declares none */
    protected $minimalPhpVersion;

    /**
     * Replace the installed files with the extracted ones.
     *
     * @return bool
     */
    abstract public function upgrade();

    /**
     * Record the newly installed release where localRelease() will read it back.
     *
     * @return bool
     */
    abstract public function upgradeInfos();

    /**
     * @return Release|string
     */
    abstract protected function localRelease();

    /** @var string absolute path the package is installed at */
    protected $localPath;

    /**
     * @param Release     $release
     * @param string      $address
     * @param string      $desc
     * @param string      $doc
     * @param string|null $minimalPhpVersion
     */
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

    /**
     * @return array<string> the paths the updater would not be able to write, relative to the install
     */
    public function checkACL()
    {
        $file2check = [
            'index.php',
            'composer.json',
            'composer.lock',
            'Dockerfile',
            'INSTALL.md',
            'LICENSE',
            'README.md',
            'robots.txt',
            'actions',
            'docs',
            'handlers',
            'lang',
            'src',
            'templates',
            'extensions',
            'themes',
            'vendor',
        ];

        $vNotGoods = [];

        foreach ($file2check as $f) {
            $path = $this->localPath . DIRECTORY_SEPARATOR . $f;
            if (file_exists($path)) {
                $vNotWritables = $this->isWritable($path);

                if ($vNotWritables !== true) {
                    $vNotGoods = array_merge($vNotGoods, $vNotWritables);
                }
            }
        }

        $vLocalPathLength = strlen($this->localPath);

        $vNotGoods = array_map(function ($pPath) use ($vLocalPathLength) {
            return '.' . substr($pPath, $vLocalPathLength);
        }, $vNotGoods);

        return $vNotGoods;
    }

    /**
     * @return bool
     */
    public function checkIntegrity()
    {
        if ($this->downloadedFile === null) {
            throw new \Exception(_t('AU_PACKAGE_NOT_DOWNLOADED'), 1);
        }
        $md5Repo = $this->getMD5();
        $md5File = md5_file($this->downloadedFile);

        return $md5File === $md5Repo;
    }

    /**
     * @return string|false path of the downloaded archive, false when the download produced no file
     */
    public function getFile()
    {
        $this->downloadedFile = $this->download($this->address, null, 30);

        if (is_file($this->downloadedFile)) {
            return $this->downloadedFile;
        }
        $this->downloadedFile = null;

        return false;
    }

    /**
     * @return string|false folder the archive was extracted into, false when it could not be opened or extracted
     */
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

    /**
     * @return void
     */
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
        $matches = [];
        if (is_string($this->minimalPhpVersion) && preg_match('/^([0-9]*)\.([0-9]*)\.([0-9]*)$/', $this->minimalPhpVersion, $matches)) {
            return $this->minimalPhpVersion;
        }

        return MINIMUM_PHP_VERSION_FOR_CORE;
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

    /**
     * @return string
     */
    protected function name()
    {
        $namePlusDate = explode('-', basename($this->address, '.zip'), 2)[1];

        $withoutDate = preg_replace('/-\d*-\d*-\d*-\d*$/', '', $namePlusDate) ?? $namePlusDate;

        return preg_replace('/-' . SEMVER . '$/', '', $withoutDate) ?? $withoutDate;
    }

    /**
     * @return string
     */
    private function getMD5()
    {
        $this->md5File = $this->download($this->address . '.md5');
        $checksum = file_get_contents($this->md5File);

        if ($checksum === false) {
            return '';
        }

        return explode(' ', $checksum)[0];
    }

    /**
     * @return bool
     */
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
