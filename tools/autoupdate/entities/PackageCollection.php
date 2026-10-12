<?php

namespace YesWiki\AutoUpdate\Entity;

class PackageCollection extends Collection
{
    public const THEME_CLASS = 'YesWiki\AutoUpdate\Entity\PackageTheme';
    public const TOOL_CLASS = 'YesWiki\AutoUpdate\Entity\PackageTool';
    public const CORE_CLASS = 'YesWiki\AutoUpdate\Entity\PackageCore';

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

    /** The same packages, without the ones named in $names that are not installed. */
    public function withoutForbidden(array $names): PackageCollection
    {
        $kept = new PackageCollection();
        foreach ($this->list as $package) {
            if ($package->installed || !in_array($package->name, $names, true)) {
                $kept[] = $package;
            }
        }

        return $kept;
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
                break;

            case 'extension':
                return $this::TOOL_CLASS;
                break;

            case 'theme':
                return $this::THEME_CLASS;
                break;

            default:
                throw new \Exception(_t('AU_UNKWON_PACKAGE_TYPE'), 1);
                break;
        }
    }
}
