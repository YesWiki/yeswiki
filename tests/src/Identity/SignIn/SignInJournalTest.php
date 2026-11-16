<?php

namespace YesWiki\Test\Identity\SignIn;

use YesWiki\Identity\Service\SignIn\SignInJournal;
use YesWiki\Identity\SignIn\Contract\FailureReason;
use YesWiki\Identity\SignIn\Contract\ProviderConfig;
use YesWiki\Identity\SignIn\SignInTrace;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\JournalSchema;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

class SignInJournalTest extends YesWikiTestCase
{
    private SignInJournal $journal;

    protected function setUp(): void
    {
        $this->journal = self::getWiki()->services->get(SignInJournal::class);
        $this->sweep();
    }

    protected function tearDown(): void
    {
        $this->sweep();
    }

    private function sweep(): void
    {
        $db = self::getWiki()->services->get(DbService::class);
        $table = $db->quoteIdentifier(self::getWiki()->services->get(JournalSchema::class)->table());
        $db->query("DELETE FROM {$table} WHERE {$db->quoteIdentifier('action')} LIKE 'login.%jrnl%' OR {$db->quoteIdentifier('action')} = 'login.failed' AND {$db->quoteIdentifier('target')} IN ('marie@x', 'a@x', 'b@x', 'x')");
    }

    /** @return list<array<string, mixed>> */
    private function rows(string $where): array
    {
        $db = self::getWiki()->services->get(DbService::class);
        $table = $db->quoteIdentifier(self::getWiki()->services->get(JournalSchema::class)->table());

        return $db->loadAll("SELECT * FROM {$table} WHERE {$where} ORDER BY id");
    }

    /** @param array<string, mixed> $connection */
    private function config(string $id, array $connection = []): ProviderConfig
    {
        return ProviderConfig::fromArray($id, ['type' => 'oidc', 'connection' => $connection]);
    }

    /** @param array<string, mixed> $attributes */
    private function trace(array $attributes = [], string $error = ''): SignInTrace
    {
        return new SignInTrace('account_matched', $attributes, [], [], null, $error);
    }

    private function failTwice(string $first, string $second): void
    {
        $config = $this->config('jrnl');
        foreach ([$first, $second] as $who) {
            $this->journal->failure($config, $who, FailureReason::EmailConflict, $this->trace());
        }
    }

    public function testARefusalWritesOneAuditRowAndOneDiagnostic(): void
    {
        $this->journal->failure($this->config('jrnl'), 'marie@x', FailureReason::EmailConflict, $this->trace(['email' => 'marie@x']));
        $audit = $this->rows("channel = 'audit' AND action = 'login.failed' AND target = 'marie@x'");
        $this->assertCount(1, $audit);
        $this->assertSame(['reason' => 'email_conflict', 'provider' => 'jrnl'], json_decode($audit[0]['context'], true));
        $diagnostic = $this->rows("channel = 'diagnostic' AND action = 'login.refused.email_conflict.jrnl'");
        $this->assertCount(1, $diagnostic);
        $context = json_decode($diagnostic[0]['context'], true);
        $this->assertSame('marie@x', $context['attributes']['email']);
        $this->assertSame('account_matched', $context['last_step']);
    }

    public function testSameProviderAndReasonCollapse(): void
    {
        $this->failTwice('a@x', 'b@x');
        $rows = $this->rows("action = 'login.refused.email_conflict.jrnl'");
        $this->assertCount(1, $rows);
        $this->assertSame(2, (int)$rows[0]['repeat']);
        $this->assertSame('b@x', $rows[0]['target']);
        $this->assertCount(2, $this->rows("channel = 'audit' AND action = 'login.failed' AND target IN ('a@x', 'b@x')"));
    }

    public function testAWrongPasswordHasNoDiagnostic(): void
    {
        $this->journal->failure($this->config('jrnl'), 'x', FailureReason::WrongCredentials, $this->trace());
        $this->assertCount(0, $this->rows("action LIKE 'login.refused.%jrnl'"));
    }

    public function testSecretsAreScrubbed(): void
    {
        $config = $this->config('jrnl', ['client_secret' => 'hunter2']);
        $this->journal->failure($config, 'x', FailureReason::ProviderRejected, $this->trace([], 'bad client hunter2'));
        $rows = $this->rows("action = 'login.refused.provider_rejected.jrnl'");
        $this->assertCount(1, $rows);
        $this->assertStringNotContainsString('hunter2', $rows[0]['context']);
    }

    /** A provider may answer with a whole HTML page: the diagnostic keeps its start, and says it was cut. */
    public function testALongErrorIsCut(): void
    {
        $page = '<html>' . str_repeat('é', 3000) . 'THE-END</html>';
        $this->journal->failure($this->config('jrnl'), 'x', FailureReason::ProviderRejected, $this->trace([], $page));

        $rows = $this->rows("action = 'login.refused.provider_rejected.jrnl'");
        $this->assertCount(1, $rows);
        $error = json_decode($rows[0]['context'], true)['error'];
        $this->assertLessThanOrEqual(SignInJournal::ERROR_BYTES + 100, strlen($error));
        $this->assertStringStartsWith('<html>éé', $error);
        $this->assertStringNotContainsString('THE-END', $error);
        $this->assertStringContainsString('[…]', $error);
        $this->assertTrue(mb_check_encoding($error, 'UTF-8'), 'cut between characters');

        $this->sweep();
        $this->journal->failure($this->config('jrnl'), 'x', FailureReason::ProviderRejected, $this->trace([], 'short'));
        $this->assertSame('short', json_decode($this->rows("action = 'login.refused.provider_rejected.jrnl'")[0]['context'], true)['error']);
    }

    public function testAWarningIsRecordedUnderItsKind(): void
    {
        $this->journal->warning($this->config('jrnl', ['client_secret' => 'hunter2']), 'x', 'group_sync', 'could not read hunter2');
        $rows = $this->rows("action = 'login.warning.group_sync.jrnl'");
        $this->assertCount(1, $rows);
        $this->assertStringNotContainsString('hunter2', $rows[0]['context']);
    }
}
