<?php

namespace YesWiki\Test\Core;

use PHPUnit\Framework\TestCase;
use Twig\Loader\FilesystemLoader;
use YesWiki\Core\Service\BotGuard;
use YesWiki\Core\Service\ConfigurationFileProvider;
use YesWiki\Core\Service\TemplateEngine;
use YesWiki\Core\YesWikiLoader;
use YesWiki\Wiki;

class YesWikiTestCase extends TestCase
{
    protected static function getWiki(): Wiki
    {
        require_once 'includes/YesWikiLoader.php';
        $wiki = YesWikiLoader::getWiki(true);

        return $wiki;
    }

    /**
     * Gives a test custom/templates/<namespace>/ for its templates, seen by Twig even if created after boot; returns the folders it created.
     */
    protected static function prepareCustomTemplates(Wiki $wiki, string $namespace): array
    {
        $created = [];
        foreach (['custom/templates', "custom/templates/$namespace"] as $folder) {
            if (!is_dir($folder)) {
                mkdir($folder, 0777, true);
                $created[] = $folder;
            }
        }
        $loader = (new \ReflectionProperty(TemplateEngine::class, 'twigLoader'))->getValue($wiki->services->get(TemplateEngine::class));
        if (!in_array("custom/templates/$namespace", array_map(fn ($path) => rtrim($path, '/'), $loader->getPaths($namespace)), true)) {
            $loader->prependPath("custom/templates/$namespace", $namespace);
        }
        foreach (['cache', 'errorCache'] as $property) {
            (new \ReflectionProperty(FilesystemLoader::class, $property))->setValue($loader, []);
        }

        return array_reverse($created);
    }

    /**
     * Guard fields an anonymous submission passes with; ALTCHA stays off until restoreBotGuard().
     */
    protected static function validBotGuardFields(Wiki $wiki): array
    {
        $botGuard = $wiki->services->get(BotGuard::class);
        $botGuard->useAltcha(false);
        $botGuard->useConfigFileAndClock(ConfigurationFileProvider::getConfigFileFromEnv(), fn () => time() - 10);
        preg_match_all('/<input type="(?:hidden|text)"[^>]*name="([^"]+)" value="([^"]*)"/', $botGuard->fields(), $inputs, PREG_SET_ORDER);
        $botGuard->useConfigFileAndClock(ConfigurationFileProvider::getConfigFileFromEnv());

        return array_column($inputs, 2, 1);
    }

    /**
     * Turns ALTCHA back on after validBotGuardFields().
     */
    protected static function restoreBotGuard(Wiki $wiki): void
    {
        $wiki->services->get(BotGuard::class)->useAltcha(true);
    }
}
