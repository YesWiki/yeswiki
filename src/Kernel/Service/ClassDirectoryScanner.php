<?php

namespace YesWiki\Kernel\Service;

use Psr\Container\ContainerInterface;

/**
 * Finds the classes a convention names, in every module and every installed extension.
 *
 * Two conventions need this and they were about to be three: fields live in `src/<Module>/Field/`
 * and in an extension's `fields/`, template-data preparers in `src/<Module>/TemplateData/` and in
 * an extension's `templatedata/` (ticket 49). Both answer the same question -- where may a class
 * of this kind be, and under which namespace -- so both ask it here.
 *
 * **This is the one place allowed to read those directories.** It addresses Source paths only
 * (`src/`, `extensions/`), which ADR-0022 puts outside Storage's tiers: that is code, it never
 * moves, and it cannot live in a bucket. Anything reading a Data path belongs in Storage instead.
 */
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
            // the bundled sample extension is the one whose folder and class prefix disagree
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
     * A module that ships no preparer has no `TemplateData/`, and an extension that ships no
     * field has no `fields/`, so absence is the ordinary case rather than a problem.
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
