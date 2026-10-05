<?php

namespace YesWiki\Admin\Entity;

use YesWiki\Kernel\Entity\ExtensionManifest;

abstract class PackageExt extends Package
{
    /** Whether a package installs into the Instance's own `custom/` rather than the shared Program. */
    protected static function installsIntoInstance(): bool
    {
        return YESWIKI_INSTANCE_DIR !== YESWIKI_PROGRAM_DIR;
    }

    public const LEGACY_INFOS_FILENAME = 'infos.json';

    /** @var string */
    public $deleteLink;

    /** @return string absolute path the package is installed at, with a trailing slash */
    abstract protected function localPath();

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
        $this->installed = $this->installed();
        $this->localPath = $this->localPath();
        $this->updateAvailable = $this->updateAvailable();
        $this->deleteLink = '&delete=' . $this->name;
    }

    /** @return bool */
    public function upgrade()
    {
        $desPath = $this->localPath();

        $neededPHPVersion = $this->getNeededPHPversionFromExtractedFolder();
        if (!$this->PHPVersionEnoughHigh($neededPHPVersion)) {
            $textAction = strtolower($this->isDirectory($desPath) ? _t('AU_UPDATE') : _t('AU_INSTALL'));
            trigger_error(_t('AU_PHP_TOO_LOW_ERROR', [
                'textAction' => $textAction,
                'NEEDEDPHPVERSION' => $neededPHPVersion,
                'CURRENTPHPVERSION' => PHP_VERSION,
                'hint' => _t('AU_PHP_TOO_LOW_HINT', ['textAction' => $textAction]),
            ]));

            return false;
        }

        if ($this->extractionPath === null) {
            throw new \Exception(_t('AU_PACKAGE_NOT_UNZIPPED'), 1);
        }

        $entries = $this->matching($this->extractionPath . '/*');
        $dirs = array_filter($entries, fn (string $path) => $this->isDirectory($path));
        if ($dirs === []) {
            throw new \Exception(_t('AU_PACKAGE_NOT_UNZIPPED'), 1);
        }
        $extractionPath = reset($dirs) . '/';

        return $this->copy($extractionPath, $desPath);
    }

    /** @return bool true: the release travels in the package's own composer.json (ADR-0029), so nothing is written beside it */
    public function upgradeInfos()
    {
        return true;
    }

    /** @return true|list<string> true when the installed files are gone, otherwise the paths that could not be deleted */
    public function deletePackage()
    {
        $desPath = $this->localPath();

        if ($this->isDirectory($desPath)) {
            $vDeleteStatus = $this->delete($desPath);

            if ($vDeleteStatus === true) {
                return true;
            }

            return $vDeleteStatus;
        }

        return true;
    }

    /** @return Release|string the version its composer.json was published with, or what a pre-ADR-0029 infos.json recorded */
    protected function localRelease()
    {
        if (!$this->installed()) {
            return new Release(Release::UNKNOW_RELEASE);
        }
        foreach ([ExtensionManifest::FILENAME => 'version', self::LEGACY_INFOS_FILENAME => 'release'] as $file => $key) {
            $path = $this->localPath() . $file;
            $decoded = $this->isFile($path) ? json_decode($this->read($path), true) : null;
            if (is_array($decoded) && is_string($decoded[$key] ?? null) && $decoded[$key] !== '') {
                return $decoded[$key];
            }
        }

        return new Release(Release::UNKNOW_RELEASE);
    }

    private function installed(): bool
    {
        if ($this->isDirectory($this->localPath())) {
            return true;
        }

        return false;
    }
}
