<?php

namespace YesWiki\AutoUpdate\Entity;

class Release
{
    public const UNKNOW_RELEASE = '0000-00-00-0';
    public $release;

    public function __construct($release)
    {
        $this->release = $this::UNKNOW_RELEASE;
        if ($this->checkFormat($release)) {
            $this->release = $release;
        }
    }

    public function __toString()
    {
        if ($this->release === $this::UNKNOW_RELEASE) {
            return _t('AU_UNKNOW');
        }

        return (string)$this->release;
    }

    public function compare($releaseToCompare)
    {
        if ((string)$releaseToCompare === $this->release) {
            return 0;
        }
        if (strpos($this->release, '.') !== false && strpos((string)$releaseToCompare, '.') === false) {
            return 1;
        }
        $releaseToCompare = $this->evalRelease(is_string($releaseToCompare) ? $releaseToCompare : $releaseToCompare->release);
        $release = $this->evalRelease($this->release);

        for ($i = 0; $i < min(count($release), count($releaseToCompare)); $i++) {
            if ($release[$i] > $releaseToCompare[$i]) {
                return $i + 1;
            }
        }

        return -1;
    }

    private function evalRelease($release)
    {
        return strpos($release, '-') !== false ? explode('-', $release) : explode('.', $release);
    }

    private function checkFormat($release)
    {
        $patternDate = '/^[0-9]{4}-[0-9]{2}-[0-9]{2}-[0-9]{1,2}$/';
        if (preg_match($patternDate, $release) === 1) {
            return true;
        }
        $patternSemVersion = '/^' . SEMVER . '$/';
        if (preg_match($patternSemVersion, $release) === 1) {
            return true;
        }

        return false;
    }
}
