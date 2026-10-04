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

    /**
     * Moves a Doryphore instance's custom/ and tools/ aside, once, and names what moved.
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
            . "Port what is still needed into custom/ or custom/extensions/, then delete it from here.\n"
            . "Keep this folder while it exists: it is what tells YesWiki the move was done.\n\n"
            . ($moved === [] ? "Nothing was moved.\n" : '- ' . implode("\n- ", $moved) . "\n");
    }
}
