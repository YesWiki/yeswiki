<?php

namespace YesWiki\Kernel\Service;

use YesWiki\Kernel\Entity\ConfigurationFile;

/** Doryphore's custom/ and tools/ hold PHP Ectoplasme cannot boot with, so they wait in private/doryphore to be ported one by one. */
final class DoryphoreLeftovers
{
    public const ASIDE = 'private/doryphore';

    public const CORE_TOOLS = [
        'aceditor', 'attach', 'autoupdate', 'bazar', 'contact', 'helloworld', 'lang', 'login',
        'progressBar', 'rss', 'security', 'syndication', 'tableau', 'tags', 'templates', 'toc',
    ];

    /** What in a Doryphore custom/ is a look rather than code: path pattern under custom/ => the extensions it may hold. */
    public const LOOK = [
        'css-presets' => ['css'],
        'themes/*/presets' => ['css'],
        'fonts' => ['woff2', 'woff', 'ttf', 'otf', 'eot', 'svg', 'css'],
        'images' => ['png', 'jpg', 'jpeg', 'gif', 'svg', 'webp', 'avif', 'ico'],
        'styles' => ['css'],
    ];

    /**
     * Moves a Doryphore instance's custom/ and tools/ aside, once, puts its look back, and names what moved.
     *
     * @return list<string>
     */
    public static function setAside(string $instanceDir, string $configFile): array
    {
        $aside = $instanceDir . '/' . self::ASIDE;
        if (is_dir($aside)) {
            return [];
        }
        if (!is_dir($instanceDir . '/custom') && !is_dir($instanceDir . '/tools')) {
            return [];
        }
        if (!self::isOlderThanEctoplasme($configFile)) {
            return [];
        }
        if (!@mkdir($aside, 0755, true) && !is_dir($aside)) {
            throw new \RuntimeException("cannot create {$aside} to set Doryphore's custom/ and tools/ aside");
        }

        $moved = [];
        if (is_dir($instanceDir . '/custom') && @rename($instanceDir . '/custom', $aside . '/custom')) {
            $moved[] = 'custom/';
            foreach (self::keepLook($aside . '/custom', $instanceDir . '/custom') as $kept) {
                $moved[] = "custom/{$kept}/ (a look, not code: copied back into custom/)";
            }
        }
        if (is_dir($instanceDir . '/tools') && @rename($instanceDir . '/tools', $aside . '/tools')) {
            foreach (glob($aside . '/tools/*', GLOB_ONLYDIR) ?: [] as $tool) {
                $name = basename($tool);
                $moved[] = in_array($name, self::CORE_TOOLS, true)
                    ? "tools/{$name}/ (Doryphore core, nothing to port)"
                    : "tools/{$name}/ (extension, to port)";
            }
        }

        file_put_contents($aside . '/README.md', self::readme($moved));

        return $moved;
    }

    /**
     * The look files a set-aside custom/ holds, relative to custom/, each with its file on disk.
     *
     * @return array<string, string>
     */
    public static function lookIn(string $customDir): array
    {
        $files = [];
        foreach (self::LOOK as $pattern => $extensions) {
            foreach (glob($customDir . '/' . $pattern, GLOB_ONLYDIR) ?: [] as $directory) {
                $iterator = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)
                );
                foreach ($iterator as $file) {
                    if (!$file->isFile() || $file->isLink()) {
                        continue;
                    }
                    if (!in_array(strtolower($file->getExtension()), $extensions, true)) {
                        continue;
                    }
                    $files[substr($file->getPathname(), strlen($customDir) + 1)] = $file->getPathname();
                }
            }
        }
        ksort($files);

        return $files;
    }

    /**
     * Copy the look files back into a fresh custom/, and name the folders they came from.
     *
     * @return list<string>
     */
    private static function keepLook(string $from, string $to): array
    {
        $folders = [];
        foreach (self::lookIn($from) as $relative => $file) {
            $target = $to . '/' . $relative;
            if (!is_dir(dirname($target)) && !@mkdir(dirname($target), 0755, true) && !is_dir(dirname($target))) {
                continue;
            }
            if (!@copy($file, $target)) {
                continue;
            }
            $folders[self::folderOf($relative)] = true;
        }

        return array_keys($folders);
    }

    /** The LOOK folder a relative path sits in: `fonts`, `themes/margot/presets`... */
    private static function folderOf(string $relative): string
    {
        $parts = explode('/', $relative);

        return $parts[0] === 'themes' ? implode('/', array_slice($parts, 0, 3)) : $parts[0];
    }

    /** Whether the configuration names a version before Ectoplasme. */
    private static function isOlderThanEctoplasme(string $configFile): bool
    {
        $config = new ConfigurationFile($configFile);
        $config->load();
        $version = strtolower((string)$config['yeswiki_version']);

        return $version !== '' && $version !== 'ectoplasme';
    }

    /** @param list<string> $moved */
    private static function readme(array $moved): string
    {
        return "# Set aside from Doryphore\n\n"
            . 'On ' . date('Y-m-d H:i') . ", the upgrade to Ectoplasme moved this wiki's custom code here, where it is not loaded.\n"
            . "Its presets, fonts, images and stylesheets were copied back into custom/, since they hold no code;\n"
            . "the copies here are the originals, and the migrations rewrite the presets in custom/ for Ectoplasme.\n"
            . "Port what is still needed into custom/ or custom/extensions/, then delete it from here.\n"
            . "Keep this folder while it exists: it is what tells YesWiki the move was done.\n\n"
            . ($moved === [] ? "Nothing was moved.\n" : '- ' . implode("\n- ", $moved) . "\n");
    }
}
