<?php
namespace AutoUpdate;

class PackageCore extends Package
{
    const CORE_NAME = 'yeswiki';
    public const IGNORED_FILES = [
        '.',
        '..',
        'custom',
        'tools',
        'files',
        'cache',
        'themes',
        'wakka.config.php'
    ];
    
    public const FILES_TO_ADD_TO_IGNORED_FOLDERS = [
        'files/README.md',
        'files/LovelaceAda_lovelace.png',
        'files/ElizabethJFeinler_elizabethfeinler-2011.jpg',
        'files/TesT2_presence-photo.png',
        'files/UnBeauLogoPourYeswiki_yeswiki-logo.png',
        'files/UnNouveauThemePourYeswiki_capture-décran-2020-02-12-à-13.16.33.png',
        'files/YeswikidaY_yeswiki-logo.png',
        'files/GererSite_modele_19880101000000_23001231235959.jpg',
        'files/PageHeader_bandeau1_19880101000000_23001231235959.png',
        'themes/README.md',
        'templates/README.md',
        'cache/README.md',
    ];

    public const FILES_TO_UPDATE_TO_IGNORED_FOLDERS = [
        'files/PageHeader_bandeau_20200101000000_29991231000000.png',
    ];

    public function __construct($release, $address, $desc, $doc, $minimalPhpVersion = null)
    {
        parent::__construct($release, $address, $desc, $doc, $minimalPhpVersion);
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

        // check if PHP update needed
        $neededPHPVersion = $this->getNeededPHPversionFromExtractedFolder() ;
        if (!$this->PHPVersionEnoughHigh($neededPHPVersion)) {
            $textAction = ($this->newVersionRequested()) ? _t('AU_PHP_TOO_LOW_VERSION_UPDATE') : _t('AU_PHP_TOO_LOW_UPDATE');
            trigger_error(_t('AU_PHP_TOO_LOW_START')
                .$textAction
                ._t('AU_PHP_TOO_LOW_MIDDLE')
                .$neededPHPVersion
                ._t('AU_PHP_TOO_LOW_END').PHP_VERSION."\n"
                ._t('AU_PHP_TOO_LOW_HINT')
                ._t('AU_PHP_TOO_LOW_VERSION_UPDATE') . ' !');
            return false;
        }

        if ($res = opendir($this->extractionPath)) {
            while (($file = readdir($res)) !== false) {
                // Ignore les fichiers de la liste
                if (!in_array($file, self::IGNORED_FILES)) {
                    $this->copy(
                        $this->extractionPath . '/' . $file,
                        $desPath . '/' . $file
                    );
                }
            }
            closedir($res);
            foreach (self::FILES_TO_ADD_TO_IGNORED_FOLDERS as $file) {
                if (is_file($this->extractionPath .'/'. $file) or is_dir($this->extractionPath .'/'. $file)) {
                    $this->copy($this->extractionPath . '/' . $file, $desPath . '/' . $file);
                }
            }
            foreach (self::FILES_TO_UPDATE_TO_IGNORED_FOLDERS as $file) {
                $this->copy($this->extractionPath . '/' . $file, $desPath . '/' . $file);
            }
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
        if (!empty($configuration['yeswiki_version'])) {
            $version = $configuration['yeswiki_version'];
        }
        return strtolower($version);
    }

    public function requestedVersion()
    {
        $configuration = new Configuration('wakka.config.php');
        $configuration->load();

        $version = AutoUpdate::DEFAULT_VERS;
        if (isset($configuration['yeswiki_version'])) {
            $version = $configuration['yeswiki_version'];
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

    /**
     * check if current PHP version enough high
     * @param string $neededRevision
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
     * get needed PHP version from json file from repository
     * @return string formatted as '7.3.0', '7.3.0' is the wanted version in case of error
     */
    public function getNeededPHPversion(): string
    {
        // check format of JSON package 99.99.99
        $matches = [];
        if (preg_match('/^([0-9]*)\.([0-9]*)\.([0-9]*)$/', $this->minimalPhpVersion, $matches)) {
            return $this->minimalPhpVersion ;
        }
        return '7.3.0'; // just in case of error give a number
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

    /**
     * get needed PHP version from json file from extracted folder
     * @return string formatted as '7.3.0', '7.3.0' is the wanted version in case of error
     */
    private function getNeededPHPversionFromExtractedFolder(): string
    {
        $jsonPath = $this->extractionPath . 'composer.json';
        if (file_exists($jsonPath)) {
            $jsonFile = file_get_contents($jsonPath);
            if (!empty($jsonFile)) {
                $composerData = json_decode($jsonFile, true) ;
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
                        return $major.'.'.$minor.'.'.$fix;
                    }
                }
            }
        } else {
            trigger_error('Not existing file composer.json in extracted package.');
        }
        return $this->getNeededPHPversion();
    }
}
