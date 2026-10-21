<?php

namespace YesWiki\Test\Federation;

use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use YesWiki\Federation\Service\ActivityPubLog;

require_once 'tests/src/Federation/RecordingJournal.php';

/** ActivityPub exchanges reach the Journal: failures always, the rest and the activities only while `activitypub_log` is on. */
class ActivityPubLogTest extends TestCase
{
    private RecordingJournal $journal;

    protected function setUp(): void
    {
        $this->journal = new RecordingJournal();
    }

    private function log(bool $enabled = true): ActivityPubLog
    {
        return new ActivityPubLog(new ParameterBag(['activitypub_log' => $enabled]), $this->journal);
    }

    private function inboxRequest(string $body): Request
    {
        return Request::create('/actors/2/inbox', 'POST', [], [], [], [
            'HTTP_SIGNATURE' => 'keyId="https://mobilizon.example/@alice#main-key",algorithm="rsa-sha256",headers="(request-target) host date digest",signature="abc"',
            'HTTP_USER_AGENT' => 'Mobilizon 5.2.5',
            'HTTP_ACCEPT' => 'application/activity+json',
        ], $body);
    }

    public function testWhileTheLogIsOffOnlyFailuresAreJournaled(): void
    {
        $log = $this->log(false);
        $log->received('2', $this->inboxRequest('{"type":"Create"}'), 202, 'https://mobilizon.example/@alice');
        $log->served('2', Request::create('/actors/2'), 200);
        $log->delivered('2', 'https://social.example/users/bob', 'https://social.example/users/bob/inbox', ['type' => 'Accept'], 202);
        $log->received('2', $this->inboxRequest('{"type":"Create"}'), 401, null, 'No signature');

        $this->assertCount(1, $this->journal->records);
        $this->assertSame(LogLevel::WARNING, $this->journal->records[0]['level']);
        $this->assertSame('activitypub.received', $this->journal->records[0]['context']['action']);
    }

    public function testAFailureWhileTheLogIsOffCarriesNoActivity(): void
    {
        $this->log(false)->received('2', $this->inboxRequest('{"type":"Create","content":"un message privé"}'), 403, 'https://mobilizon.example/@alice', 'Refused');

        $this->assertArrayNotHasKey('activity', $this->journal->records[0]['context']);
    }

    public function testAReceivedActivityNamesItsSignerTheAnswerAndTheForm(): void
    {
        $activity = ['id' => 'https://mobilizon.example/activities/1', 'type' => 'Create', 'actor' => 'https://mobilizon.example/@alice', 'object' => ['type' => 'Event', 'id' => 'https://mobilizon.example/events/1']];

        $this->log()->received('2', $this->inboxRequest((string)json_encode($activity)), 403, 'https://mobilizon.example/@alice', 'This entry belongs to another actor');

        $record = $this->journal->records[0];
        $this->assertSame('Create from https://mobilizon.example/@alice answered 403: This entry belongs to another actor', $record['message']);
        $this->assertSame('actors/2', $record['context']['target']);
        $this->assertSame('https://mobilizon.example/activities/1', $record['context']['id']);
        $this->assertSame('https://mobilizon.example/@alice#main-key', $record['context']['request']['keyId']);
        $this->assertSame('Mobilizon 5.2.5', $record['context']['request']['userAgent']);
        $this->assertSame($activity, $record['context']['activity']);
    }

    public function testABodyThatIsNotJsonIsKeptAsTextAndCut(): void
    {
        $this->log()->received('2', $this->inboxRequest(str_repeat('x', ActivityPubLog::MAX_BODY_BYTES + 10)), 401, null, 'No signature');

        $context = $this->journal->records[0]['context'];
        $this->assertArrayNotHasKey('peer', $context);
        $this->assertSame(ActivityPubLog::MAX_BODY_BYTES + strlen('…'), strlen($context['activity']));
    }

    public function testEachKindOfExchangeHasItsOwnAction(): void
    {
        $log = $this->log();
        $log->delivered('2', 'https://social.example/users/bob', 'https://social.example/users/bob/inbox', ['type' => 'Accept'], 202);
        $log->delivered('2', 'https://down.example/users/a', null, ['type' => 'Create'], null, 'HTTP 500');
        $log->served('2', Request::create('/actors/2/outbox'), 200);
        $log->syncFailed('2', 'https://mobilizon.example/@group', ['type' => 'Event', 'id' => 'x'], 'champs requis : Titre');

        $this->assertSame(
            ['activitypub.delivered', 'activitypub.delivery_failed', 'activitypub.served', 'activitypub.sync_failed'],
            array_map(fn ($record) => $record['context']['action'], $this->journal->records)
        );
        $this->assertSame([LogLevel::DEBUG, LogLevel::WARNING, LogLevel::DEBUG, LogLevel::WARNING], array_column($this->journal->records, 'level'));
        $this->assertSame('/actors/2/outbox', $this->journal->records[2]['context']['request']['uri']);
    }

    public function testAnEntryAskedForWithoutAFormHasNoTarget(): void
    {
        $this->log()->served(null, Request::create('/?Absente'), 404);

        $this->assertSame('', $this->journal->records[0]['context']['target']);
    }
}
