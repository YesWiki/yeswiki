<?php

namespace YesWiki\AutoUpdate\Entity;

abstract class PackageExt extends Package
{
    public const INFOS_FILENAME = 'infos.json';

    protected $infos = null;

    public $deleteLink;

    abstract protected function localPath();

    public function __construct($release, $address, $desc, $doc, $minimalPhpVersion = null)
    {
        parent::__construct($release, $address, $desc, $doc, $minimalPhpVersion);
        $this->installed = $this->installed();
        $this->localPath = $this->localPath();
        $this->updateAvailable = $this->updateAvailable();
        $this->deleteLink = '&delete=' . $this->name;
    }

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

        // get the first subfolder extracted from the zip (it contains everything)
        $dirs = array_filter(glob($this->extractionPath . '/*'), 'is_dir');
        $extractionPath = $dirs[0] . '/';

        $this->copy(
            $extractionPath,
            $desPath
        );

        return true;
    }

    public function upgradeInfos()
    {
        $infos = [
            'name' => $this->name,
            'release' => (string)$this->release,
        ];
        $json = json_encode($infos);
        file_put_contents($this->infosFilePath(), $json);
        // TODO Vérifier que l'action a bien été éxécutée.
        return true;
    }

    public function deletePackage()
    {
        $desPath = $this->localPath();
        if (is_dir($desPath)) {
            $this->delete($desPath);
        }
    }

    protected function getInfos()
    {
        if ($this->infos !== null) {
            return $this->infos;
        }

        $this->infos = [];
        if (is_file($this->infosFilePath())) {
            $json = file_get_contents($this->infosFilePath());
            $this->infos = json_decode($json, true);
        }

        return $this->infos;
    }

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

    private function installed()
    {
        if (is_dir($this->localPath())) {
            return true;
        }

        return false;
    }

    private function infosFilePath()
    {
        return $this->localPath() . $this::INFOS_FILENAME;
    }
}
