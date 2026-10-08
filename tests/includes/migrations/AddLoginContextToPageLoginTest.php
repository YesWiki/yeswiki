<?php

namespace YesWiki\Test\Core\Migration;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Core\Service\AclService;
use YesWiki\Core\Service\DbService;
use YesWiki\Core\Service\PageManager;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * PageLogin's {{login}} actions get their own context, and nothing else in the page changes.
 */
class AddLoginContextToPageLoginTest extends YesWikiTestCase
{
    private const TAG = 'AddLoginContextTestPage';

    protected function setUp(): void
    {
        $GLOBALS['wiki'] = $this->getWiki();
        require_once 'includes/migrations/20261004120000_AddLoginContextToPageLogin.php';
    }

    protected function tearDown(): void
    {
        $GLOBALS['wiki']->services->get(PageManager::class)->deleteOrphaned(self::TAG);
        $GLOBALS['wiki']->services->get(AclService::class)->delete(self::TAG);
    }

    public static function bodies(): array
    {
        return [
            'bare action' => ['{{login}}', '{{login context="login-page"}}'],
            'action with parameters' => ['{{login signupurl="0"}}', '{{login context="login-page" signupurl="0"}}'],
            'already contextualised' => ['{{login context="login-page" signupurl="0"}}', '{{login context="login-page" signupurl="0"}}'],
            'own context kept' => ['{{login context="mine"}}', '{{login context="mine"}}'],
            'several actions and text' => [
                "Bienvenue\n{{login template=\"modal\"}}\n\n{{button link=\"x\"}}\n{{login}}",
                "Bienvenue\n{{login context=\"login-page\" template=\"modal\"}}\n\n{{button link=\"x\"}}\n{{login context=\"login-page\"}}",
            ],
            'other actions starting with login untouched' => ['{{loginbar}} {{login-x}}', '{{loginbar}} {{login-x}}'],
            'a parameter merely containing context untouched' => ['{{login nocontext="1"}}', '{{login context="login-page" nocontext="1"}}'],
        ];
    }

    #[DataProvider('bodies')]
    public function testAddContext(string $body, string $expected)
    {
        $this->assertSame($expected, \AddLoginContextToPageLogin::addContext($body));
    }

    private function migration(): \AddLoginContextToPageLogin
    {
        $wiki = $GLOBALS['wiki'];
        $migration = new \AddLoginContextToPageLogin();
        $migration->setWiki($wiki);
        $migration->setDbService($wiki->services->get(DbService::class));
        $migration->setParams($wiki->services->get(ParameterBagInterface::class));

        return $migration;
    }

    public function testALoginWithoutContextIsContextualisedOnce()
    {
        $pageManager = $GLOBALS['wiki']->services->get(PageManager::class);
        $pageManager->save(self::TAG, "Bienvenue\n{{login}}", '', true);

        $this->migration()->contextualise(self::TAG);
        $page = $pageManager->getOne(self::TAG, null, false, true);
        $this->assertSame("Bienvenue\n{{login context=\"login-page\"}}", $page['body']);

        $this->migration()->contextualise(self::TAG);
        $this->assertSame($page['time'], $pageManager->getOne(self::TAG, null, false, true)['time']);
        $this->assertSame(2, count($pageManager->getRevisions(self::TAG)));
    }
}
