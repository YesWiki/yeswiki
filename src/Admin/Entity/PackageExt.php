<?php

namespace YesWiki\Admin\Entity;

abstract class PackageExt extends Package
{
    /** Whether this package installs into the Instance's own `custom/` rather than the Program. */
    /** Whether a package installs into the Instance's own `custom/` rather than the shared Program. */
    protected static function installsIntoInstance(): bool
    {
        return YESWIKI_INSTANCE_DIR !== YESWIKI_PROGRAM_DIR;
    }

    public const INFOS_FILENAME = 'infos.json';

    /** @var array<string, mixed>|null what infos.json holds, null until getInfos() has read it */
    protected $infos;

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
            $textAction = strtolower((is_dir($desPath)) ? _t('AU_UPDATE') : _t('AU_INSTALL'));
            trigger_error(_t('AU_PHP_TOO_LOW_ERROR', [
                'textAction' => $textAction,
                'NEEDEDPHPVERSION' => $neededPHPVersion,
                'CURRENTPHPVERSION' => PHP_VERSION,
                'hint' => _t('AU_PHP_TOO_LOW_HINT', ['textAction' => $textAction]),
            ]));

            return false;
        }

        $this->deletePackage();
        mkdir($desPath);

        if ($this->extractionPath === null) {
            throw new \Exception(_t('AU_PACKAGE_NOT_UNZIPPED'), 1);
        }

        $entries = glob($this->extractionPath . '/*');
        $dirs = ($entries === false) ? [] : array_filter($entries, 'is_dir');
        if ($dirs === []) {
            throw new \Exception(_t('AU_PACKAGE_NOT_UNZIPPED'), 1);
        }
        $extractionPath = reset($dirs) . '/';

        $this->copy(
            $extractionPath,
            $desPath
        );

        return true;
    }

    /** @return bool */
    public function upgradeInfos()
    {
        $infos = [
            'name' => $this->name,
            'release' => (string)$this->release,
        ];
        $json = json_encode($infos);
        file_put_contents($this->infosFilePath(), $json);

        return true;
    }

    /** @return true|list<string> true when the installed files are gone, otherwise the paths that could not be deleted */
    public function deletePackage()
    {
        $desPath = $this->localPath();

        if (is_dir($desPath)) {
            $vDeleteStatus = $this->delete($desPath);

            if ($vDeleteStatus === true) {
                return true;
            }

            return $vDeleteStatus;
        }

        return true;
    }

    /** @return array<string, mixed> what infos.json holds, empty when the package has none or it is unreadable */
    protected function getInfos()
    {
        if ($this->infos !== null) {
            return $this->infos;
        }

        $this->infos = [];
        if (is_file($this->infosFilePath())) {
            $json = file_get_contents($this->infosFilePath());
            $decoded = ($json === false) ? null : json_decode($json, true);
            if (is_array($decoded)) {
                $this->infos = $decoded;
            }
        }

        return $this->infos;
    }

    /** @return Release|string */
    protected function localRelease()
    {
        if ($this->installed()) {
            $infos = $this->getInfos();
            if (isset($infos['release'])) {
                return $infos['release'];
            }
        }

        return new Release(Release::UNKNOW_RELEASE);
    }

    private function installed(): bool
    {
        if (is_dir($this->localPath())) {
            return true;
        }

        return false;
    }

    private function infosFilePath(): string
    {
        return $this->localPath() . $this::INFOS_FILENAME;
    }
}
