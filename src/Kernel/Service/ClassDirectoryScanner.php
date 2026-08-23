<?php

namespace YesWiki\Kernel\Service;

use Psr\Container\ContainerInterface;

/** Finds the classes a convention names, in every module and every installed extension. */
class ClassDirectoryScanner
{
    public function __construct(private readonly ContainerInterface $container)
    {
    }

    /**
     * Every directory where a class of this convention may live, keyed by its namespace prefix.
     *
     * @param string $moduleSubdirectory    e.g. `Field`, matched under `src/<Module>/`
     * @param string $extensionSubdirectory e.g. `fields`, matched under an extension's root
     *
     * @return array<string, string> namespace prefix => absolute directory
     */
    public function directories(string $moduleSubdirectory, string $extensionSubdirectory): array
    {
        $found = [];

        foreach (glob(YESWIKI_PROGRAM_DIR . '/src/*/' . $moduleSubdirectory, GLOB_ONLYDIR) ?: [] as $dir) {
            $module = basename(dirname($dir));
            $found['YesWiki\\' . $module . '\\' . $moduleSubdirectory . '\\'] = $dir;
        }

        foreach ($this->container->get(ExtensionRegistry::class)->all() as $key => $extensionDir) {
            $name = ucfirst($key);
            if ($name === 'Helloworld') {
                $name = 'HelloWorld';
            }
            $dir = realpath($extensionDir);
            if ($dir === false) {
                continue;
            }
            $found['YesWiki\\' . $name . '\\' . $moduleSubdirectory . '\\'] = $dir . '/' . $extensionSubdirectory;
        }

        return $found;
    }

    /**
     * The filenames in $directory, or nothing at all when it does not exist.
     *
     * @return list<string>
     */
    public function filesIn(string $directory): array
    {
        if (!is_dir($directory)) {
            return [];
        }

        return array_values(array_diff(scandir($directory) ?: [], ['.', '..']));
    }
}
