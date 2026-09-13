<?php

namespace YesWiki\Test\Migrations;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Entity\Translations;
use YesWiki\Content\Service\PageManager;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** The migration that turns `{{lang="xx"}}` sections into translations and collapses the old switch. */
class PagesAreTranslatedLikeEntriesTest extends YesWikiTestCase
{
    private const WITH_SECTIONS = "intro\n{{lang=\"fr\"}}Bonjour\n{{lang=\"en\"}}Hello\n{{lang=\"eu\"}}Kaixo";

    private const WITH_SWITCH = " - {{translation destination=\"fr\"}}\n - {{translation destination=\"en\"}}\n - {{translation destination=\"es\"}}\n";

    private const TAG = 'MigrationLangSectionsTestPage';

    protected function tearDown(): void
    {
        $this->getWiki()->services->get(PageManager::class)->deleteOrphaned(self::TAG);

        parent::tearDown();
    }

    private function migrate(): void
    {
        require_once 'src/migrations/20260909120000_PagesAreTranslatedLikeEntries.php';
        $wiki = $this->getWiki();
        $migration = new \PagesAreTranslatedLikeEntries();
        $migration->setServices($wiki->services);
        $migration->setDbService($wiki->services->get(DbService::class));
        $migration->setParams($wiki->services->get(ParameterBagInterface::class));
        $migration->run();
    }

    /**
     * @return array<string, mixed>
     */
    private function bodyAfterMigrating(string $content): array
    {
        $pageManager = $this->getWiki()->services->get(PageManager::class);
        $pageManager->save(self::TAG, [PageBody::CONTENT => $content]);

        $this->migrate();

        $page = $pageManager->getOne(self::TAG, null, false, true);
        $this->assertIsArray($page);

        return is_array($page['body'] ?? null) ? $page['body'] : [];
    }

    public function testTheDefaultLanguageSectionBecomesThePagesOwnText(): void
    {
        $body = $this->bodyAfterMigrating(self::WITH_SECTIONS);

        $this->assertSame("intro\nBonjour\n", PageBody::content($body));
    }

    public function testEveryOtherSectionBecomesATranslation(): void
    {
        $body = $this->bodyAfterMigrating(self::WITH_SECTIONS);

        $this->assertSame("intro\nHello", Translations::of($body, 'en')[PageBody::CONTENT]);
        $this->assertSame("intro\nKaixo", Translations::of($body, 'eu')[PageBody::CONTENT]);
    }

    public function testTheMarkersAreGone(): void
    {
        $body = $this->bodyAfterMigrating(self::WITH_SECTIONS);

        $this->assertStringNotContainsString('{{lang=', PageBody::encode($body));
    }

    public function testTheOneFlagPerLanguageSwitchBecomesASingleCall(): void
    {
        $body = $this->bodyAfterMigrating(self::WITH_SWITCH);
        $content = PageBody::content($body);

        $this->assertStringNotContainsString('{{translation', $content);
        $this->assertSame(1, substr_count($content, '{{languages}}'));
        $this->assertStringContainsString(' - {{languages}}', $content);
    }

    public function testAPageWithNeitherIsLeftAlone(): void
    {
        $pageManager = $this->getWiki()->services->get(PageManager::class);
        $pageManager->save(self::TAG, [PageBody::CONTENT => 'du texte ordinaire']);
        $before = $pageManager->getOne(self::TAG, null, false, true);

        $this->migrate();

        $after = $pageManager->getOne(self::TAG, null, false, true);
        $this->assertIsArray($before);
        $this->assertIsArray($after);
        $this->assertSame($before['time'], $after['time']);
    }
}
