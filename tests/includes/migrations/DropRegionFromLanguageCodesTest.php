<?php

namespace YesWiki\Test\Core\Migration;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Core\Service\DbService;
use YesWiki\Core\Service\PageManager;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * Language codes with a region are stored as their plain ISO 639 code.
 */
class DropRegionFromLanguageCodesTest extends YesWikiTestCase
{
    private const TAG = 'DropRegionFromLanguageCodesTestPage';

    private array $formIds = [];

    protected function setUp(): void
    {
        $GLOBALS['wiki'] = $this->getWiki();
        require_once 'includes/migrations/20261113000000_DropRegionFromLanguageCodes.php';
    }

    protected function tearDown(): void
    {
        foreach ($this->formIds as $id) {
            self::deleteLegacyForm($GLOBALS['wiki'], $id);
        }
        $GLOBALS['wiki']->services->get(PageManager::class)->deleteOrphaned(self::TAG);
    }

    private function migrate(): void
    {
        $wiki = $GLOBALS['wiki'];
        $migration = new \DropRegionFromLanguageCodes();
        $migration->setWiki($wiki);
        $migration->setDbService($wiki->services->get(DbService::class));
        $migration->setParams($wiki->services->get(ParameterBagInterface::class));
        $migration->run();
    }

    private function storedBody(): string
    {
        $dbService = $GLOBALS['wiki']->services->get(DbService::class);

        return $dbService->loadSingle('SELECT `body` FROM ' . $dbService->prefixTable('pages') . " WHERE `tag` = '" . self::TAG . "' AND `latest` = 'Y'")['body'];
    }

    private function savePage(string $body): void
    {
        $GLOBALS['wiki']->services->get(PageManager::class)->save(self::TAG, $body, '', true);
    }

    public function testABodyLanguageLosesItsRegion(): void
    {
        $this->savePage('{"lang":"fr-FR","label":"Été","fields":{},"list":[]}');

        $this->migrate();

        $this->assertSame('{"lang":"fr","label":"Été","fields":{},"list":[]}', json_encode(json_decode($this->storedBody()), JSON_UNESCAPED_UNICODE));
    }

    public function testAPlainLanguageIsKept(): void
    {
        $body = '{"lang":"en","label":"Kept"}';
        $this->savePage($body);

        $this->migrate();

        $this->assertSame($body, $this->storedBody());
    }

    public function testANatureRowLosesItsRegion(): void
    {
        $id = self::createLegacyForm($GLOBALS['wiki'], ['bn_label_nature' => 'Language codes migration test form', 'bn_template' => '', 'bn_ce_i18n' => 'fr-FR']);
        $this->formIds[] = $id;

        $this->migrate();

        $dbService = $GLOBALS['wiki']->services->get(DbService::class);
        $this->assertSame('fr', $dbService->loadSingle('SELECT `bn_ce_i18n` FROM ' . $dbService->prefixTable('nature') . " WHERE `bn_id_nature` = '{$id}'")['bn_ce_i18n']);
    }
}
