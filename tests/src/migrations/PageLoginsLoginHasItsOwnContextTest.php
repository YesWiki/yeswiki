<?php

namespace YesWiki\Test\Core\Migrations;

use PHPUnit\Framework\Attributes\DataProvider;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** PageLogin login context migration. */
class PageLoginsLoginHasItsOwnContextTest extends YesWikiTestCase
{
    public static function setUpBeforeClass(): void
    {
        self::getWiki();
        require_once 'src/migrations/20261007120000_PageLoginsLoginHasItsOwnContext.php';
    }

    /** @return array<string, array{string, string}> */
    public static function contents(): array
    {
        return [
            'bare action' => ['{{login}}', '{{login context="login-page"}}'],
            'action with parameters' => ['{{login template="login-form.twig" signupurl="0"}}', '{{login context="login-page" template="login-form.twig" signupurl="0"}}'],
            'already contextualised' => ['{{login context="login-page" signupurl="0"}}', '{{login context="login-page" signupurl="0"}}'],
            'own context kept' => ['{{login context="mine"}}', '{{login context="mine"}}'],
            'several actions and text' => [
                "Bienvenue\n{{login template=\"modal\"}}\n\n{{button link=\"x\"}}\n{{login}}",
                "Bienvenue\n{{login context=\"login-page\" template=\"modal\"}}\n\n{{button link=\"x\"}}\n{{login context=\"login-page\"}}",
            ],
            'other actions starting with login untouched' => ['{{loginbar}} {{login-x}}', '{{loginbar}} {{login-x}}'],
            'a parameter merely containing context' => ['{{login nocontext="1"}}', '{{login context="login-page" nocontext="1"}}'],
        ];
    }

    #[DataProvider('contents')]
    public function testAddContext(string $content, string $expected): void
    {
        $this->assertSame($expected, \PageLoginsLoginHasItsOwnContext::addContext($content));
    }

    public function testRunningItTwiceChangesNothingTheSecondTime(): void
    {
        $once = \PageLoginsLoginHasItsOwnContext::addContext('{{login}} {{login signupurl="0"}}');

        $this->assertSame($once, \PageLoginsLoginHasItsOwnContext::addContext($once));
    }
}
