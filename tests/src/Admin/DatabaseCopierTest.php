<?php

namespace YesWiki\Test\Admin;

use YesWiki\Admin\Service\DatabaseCopier;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** db:copy onto another engine: the installer's schema, every row, the same bytes, and no second copy over the first. */
class DatabaseCopierTest extends YesWikiTestCase
{
    private string $file = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->file = sys_get_temp_dir() . '/yeswiki-db-copy-' . bin2hex(random_bytes(4)) . '.sqlite';
    }

    protected function tearDown(): void
    {
        @unlink($this->file);
        parent::tearDown();
    }

    public function testTheWikiArrivesWholeOnAnotherEngine(): void
    {
        $db = $this->getWiki()->services->get(DbService::class);
        $prefix = trim($db->prefixTable(''));
        $copier = new DatabaseCopier($db);
        $target = DatabaseCopier::connect('sqlite', '', '', $this->file, '', '');

        $counts = $copier->copy($target, $prefix);

        $this->assertArrayHasKey($prefix . 'pages', $counts);
        $this->assertArrayHasKey($prefix . 'journal', $counts);
        foreach ($counts as $table => [$from, $to]) {
            $this->assertSame($from, $to, "{$table} lost or gained rows");
        }
        $this->assertGreaterThan(0, $counts[$prefix . 'pages'][1], 'the wiki has pages to copy');

        $source = $db->loadSingle('SELECT id, body FROM ' . $db->prefixTable('pages') . " WHERE latest = 'Y' ORDER BY id DESC LIMIT 1");
        $this->assertNotNull($source);
        $copy = $target->prepare('SELECT body FROM "' . $prefix . 'pages" WHERE id = ?');
        $copy->execute([$source['id']]);
        $this->assertSame(json_decode((string)$source['body'], true), json_decode((string)$copy->fetchColumn(), true));
    }

    public function testASecondCopyOverTheFirstIsRefused(): void
    {
        $db = $this->getWiki()->services->get(DbService::class);
        $prefix = trim($db->prefixTable(''));
        $target = DatabaseCopier::connect('sqlite', '', '', $this->file, '', '');
        (new DatabaseCopier($db))->copy($target, $prefix);

        $this->expectException(\RuntimeException::class);
        (new DatabaseCopier($db))->copy($target, $prefix);
    }
}
