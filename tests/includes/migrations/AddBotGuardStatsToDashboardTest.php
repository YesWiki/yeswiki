<?php

namespace YesWiki\Test\Core;

use YesWiki\Core\Service\DbService;
use YesWiki\Core\Service\PageManager;

require_once 'tests/YesWikiTestCase.php';
require_once 'includes/YesWikiMigration.php';
require_once 'includes/migrations/20261002220000_AddBotGuardStatsToDashboard.php';

/**
 * The migration appends the BotGuard statistics once to the dashboard, and leaves a wiki without one alone.
 */
class AddBotGuardStatsToDashboardTest extends YesWikiTestCase
{
    private $wiki;
    private PageManager $pageManager;

    protected function setUp(): void
    {
        $this->wiki = $this->getWiki();
        $this->pageManager = $this->wiki->services->get(PageManager::class);
    }

    protected function tearDown(): void
    {
        $this->pageManager->deleteOrphaned(TestDashboardMigration::TAG);
    }

    private function migrate(): void
    {
        $migration = new TestDashboardMigration();
        $migration->setWiki($this->wiki);
        $migration->setDbService($this->wiki->services->get(DbService::class));
        $migration->run();
    }

    private function body(): ?string
    {
        return $this->pageManager->getOne(TestDashboardMigration::TAG, null, false, true)['body'] ?? null;
    }

    public function testTheSectionIsAppendedOnce()
    {
        $this->pageManager->save(TestDashboardMigration::TAG, "# Tableau de bord\n{{recentchanges}}\n\n", '', true);

        $this->migrate();
        $this->migrate();

        $this->assertSame("# Tableau de bord\n{{recentchanges}}\n\n" . \AddBotGuardStatsToDashboard::SECTION, $this->body());
    }

    public function testAWikiWithoutDashboardIsLeftAlone()
    {
        $this->migrate();

        $this->assertNull($this->body());
    }
}

class TestDashboardMigration extends \AddBotGuardStatsToDashboard
{
    public const TAG = 'BotGuardDashboardMigrationTest';
}
