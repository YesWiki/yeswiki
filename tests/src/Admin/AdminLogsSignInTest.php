<?php

namespace YesWiki\Test\Admin;

use Symfony\Component\HttpFoundation\Request;
use YesWiki\Admin\Api\AdminLogsApiController;
use YesWiki\Identity\Service\AclService;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\JournalSchema;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** Failed sign-ins read in the log screen: a filter on the target, and the detail of a refusal. */
class AdminLogsSignInTest extends YesWikiTestCase
{
    private const ACTOR = 'AdminLogsSignInTestActor';

    private function db(): DbService
    {
        return self::getWiki()->services->get(DbService::class);
    }

    private function table(): string
    {
        return $this->db()->quoteIdentifier(self::getWiki()->services->get(JournalSchema::class)->table());
    }

    protected function setUp(): void
    {
        $this->clean();
    }

    protected function tearDown(): void
    {
        self::getWiki()->services->get(AuthenticationService::class)->logout();
        $this->clean();
    }

    private function clean(): void
    {
        $this->db()->query('DELETE FROM ' . $this->table() . ' WHERE ' . $this->db()->quoteIdentifier('actor') . ' = ?', [self::ACTOR]);
    }

    /** @param array<string, mixed> $context */
    private function seed(string $channel, string $action, string $target, array $context = []): void
    {
        $db = $this->db();
        $now = date('Y-m-d H:i:s');
        $columns = implode(', ', array_map(
            fn (string $column): string => $db->quoteIdentifier($column),
            ['at', 'last_at', 'repeat', 'channel', 'level', 'actor', 'action', 'target', 'context']
        ));
        $db->query(
            'INSERT INTO ' . $this->table() . " ({$columns}) VALUES (?, ?, 1, ?, 'warning', ?, ?, ?, ?)",
            [$now, $now, $channel, self::ACTOR, $action, $target, json_encode($context)]
        );
    }

    private function loginAsAdmin(): void
    {
        $services = self::getWiki()->services;
        $acl = $services->get(AclService::class);
        $admin = current(array_filter(
            $services->get(UserManager::class)->getAll(),
            fn ($user) => $acl->isAdmin($user['name'])
        ));
        $this->assertNotFalse($admin, 'need an existing admin on this wiki');
        $services->get(AuthenticationService::class)->login($admin);
    }

    /** @param array<string, string> $query */
    private function get(array $query): string
    {
        $controller = self::getWiki()->services->get(AdminLogsApiController::class);
        $this->loginAsAdmin();
        $method = new \ReflectionMethod(AdminLogsApiController::class, 'getEntries');

        return $method->invoke($controller, new Request($query + ['actor' => self::ACTOR]))->getContent();
    }

    /** @param array<string, mixed> $overrides */
    private function refusal(array $overrides = []): void
    {
        $this->seed('diagnostic', 'login.refused.email_conflict.acme', 'marie@x', $overrides + [
            'message' => 'Sign-in refused',
            'last_step' => 'attributes_read',
            'attributes' => ['given_name' => 'Marie', 'mail' => 'marie@x'],
            'attribute_use' => ['email' => ['attribute' => 'mail', 'present' => true]],
            'groups' => ['staff'],
            'account' => 'MarieDupont',
            'error' => 'boom',
        ]);
    }

    public function testFilterOnTarget(): void
    {
        $this->seed('audit', 'login.failed', 'marie@x', ['reason' => 'no_account', 'provider' => 'acme']);
        $this->seed('audit', 'login.failed', 'jean@x', ['reason' => 'no_account', 'provider' => 'acme']);

        $html = $this->get(['target' => 'marie@x']);
        $this->assertStringContainsString('marie@x', $html);
        $this->assertStringNotContainsString('jean@x', $html);
    }

    public function testTheTargetFilterIsABoundParameter(): void
    {
        $this->seed('audit', 'login.failed', 'marie@x');
        $html = $this->get(['target' => "x' OR '1'='1"]);
        $this->assertStringNotContainsString('marie@x', $html);
    }

    public function testADiagnosticShowsItsAttributesAndAFix(): void
    {
        $this->refusal();

        $html = $this->get(['action' => 'login.refused.email_conflict.acme']);
        $this->assertStringContainsString('given_name', $html);
        $this->assertStringContainsString('Marie', $html);
        $this->assertStringContainsString('MarieDupont', $html);
        $this->assertStringContainsString('boom', $html);
        $this->assertStringContainsString('staff', $html);
        $this->assertStringContainsString(htmlspecialchars(_t('SIGN_IN_FIX_EMAIL_CONFLICT'), ENT_QUOTES), $html);
        $this->assertStringContainsString('admin/sign-in/acme/edit', $html);
    }

    public function testAttributesAreEscaped(): void
    {
        $this->refusal([
            'attributes' => ['<b>name</b>' => '<script>alert(1)</script>'],
            'error' => '<img src=x onerror=1>',
            'account' => '<i>acc</i>',
            'groups' => ['<u>g</u>'],
        ]);

        $html = $this->get(['action' => 'login.refused.email_conflict.acme']);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        $this->assertStringContainsString('&lt;b&gt;name&lt;/b&gt;', $html);
        $this->assertStringContainsString('&lt;img src=x onerror=1&gt;', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('<img src=x', $html);
        $this->assertStringNotContainsString('<i>acc</i>', $html);
        $this->assertStringNotContainsString('<u>g</u>', $html);
    }

    public function testAnAuditRowShowsNoAttributes(): void
    {
        $this->seed('audit', 'login.failed', 'marie@x', ['reason' => 'email_conflict', 'provider' => 'acme']);

        $html = $this->get(['action' => 'login.failed']);
        $this->assertStringContainsString('marie@x', $html);
        $this->assertStringNotContainsString('yw-logs__sign-in', $html);
        $this->assertStringNotContainsString('given_name', $html);
    }

    public function testAJournalPhraseExistsForThrottled(): void
    {
        $this->assertNotSame('JOURNAL_ACTION_LOGIN_THROTTLED', _t('JOURNAL_ACTION_LOGIN_THROTTLED'));
    }
}
