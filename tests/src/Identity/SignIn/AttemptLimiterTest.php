<?php

namespace YesWiki\Test\Identity\SignIn;

use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Identity\Service\SignIn\AttemptLimiter;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\JournalSchema;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

class AttemptLimiterTest extends YesWikiTestCase
{
    private AttemptLimiter $limiter;

    private DbService $db;

    protected function setUp(): void
    {
        $this->limiter = self::getWiki()->services->get(AttemptLimiter::class);
        $this->db = self::getWiki()->services->get(DbService::class);
        $this->sweep();
    }

    protected function tearDown(): void
    {
        $this->sweep();
    }

    private function sweep(): void
    {
        $table = $this->db->quoteIdentifier(self::getWiki()->services->get(JournalSchema::class)->table());
        $this->db->query("DELETE FROM {$table} WHERE {$this->db->quoteIdentifier('target')} IN ('limit@x', 'someone-else@x', 'quiet@x')");
    }

    /** @return list<array<string, mixed>> */
    private function rows(string $where): array
    {
        $table = $this->db->quoteIdentifier(self::getWiki()->services->get(JournalSchema::class)->table());

        return $this->db->loadAll("SELECT * FROM {$table} WHERE {$where}");
    }

    private function recordFailure(string $who): void
    {
        self::getWiki()->services->get(AuthenticationService::class)->recordFailedLogin($who, 'wrong-password');
    }

    public function testTheLimitBlocksAfterFiveFailures(): void
    {
        for ($i = 0; $i < 4; $i++) {
            $this->recordFailure('limit@x');
        }
        $this->assertFalse($this->limiter->isBlocked('limit@x'));
        $this->recordFailure('limit@x');
        $this->assertTrue($this->limiter->isBlocked('limit@x'));
        $this->assertTrue($this->limiter->isBlocked('  LIMIT@x '));
        $this->assertFalse($this->limiter->isBlocked('someone-else@x'));
    }

    public function testAThrottledAttemptDoesNotExtendTheLimit(): void
    {
        $this->limiter->recordThrottled('p', 'quiet@x');
        $this->assertCount(1, $this->rows("action = 'login.throttled' AND target = 'quiet@x'"));
        $this->assertFalse($this->limiter->isBlocked('quiet@x'));
    }

    public function testNormalize(): void
    {
        $this->assertSame('marie', AttemptLimiter::normalize('  MaRie '));
    }

    public function testDateSubMinutesRunsOnThisDialect(): void
    {
        $this->assertNotNull($this->db->scalar('SELECT ' . $this->db->dateSubMinutes(15), null));
    }
}
