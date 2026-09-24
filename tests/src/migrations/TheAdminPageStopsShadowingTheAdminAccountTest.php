<?php

namespace YesWiki\Test\Migrations;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Entity\PageType;
use YesWiki\Content\Service\PageManager;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** The migration that stops a seeded page from shadowing an account of the same name (ADR-0001). */
class TheAdminPageStopsShadowingTheAdminAccountTest extends YesWikiTestCase
{
    private const TAG = 'MigrationShadowTestAccount';

    private const SEEDED_REDIRECT = '{{redirect page="GererSite"}}';

    protected function tearDown(): void
    {
        $this->getWiki()->services->get(PageManager::class)->deleteOrphaned(self::TAG);

        parent::tearDown();
    }

    /**
     * A tag carrying both a current account and a current page.
     *
     * Written straight to the table, the way the seed did: `PageManager::save()` marks every earlier
     * revision of a tag `latest = 'N'`, so it maintains the invariant and cannot reproduce this.
     */
    private function shadowTheAccount(string $pageContent): void
    {
        $dbService = $this->getWiki()->services->get(DbService::class);
        $pages = trim($dbService->prefixTable('pages'));
        $now = date('Y-m-d H:i:s');

        foreach ([
            [PageType::USER, PageBody::encode(['username' => self::TAG, 'email' => 'shadow@example.org'])],
            [PageType::PAGE, PageBody::encode([PageBody::CONTENT => $pageContent])],
        ] as [$type, $body]) {
            $dbService->query(
                "INSERT INTO {$pages} ({$dbService->quoteIdentifier('tag')}, {$dbService->quoteIdentifier('time')},"
                . " {$dbService->quoteIdentifier('body')}, {$dbService->quoteIdentifier('owner')},"
                . " {$dbService->quoteIdentifier('user')}, {$dbService->quoteIdentifier('latest')},"
                . " {$dbService->quoteIdentifier('type')}, {$dbService->quoteIdentifier('parent')})"
                . " VALUES (?, ?, ?, '', '', 'Y', ?, '')",
                [self::TAG, $now, $body, $type]
            );
        }
    }

    private function migrate(): void
    {
        require_once 'src/migrations/20260909130000_TheAdminPageStopsShadowingTheAdminAccount.php';
        $wiki = $this->getWiki();
        $migration = new \TheAdminPageStopsShadowingTheAdminAccount();
        $migration->setServices($wiki->services);
        $migration->setDbService($wiki->services->get(DbService::class));
        $migration->setParams($wiki->services->get(ParameterBagInterface::class));
        $migration->run();
    }

    /**
     * @return array<string, int> type => how many current rows carry it
     */
    private function currentRowsByType(): array
    {
        $dbService = $this->getWiki()->services->get(DbService::class);
        $rows = $dbService->loadAll(
            'SELECT type, COUNT(*) AS total FROM ' . trim($dbService->prefixTable('pages'))
            . " WHERE tag = ? AND latest = 'Y' GROUP BY type",
            [self::TAG]
        );

        $counts = [];
        foreach ($rows as $row) {
            $counts[(string)$row['type']] = (int)$row['total'];
        }
        ksort($counts);

        return $counts;
    }

    public function testTheSeededRedirectGoesAndTheAccountStays(): void
    {
        $this->shadowTheAccount(self::SEEDED_REDIRECT);
        $this->assertSame([PageType::PAGE => 1, PageType::USER => 1], $this->currentRowsByType());

        $this->migrate();

        $this->assertSame([PageType::USER => 1], $this->currentRowsByType());
    }

    /** Every revision of the page goes, not just the current one, or the tag is still doubled. */
    public function testNoRevisionOfTheRemovedPageIsLeftBehind(): void
    {
        $this->shadowTheAccount(self::SEEDED_REDIRECT);

        $this->migrate();

        $dbService = $this->getWiki()->services->get(DbService::class);
        $remaining = $dbService->loadAll(
            'SELECT type FROM ' . trim($dbService->prefixTable('pages')) . ' WHERE tag = ?',
            [self::TAG]
        );

        $this->assertNotSame([], $remaining, 'the account is still there');
        foreach ($remaining as $row) {
            $this->assertSame(PageType::USER, (string)$row['type']);
        }
    }

    /** Somebody's own content is not this migration's to delete. */
    public function testAPageSomeoneWroteIntoIsLeftAlone(): void
    {
        $this->shadowTheAccount('Des notes que quelqu’un a écrites ici');

        $this->migrate();

        $this->assertSame([PageType::PAGE => 1, PageType::USER => 1], $this->currentRowsByType());
    }
}
