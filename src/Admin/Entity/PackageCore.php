<?php

namespace YesWiki\Admin\Entity;

use YesWiki\Admin\Service\AutoUpdateService;
use YesWiki\Kernel\Entity\ConfigurationFile;
use YesWiki\Kernel\Service\ConfigurationFileProvider;
use YesWiki\Kernel\Service\ConfigurationService;

class PackageCore extends Package
{
    public const CORE_NAME = 'yeswiki';
    public const IGNORED_FILES = [
        '.',
        '..',
        'custom',
        'extensions',
        'files',
        'cache',
        'themes',
        'robots.txt',
        'yeswiki.config.php',
        'wakka.config.php',
        'private',
    ];

    public const FILES_TO_ADD_TO_IGNORED_FOLDERS = [
        'files/README.md',
        'files/LovelaceAda_lovelace.png',
        'files/ElizabethJFeinler_elizabethfeinler-2011.jpg',
        'files/TesT2_presence-photo.png',
        'files/UnBeauLogoPourYeswiki_yeswiki-logo.png',
        'files/UnNouveauThemePourYeswiki_capture-décran-2020-02-12-à-13.16.33.png',
        'files/YeswikidaY_yeswiki-logo.png',
        'files/GererSite_modele_19880101000000_23001231235959.jpg',
        'files/PageHeader_bandeau_19880101000000_23001231235959.webp',
        'themes/README.md',
        'templates/README.md',
        'cache/README.md',
    ];

    public const FILES_TO_UPDATE_TO_IGNORED_FOLDERS = [
        'files/PageHeader_bandeau_20200101000000_29991231000000.png',
    ];

    /** The version an admin asked for, empty when they asked for none. */
    private string $requestedVersion = '';

    private ?ConfigurationService $configurationService = null;

    /**
     * The two facts this entity used to reach for through `$GLOBALS['yeswikiServices']`.
     *
     * A package is built from a class-string by PackageCollection, so its constructor signature
     * is shared with the theme and tool packages and cannot carry these. The collection hands
     * them over straight afterwards, from what the update flow already knew (ticket 45).
     */
    public function updateContext(string $requestedVersion, ?ConfigurationService $configurationService): void
    {
        $this->requestedVersion = $requestedVersion;
        $this->configurationService = $configurationService;
    }

    /**
     * @param Release     $release
     * @param string      $address
     * @param string      $desc
     * @param string      $doc
     * @param string|null $minimalPhpVersion
     */
    public function __construct($release, $address, $desc, $doc, $minimalPhpVersion = null)
    {
        parent::__construct($release, $address, $desc, $doc, $minimalPhpVersion);
        $this->installed = true;

        $this->localPath = YESWIKI_SOURCE_DIR;
        $this->name = $this::CORE_NAME;
        $this->updateAvailable = $this->updateAvailable();
    }

    public function upgrade(): bool
    {
        $desPath = $this->localPath;
        if ($this->extractionPath === null) {
            throw new \Exception(_t('AU_PACKAGE_NOT_UNZIPPED'), 1);
        }
        if (substr($this->extractionPath, -1) != '/') {
            $this->extractionPath .= '/';
        }

        $dirs = array_filter(glob($this->extractionPath . '*'), 'is_dir');
        $this->extractionPath = $dirs[0] . '/';

        $neededPHPVersion = $this->getNeededPHPversionFromExtractedFolder();
        if (!$this->PHPVersionEnoughHigh($neededPHPVersion)) {
            $textAction = ($this->newVersionRequested()) ? _t('AU_PHP_TOO_LOW_VERSION_UPDATE') : _t('AU_PHP_TOO_LOW_UPDATE');
            trigger_error(_t('AU_PHP_TOO_LOW_ERROR', [
                'textAction' => $textAction,
                'NEEDEDPHPVERSION' => $neededPHPVersion,
                'CURRENTPHPVERSION' => PHP_VERSION,
                'hint' => _t('AU_PHP_TOO_LOW_HINT', ['textAction' => $textAction]),
            ]));

            return false;
        }

        if ($res = opendir($this->extractionPath)) {
            while (($file = readdir($res)) !== false) {
                if (!in_array($file, self::IGNORED_FILES)) {
                    $this->copy(
                        $this->extractionPath . '/' . $file,
                        $desPath . '/' . $file
                    );
                }
            }
            closedir($res);
            foreach (self::FILES_TO_ADD_TO_IGNORED_FOLDERS as $file) {
                if (is_file($this->extractionPath . '/' . $file) or is_dir($this->extractionPath . '/' . $file)) {
                    $this->copy($this->extractionPath . '/' . $file, $desPath . '/' . $file);
                }
            }
            foreach (self::FILES_TO_UPDATE_TO_IGNORED_FOLDERS as $file) {
                $this->copy($this->extractionPath . '/' . $file, $desPath . '/' . $file);
            }
        }

        foreach (['cache', 'files'] as $dirName) {
            if (!is_dir($desPath . '/' . $dirName)) {
                mkdir($desPath . '/' . $dirName);
            }
        }

        return true;
    }

