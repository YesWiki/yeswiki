<?php

namespace YesWiki\Test\Actions;

use YesWiki\Core\Controller\AuthController;
use YesWiki\Core\Service\BotGuard;
use YesWiki\Core\Service\DbService;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * {{adminbotguard}} shows admins the refused submissions per day and reason, and nothing to anyone else.
 */
class AdminBotGuardActionTest extends YesWikiTestCase
{
    private $wiki;
    private DbService $db;
    private int $maxTripleId;
    private int $totalBefore;

    protected function setUp(): void
    {
        $this->wiki = $this->getWiki();
        $this->db = $this->wiki->services->get(DbService::class);
        $this->maxTripleId = (int)($this->db->loadSingle('SELECT MAX(id) AS id FROM' . $this->db->prefixTable('triples'))['id'] ?? 0);
        $this->totalBefore = array_sum($this->wiki->services->get(BotGuard::class)->refusedLastDays(7));
        $this->db->query(
            'INSERT INTO' . $this->db->prefixTable('triples') . '(resource, property, value) VALUES '
            . "('" . BotGuard::COUNTER_RESOURCE . date('Y-m-d') . "', '" . BotGuard::REFUSED_PROPERTY . BotGuard::REFUSED_HONEYPOT . "', '41'),"
            . "('" . BotGuard::COUNTER_RESOURCE . date('Y-m-d', time() - 86400) . "', '" . BotGuard::REFUSED_PROPERTY . BotGuard::REFUSED_TOKEN_MISSING . "', '7')"
        );
    }

    protected function tearDown(): void
    {
        $this->wiki->services->get(AuthController::class)->logout();
        $this->db->query('DELETE FROM' . $this->db->prefixTable('triples') . 'WHERE id > ' . $this->maxTripleId);
    }

    public function testAnAdminSeesTheRefusalsPerDayAndReason()
    {
        if (empty($this->wiki->services->get(AuthController::class)->connectFirstAdmin())) {
            $this->markTestSkipped('no admin account in the test wiki');
        }

        $html = $this->wiki->Action('adminbotguard', 1, ['days' => '7']);

        $this->assertStringContainsString(_t('BOT_GUARD_REASON_HONEYPOT'), $html);
        $this->assertStringContainsString(_t('BOT_GUARD_REASON_TOKEN_MISSING'), $html);
        $this->assertStringContainsString('<td>' . date('d/m/Y', time() - 6 * 86400) . '</td>', $html);
        $this->assertStringNotContainsString('<td>' . date('d/m/Y', time() - 7 * 86400) . '</td>', $html);
        $this->assertStringContainsString('<th>' . ($this->totalBefore + 48) . '</th>', $html);
    }

    public function testAnyoneElseSeesNoFigure()
    {
        $html = $this->wiki->Action('adminbotguard', 1, []);

        $this->assertStringNotContainsString('<table', $html);
        $this->assertStringContainsString(_t('BAZ_NEED_ADMIN_RIGHTS'), $html);
    }
}
