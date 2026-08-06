<?php

namespace YesWiki\Test\Core\Migrations;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Content\Service\PageManager;
use YesWiki\Kernel\Routing\ReservedTags;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * Ticket 20's migration: Content already sitting on a tag the router owns is renamed off
 * it, because such a row is unreachable by its own tag and renaming is the only thing that
 * gives it a URL back.
 *
 * The fixture is inserted with raw SQL on purpose -- PageManager::save() now refuses a
 * reserved tag, which is exactly the state a real upgrading wiki is in and exactly the
 * state no supported API can reproduce.
 *
 * Safety: if this wiki genuinely holds Content on a reserved tag, the test skips rather
 * than running a rename over somebody's real data as a side effect of `make test`. That
 * wiki wants the actual migration, not this.
 */
class RenameContentOffReservedTagsTest extends YesWikiTestCase
{
    public static function setUpBeforeClass(): void
    {
        // YesWikiMigration is only autoloadable once getWiki() has registered
        // src/autoload.inc.php's fallback autoloader
        self::getWiki();
        require_once 'src/migrations/20260801000000_RenameContentOffReservedTags.php';
    }

    public function testContentOnAReservedTagIsRenamedAndItsTriplesFollow(): void
    {
        $wiki = $this->getWiki();
        $dbService = $wiki->services->get(DbService::class);
        $pageManager = $wiki->services->get(PageManager::class);
        $pages = $dbService->prefixTable('pages');
        $triples = $dbService->prefixTable('triples');
        $reserved = ReservedTags::NAMES[0];

        if ($pageManager->tagExists($reserved)) {
            $this->markTestSkipped("this wiki holds real Content on '{$reserved}'; run the migration, do not test on it");
        }

        $expectedNewTag = $pageManager->suggestFreeTag($reserved);

        try {
            $dbService->query(
                "INSERT INTO {$pages} (tag, time, body, owner, user, latest, parent)"
                . " VALUES ('{$dbService->escape($reserved)}', '2026-01-01 00:00:00',"
                . " '" . $dbService->escape('{"content":"content stranded on a reserved tag"}') . "',"
                . " '', '', 'Y', '')"
            );
            $dbService->query(
                "INSERT INTO {$triples} (resource, property, value)"
                . " VALUES ('{$dbService->escape($reserved)}', 'reserved-tag-test-property', 'fixture')"
            );

            $migration = new \RenameContentOffReservedTags();
            $migration->setServices($wiki->services);
            $migration->setDbService($dbService);
            $migration->setParams($wiki->services->get(ParameterBagInterface::class));
            $migration->run();

            $this->assertFalse(
                $pageManager->tagExists($reserved),
                'nothing may be left on the reserved tag'
            );
            $this->assertTrue(
                $pageManager->tagExists($expectedNewTag),
                "the Content should have moved to '{$expectedNewTag}'"
            );
            $this->assertNotEmpty(
                $dbService->loadAll(
                    "SELECT 1 FROM {$triples} WHERE resource = '{$dbService->escape($expectedNewTag)}'"
                    . " AND property = 'reserved-tag-test-property'"
                ),
                'the triples keyed on the old tag must follow it, or the Content loses its type and keywords'
            );

            // idempotent: a second pass has nothing to do and must not move it again
            $migration->run();
            $this->assertTrue($pageManager->tagExists($expectedNewTag), 'a second run must be a no-op');
        } finally {
            foreach ([$reserved, $expectedNewTag] as $tag) {
                $dbService->query("DELETE FROM {$pages} WHERE tag = '{$dbService->escape($tag)}'");
                $dbService->query(
                    "DELETE FROM {$triples} WHERE resource = '{$dbService->escape($tag)}'"
                    . " AND property = 'reserved-tag-test-property'"
                );
            }
        }
    }
}
