<?php

namespace YesWiki\Admin\Entity;

use YesWiki\Admin\Service\AutoUpdateService;
use YesWiki\Kernel\Service\ProgramVersion;

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
        'themes/README.md',
        'templates/README.md',
        'cache/README.md',
    ];

    /** The version an admin asked for, empty when they asked for none. */
    private string $requestedVersion = '';

    /** The version an admin asked for, the one fact this entity used to reach for through `$GLOBALS['yeswikiServices']`. */
    public function updateContext(string $requestedVersion): void
    {
        $this->requestedVersion = $requestedVersion;
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

        $this->localPath = YESWIKI_PROGRAM_DIR;
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

        $entries = $this->matching($this->extractionPath . '*');
        $dirs = array_filter($entries, fn (string $path) => $this->isDirectory($path));
        if ($dirs === []) {
            throw new \Exception(_t('AU_PACKAGE_NOT_UNZIPPED'), 1);
        }
        $this->extractionPath = reset($dirs) . '/';

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

        $copied = true;
        foreach ($this->entriesIn($this->extractionPath) as $file) {
            if (!in_array($file, self::IGNORED_FILES) && !$this->copy($this->extractionPath . '/' . $file, $desPath . '/' . $file)) {
                $copied = false;
            }
        }
        foreach (self::FILES_TO_ADD_TO_IGNORED_FOLDERS as $file) {
            if ($this->exists($this->extractionPath . '/' . $file) && !$this->copy($this->extractionPath . '/' . $file, $desPath . '/' . $file)) {
                $copied = false;
            }
        }

        foreach (['cache', 'files'] as $dirName) {
            if (!$this->isDirectory($desPath . '/' . $dirName)) {
                $this->makeDirectory($desPath . '/' . $dirName);
            }
        }

        return $copied;
    }

    public function upgradeDefaultTheme(): bool
    {
        $src = $this->extractionPath . '/themes/' . THEME_PAR_DEFAUT;
        $desPath = $this->localPath . '/themes/' . THEME_PAR_DEFAUT;
        $copied = true;
        foreach ($this->entriesIn($src) as $file) {
            if (!$this->copy($src . '/' . $file, $desPath . '/' . $file)) {
                $copied = false;
            }
        }

        return $copied;
    }

    public function upgradeTools(): bool
    {
        $src = $this->extractionPath . '/extensions';
        $desPath = $this->localPath . '/extensions';
        $copied = true;
        foreach ($this->entriesIn($src) as $file) {
            if (!$this->copy($src . '/' . $file, $desPath . '/' . $file)) {
                $copied = false;
            }
        }

        return $copied;
    }

    /** Nothing to record: the version is the program's own, read from its composer.json (replicas 20). */
    public function upgradeInfos(): bool
    {
        return true;
    }

    public function name(): string
    {
        return $this::CORE_NAME;
    }

    public function localVersion(): string
    {
        $line = (new ProgramVersion())->releaseLine();

        return strtolower($line !== '' ? $line : AutoUpdateService::DEFAULT_VERS);
    }

    public function requestedVersion(): string
    {
        $version = $this->localVersion();
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
        return new Release((new ProgramVersion())->release(Release::UNKNOW_RELEASE));
    }

    protected function updateAvailable(): bool
    {
        if ($this->release->compare($this->localRelease()) > 0) {
            return true;
        }

        return false;
    }
}
