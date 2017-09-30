<?php
namespace AutoUpdate;

class Release
{
    const UNKNOW_RELEASE = "0000-00-00-0";
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

        $releaseToCompare = $this->evalRelease($releaseToCompare->release);
        $release = $this->evalRelease($this->release);

        for ($i = 0; $i < 4; $i++) {
            if ($release[$i] > $releaseToCompare[$i]) {
                return $i + 1;
            }
        }
        return -1;
    }

    private function evalRelease($release)
    {
        return explode('-', $release);
    }

    private function checkFormat($release)
    {
        $pattern = "/^[0-9]{4}-[0-9]{2}-[0-9]{2}-[0-9]{1}$/";
        if (preg_match($pattern, $release) === 1) {
            return true;
        }
        return false;
    }
}
