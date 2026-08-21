<?php

namespace YesWiki\Admin\Entity;

use YesWiki\Kernel\Entity\Collection;
use YesWiki\Kernel\Service\ConfigurationService;

class PackageCollection extends Collection
{
    public const THEME_CLASS = 'YesWiki\Admin\Entity\PackageTheme';
    public const TOOL_CLASS = 'YesWiki\Admin\Entity\PackageTool';
    public const CORE_CLASS = 'YesWiki\Admin\Entity\PackageCore';

    /**
     * The version an admin asked for, when they asked for one, and the service that reads the
     * configuration file. Only PackageCore wants either; it used to reach for both through
     * `$GLOBALS['yeswikiServices']` because an entity built from a class-string has nowhere else
     * to look (ticket 45).
     */
    protected string $requestedVersion = '';
    protected ?ConfigurationService $configurationService = null;

    public function add($release, $address, $file, $description, $documentation, $minimalPhpVersion = null)
    {
        $className = $this->getPackageType($file);
        $package = new $className(
            $release,
            $address . $file,
            $description,
            $documentation,
            $minimalPhpVersion
        );
        if ($package instanceof PackageCore) {
            $package->updateContext($this->requestedVersion, $this->configurationService);
        }
        $this->list[$package->name] = $package;
    }

    public function getPackage($packageName)
    {
        if (isset($this->list[$packageName])) {
            return $this->list[$packageName];
        }
    }

    public function getCorePackage()
    {
        if (isset($this->list['yeswiki'])) {
            return $this->list['yeswiki'];
        }
    }

    public function getThemesPackages()
    {
        return $this->filterPackages($this::THEME_CLASS);
    }

    public function getToolsPackages()
    {
        return $this->filterPackages($this::TOOL_CLASS);
    }

    private function filterPackages($class)
    {
        $filteredPackages = new PackageCollection();
        foreach ($this->list as $package) {
            if (get_class($package) === $class) {
                $filteredPackages[] = $package;
            }
        }

        return $filteredPackages;
    }

    private function getPackageType($filename)
    {
        $type = explode('-', $filename)[0];
        switch ($type) {
            case 'yeswiki':
                return $this::CORE_CLASS;

            case 'extension':
                return $this::TOOL_CLASS;

            case 'theme':
                return $this::THEME_CLASS;

            default:
                throw new \Exception(_t('AU_UNKWON_PACKAGE_TYPE'), 1);
        }
    }
}
