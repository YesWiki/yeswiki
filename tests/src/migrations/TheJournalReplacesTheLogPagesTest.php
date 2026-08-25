<?php

namespace YesWiki\Test\Migrations;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Content\Entity\PageBody;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\Journal;
use YesWiki\Kernel\Service\JournalSchema;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * Ticket 51: the log pages are imported before they are deleted.
 *
 * An upgrade that silently destroys an audit trail is the one thing an audit system exists to
 * prevent, so this is the half of the migration worth a test of its own.
 *
 * It runs inside a transaction that is always rolled back, and it calls the migration's two steps
 * rather than `run()`. Both are deliberate: the migration sweeps **every**
 * `LogDesActionsAdministratives*` page in the wiki, which on a developer's own wiki is their real
 * trail, and `run()` opens with a `CREATE TABLE IF NOT EXISTS` whose implicit commit on MySQL
 * would end the transaction that is supposed to be protecting it.
 */
class TheJournalReplacesTheLogPagesTest extends YesWikiTestCase
{
    private const TAG = 'LogDesActionsAdministratives20250101';

    private const LINES = "\n2025-01-01 09:15:00 . . . . MelanieMichel . . . . Suppression de la page ->\"\"MichelMelanie\"\"\n"
        . "\n2025-01-01 11:42:07 . . . . WikiAdmin . . . . Nouveau droit pour le handler Edit : @admins\n";

    public static function setUpBeforeClass(): void
    {
        self::getWiki();
        require_once 'src/migrations/20260826100000_TheJournalReplacesTheLogPages.php';
    }

    public function testTheOldPagesAreImportedThenDeleted(): void
    {
        $wiki = self::getWiki();
        $db = $wiki->services->get(DbService::class);
        $pages = $db->quoteIdentifier(trim($db->prefixTable('pages')));
        $journal = $db->quoteIdentifier($wiki->services->get(JournalSchema::class)->table());

        $this->assertTrue(
            $wiki->services->get(JournalSchema::class)->exists(),
            'the table the migration creates -- here already, from the installer or an earlier run'
        );

        $migration = new \TheJournalReplacesTheLogPages();
        $migration->setServices($wiki->services);
        $migration->setDbService($db);
        $migration->setParams($wiki->services->get(ParameterBagInterface::class));

        $db->beginTransaction();

        try {
            // Two revisions of the same day's page, the way an append-per-event log accumulated:
            // every line of the first is in the second, so the import has to see each fact once.
            foreach (['N', 'Y'] as $latest) {
                $db->query(
                    "INSERT INTO {$pages} (tag, {$db->quoteIdentifier('time')}, body, owner,"
                    . " {$db->quoteIdentifier('user')}, latest, {$db->quoteIdentifier('type')}, parent)"
                    . " VALUES (?, {$db->now()}, ?, '', '', ?, 'page', '')",
                    [self::TAG, PageBody::encode([PageBody::CONTENT => self::LINES]), $latest]
                );
            }

            $imported = $this->step($migration, 'importLegacyPages');
            $deleted = $this->step($migration, 'deleteLegacyPages');

            $this->assertSame(2, $imported, 'two facts, however many revisions carried them');
            $this->assertSame(1, $deleted, 'and the tag goes back to the namespace');

            $rows = $db->loadAll(
                "SELECT * FROM {$journal} WHERE {$db->quoteIdentifier('target')} = ?"
                . ' ORDER BY ' . $db->quoteIdentifier('at') . ' ASC',
                [self::TAG]
            );

            $this->assertCount(2, $rows);
            $this->assertSame('audit', $rows[0]['channel']);
            $this->assertSame(Journal::LEGACY, $rows[0]['action']);
            $this->assertSame('MelanieMichel', $rows[0]['actor']);
            $this->assertStringStartsWith('2025-01-01 09:15:00', (string)$rows[0]['at']);
            $this->assertSame(
                'Suppression de la page ->""MichelMelanie""',
                json_decode((string)$rows[0]['context'], true)['message'] ?? null,
                'the sentence is kept verbatim: rewriting somebody\'s history would be inventing it'
            );

            $this->assertSame(
                0,
                (int)$db->scalar("SELECT COUNT(*) FROM {$pages} WHERE tag = ?", 0, [self::TAG])
            );
        } finally {
            $db->rollBack();
        }
    }

    private function step(\TheJournalReplacesTheLogPages $migration, string $name): int
    {
        $method = new \ReflectionMethod($migration, $name);
        $method->setAccessible(true);

        return (int)$method->invoke($migration);
    }
}
