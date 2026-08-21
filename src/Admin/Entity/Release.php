<?php

namespace YesWiki\Admin\Entity;

class Release
{
    public const UNKNOW_RELEASE = '0000-00-00-0';

    /** @var string a date release, a semantic version, or UNKNOW_RELEASE */
    public $release;

    /**
     * @param mixed $release anything the repository index or the config file offered; whatever
     *                       does not read as a release becomes UNKNOW_RELEASE
     */
    public function __construct($release)
    {
        $this->release = $this::UNKNOW_RELEASE;
        if (is_string($release) && $this->checkFormat($release)) {
            $this->release = $release;
        }
    }

    public function __toString(): string
    {
        if ($this->release === $this::UNKNOW_RELEASE) {
            return _t('AU_UNKNOW');
        }

        return $this->release;
    }

    /**
     * Order this release against another one.
     *
     * @param Release|string $releaseToCompare
     *
     * @return int positive when this release is the newer of the two, 0 when they are the same,
     *             -1 when it is not newer
     */
    public function compare($releaseToCompare): int
    {
        if ((string)$releaseToCompare === $this->release) {
            return 0;
        }
        if (strpos($this->release, '.') !== false && strpos((string)$releaseToCompare, '.') === false) {
            return 1;
        }
        $other = $this->evalRelease(is_string($releaseToCompare) ? $releaseToCompare : $releaseToCompare->release);
        $release = $this->evalRelease($this->release);

        for ($i = 0; $i < min(count($release), count($other)); $i++) {
            if ($release[$i] > $other[$i]) {
                return $i + 1;
            }
        }

        return -1;
    }

    /** @return list<string> */
    private function evalRelease(string $release): array
    {
        return strpos($release, '-') !== false ? explode('-', $release) : explode('.', $release);
    }

    private function checkFormat(string $release): bool
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