    public function upgradeDefaultTheme(): bool
    {
        $src = $this->extractionPath . '/themes/' . THEME_PAR_DEFAUT;
        $desPath = $this->localPath . '/themes/' . THEME_PAR_DEFAUT;
        $file2ignore = ['.', '..'];
        if ($res = opendir($src)) {
            while (($file = readdir($res)) !== false) {
                if (!in_array($file, $file2ignore)) {
                    $this->copy($src . '/' . $file, $desPath . '/' . $file);
                }
            }
            closedir($res);
        }

        return true;
    }

    public function upgradeTools(): bool
    {
        $src = $this->extractionPath . '/extensions';
        $desPath = $this->localPath . '/extensions';
        $file2ignore = ['.', '..'];
        if ($res = opendir($src)) {
            while (($file = readdir($res)) !== false) {
                if (!in_array($file, $file2ignore)) {
                    $this->copy($src . '/' . $file, $desPath . '/' . $file);
                }
            }
            closedir($res);
        }

        return true;
    }

    public function upgradeInfos(): bool
    {
        $configuration = new ConfigurationFile(ConfigurationFileProvider::getConfigFileFromEnv(), $this->configurationService);
        $configuration->load();
        $configuration['yeswiki_release'] = $this->release;
        $configuration['yeswiki_version'] = $this->requestedVersion();

        return $configuration->write();
    }

    public function name(): string
    {
        return $this::CORE_NAME;
    }

    public function localVersion(): string
    {
        $configuration = new ConfigurationFile(ConfigurationFileProvider::getConfigFileFromEnv(), $this->configurationService);
        $configuration->load();

        $version = AutoUpdateService::DEFAULT_VERS;
        if (!empty($configuration['yeswiki_version'])) {
            $version = $configuration['yeswiki_version'];
        }

        return strtolower($version);
    }

    public function requestedVersion(): string
    {
        $configuration = new ConfigurationFile(ConfigurationFileProvider::getConfigFileFromEnv(), $this->configurationService);
        $configuration->load();

        $version = AutoUpdateService::DEFAULT_VERS;
        if (isset($configuration['yeswiki_version'])) {
            $version = $configuration['yeswiki_version'];
        }
        if ($this->requestedVersion !== '') {
            $version = $this->requestedVersion;
        }

        return strtolower($version);
    }

    public function newVersionRequested(): bool
    {
        $result = false;
        $localVersion = $this->localVersion();
        $requestedVersion = $this->requestedVersion();
        if ($localVersion != $requestedVersion) {
            $result = true;
        }

        return $result;
    }

    protected function localRelease(): Release
    {
        $configuration = new ConfigurationFile(ConfigurationFileProvider::getConfigFileFromEnv(), $this->configurationService);
        $configuration->load();

        $release = Release::UNKNOW_RELEASE;
        if (isset($configuration['yeswiki_release'])) {
            $release = $configuration['yeswiki_release'];
        }
        $release = new Release($release);

        return $release;
    }

    protected function updateAvailable(): bool
    {
        if ($this->release->compare($this->localRelease()) > 0) {
            return true;
        }

        return false;
    }
}
