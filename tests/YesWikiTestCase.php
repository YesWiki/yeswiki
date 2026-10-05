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
     * Makes Twig look for templates again, since it remembers one it found missing, even after a test writes it.
     */
    protected static function forgetTemplateLookups(Wiki $wiki): void
    {
        $loader = (new \ReflectionProperty(TemplateEngine::class, 'twigLoader'))->getValue($wiki->services->get(TemplateEngine::class));
        foreach (['cache', 'errorCache'] as $property) {
            (new \ReflectionProperty(FilesystemLoader::class, $property))->setValue($loader, []);
        }
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
