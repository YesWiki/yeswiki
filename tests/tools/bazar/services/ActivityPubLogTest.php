<?php

namespace YesWiki\Test\Bazar\Service;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use YesWiki\Bazar\Service\ActivityPubLog;

require_once 'includes/autoload.inc.php';

/**
 * The ActivityPub log writes nothing unless switched on, records who signed what, and forgets files older than two weeks.
 */
class ActivityPubLogTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/yeswiki-activitypub-log-' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir . '/*') ?: []);
        @rmdir($this->dir);
    }

    private function log(bool $enabled = true): ActivityPubLog
    {
        return new ActivityPubLog(new ParameterBag(['activitypub_log' => $enabled]), $this->dir);
    }

    private function records(): array
    {
        $file = $this->dir . '/' . date('Y-m-d') . '.jsonl';

        return array_map(fn ($line) => json_decode($line, true), file($file, FILE_IGNORE_NEW_LINES));
    }

    private function inboxRequest(string $body): Request
    {
        return Request::create('/actors/2/inbox', 'POST', [], [], [], [
            'HTTP_SIGNATURE' => 'keyId="https://mobilizon.example/@alice#main-key",algorithm="rsa-sha256",headers="(request-target) host date digest",signature="abc"',
            'HTTP_USER_AGENT' => 'Mobilizon 5.2.5',
            'HTTP_ACCEPT' => 'application/activity+json',
        ], $body);
    }

    public function testNothingIsWrittenWhileTheLogIsOff()
    {
        $this->log(false)->received('2', $this->inboxRequest('{"type":"Create"}'), 200, 'https://mobilizon.example/@alice');

        $this->assertDirectoryDoesNotExist($this->dir);
    }

    public function testAReceivedActivityIsLoggedWithItsSignerAndTheAnswer()
    {
        $activity = ['type' => 'Create', 'actor' => 'https://mobilizon.example/@alice', 'object' => ['type' => 'Event', 'id' => 'https://mobilizon.example/events/1']];

        $this->log()->received('2', $this->inboxRequest(json_encode($activity)), 403, 'https://mobilizon.example/@alice', 'This entry belongs to another actor');

        $record = $this->records()[0];
        $this->assertSame('in', $record['direction']);
        $this->assertSame('2', $record['form']);
        $this->assertSame('Create', $record['type']);
        $this->assertSame(403, $record['status']);
        $this->assertSame('This entry belongs to another actor', $record['error']);
        $this->assertSame('https://mobilizon.example/@alice#main-key', $record['request']['keyId']);
        $this->assertSame('Mobilizon 5.2.5', $record['request']['userAgent']);
        $this->assertSame($activity, $record['activity']);
    }

    public function testABodyThatIsNotJsonIsKeptAsTextAndCut()
    {
        $this->log()->received('2', $this->inboxRequest(str_repeat('x', ActivityPubLog::MAX_BODY_BYTES + 10)), 401, null, 'No signature');

        $record = $this->records()[0];
        $this->assertNull($record['peer']);
        $this->assertSame(ActivityPubLog::MAX_BODY_BYTES + strlen('…'), strlen($record['activity']));
    }

    public function testOneLinePerEvent()
    {
        $log = $this->log();
        $log->delivered('2', 'https://social.example/users/bob', 'https://social.example/users/bob/inbox', ['type' => 'Accept'], 202);
        $log->served('2', Request::create('/actors/2/outbox'), 200);
        $log->syncFailed('2', 'https://mobilizon.example/@group', ['type' => 'Event', 'id' => 'x'], 'champs requis : Titre');

        $this->assertSame(['out', 'get', 'sync'], array_column($this->records(), 'direction'));
        $this->assertSame('/actors/2/outbox', $this->records()[1]['request']['uri']);
    }

    public function testFilesOlderThanTwoWeeksAreRemovedWhenANewDayStarts()
    {
        mkdir($this->dir, 0700, true);
        $old = $this->dir . '/2026-01-01.jsonl';
        $recent = $this->dir . '/' . date('Y-m-d', strtotime('-3 days')) . '.jsonl';
        touch($old, time() - (ActivityPubLog::KEEP_DAYS + 1) * 86400);
        touch($recent, strtotime('-3 days'));

        $this->log()->served(null, Request::create('/?Absente'), 404);

        $this->assertFileDoesNotExist($old);
        $this->assertFileExists($recent);
    }
}
