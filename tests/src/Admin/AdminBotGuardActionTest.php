<?php

namespace YesWiki\Test\Admin;

use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Identity\Service\BotGuard;
use YesWiki\Identity\Service\GroupManager;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Render\Service\ActionRunner;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** `{{adminbotguard}}` shows refusals to admins only. */
class AdminBotGuardActionTest extends YesWikiTestCase
{
    private DbService $db;
    private int $maxTripleId;
    private int $totalBefore;

    protected function setUp(): void
    {
        parent::setUp();
        $services = $this->getWiki()->services;
        $this->db = $services->get(DbService::class);
        $this->maxTripleId = (int)($this->db->loadSingle('SELECT MAX(id) AS id FROM ' . $this->db->prefixTable('triples'))['id'] ?? 0);
        $this->totalBefore = array_sum($services->get(BotGuard::class)->refusedLastDays(7));
        $this->db->query(
            'INSERT INTO ' . $this->db->prefixTable('triples') . ' (resource, property, value) VALUES (?, ?, ?), (?, ?, ?)',
            [
                BotGuard::RESOURCE_PREFIX . date('Y-m-d'), BotGuard::REFUSED_PROPERTY . BotGuard::REFUSED_HONEYPOT, '41',
                BotGuard::RESOURCE_PREFIX . date('Y-m-d', time() - 86400), BotGuard::REFUSED_PROPERTY . BotGuard::REFUSED_TOKEN_MISSING, '7',
            ]
        );
    }

    protected function tearDown(): void
    {
        $this->getWiki()->services->get(AuthenticationService::class)->logout();
        $this->db->query('DELETE FROM ' . $this->db->prefixTable('triples') . ' WHERE id > ? AND resource LIKE ?', [$this->maxTripleId, BotGuard::RESOURCE_PREFIX . '%']);
        parent::tearDown();
    }

    public function testAnAdminSeesTheRefusalsPerDayAndReason(): void
    {
        $services = $this->getWiki()->services;
        $admins = $services->get(GroupManager::class)->getMembers('admins');
        $admin = $admins === [] ? null : $services->get(UserManager::class)->getOneByName($admins[0]);
        if ($admin === null) {
            $this->markTestSkipped('no admin account in the test wiki');
        }
        $services->get(AuthenticationService::class)->login($admin);

        $html = $services->get(ActionRunner::class)->action('adminbotguard days="7"');

        $this->assertStringContainsString(_t('BOT_GUARD_REASON_HONEYPOT'), $html);
        $this->assertStringContainsString(_t('BOT_GUARD_REASON_TOKEN_MISSING'), $html);
        $this->assertStringContainsString('<td>' . date('d/m/Y', time() - 6 * 86400) . '</td>', $html);
        $this->assertStringNotContainsString('<td>' . date('d/m/Y', time() - 7 * 86400) . '</td>', $html);
        $this->assertStringContainsString('<th>' . ($this->totalBefore + 48) . '</th>', $html);
    }

    public function testAnyoneElseSeesNoFigure(): void
    {
        $html = $this->getWiki()->services->get(ActionRunner::class)->action('adminbotguard');

        $this->assertStringNotContainsString('<table', $html);
        $this->assertStringContainsString(_t('BAZ_NEED_ADMIN_RIGHTS'), $html);
    }
}
