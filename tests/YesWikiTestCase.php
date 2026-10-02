<?php

namespace YesWiki\Test\Core;

use PHPUnit\Framework\TestCase;
use YesWiki\Core\Service\BotGuard;
use YesWiki\Core\Service\ConfigurationFileProvider;
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
