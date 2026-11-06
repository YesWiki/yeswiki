<?php

namespace YesWiki\Test\Admin;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Container\ContainerInterface;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use YesWiki\Admin\Api\JobsApiController;
use YesWiki\Admin\Controller\JobsController;
use YesWiki\Admin\Message\CreateArchive;
use YesWiki\Admin\Service\JobsOverview;
use YesWiki\Identity\Service\AclService;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Identity\Service\RequestGuard;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Kernel\Api\QueueApiController;
use YesWiki\Kernel\Health\HealthCheck;
use YesWiki\Kernel\Health\ProvidesHealthChecks;
use YesWiki\Kernel\Queue\ProvidesSchedule;
use YesWiki\Kernel\Queue\QueueConsumerMode;
use YesWiki\Kernel\Queue\QueuePassOrigin;
use YesWiki\Kernel\Queue\QueuePassReport;
use YesWiki\Kernel\Queue\RecurringMessageTimezoned;
use YesWiki\Kernel\Service\ConfigurationService;
use YesWiki\Kernel\Service\CurrentRequest;
use YesWiki\Kernel\Service\HealthService;
use YesWiki\Kernel\Service\Journal;
use YesWiki\Kernel\Service\JournalSchema;
use YesWiki\Kernel\Service\LanguageService;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Kernel\Service\Queue\QueueConsumer;
use YesWiki\Kernel\Service\Queue\QueueHealthChecks;
use YesWiki\Kernel\Service\Queue\QueuePassLimitsFactory;
use YesWiki\Kernel\Service\Queue\QueueRecorder;
use YesWiki\Kernel\Service\Queue\QueueScheduleTicker;
use YesWiki\Kernel\Service\Queue\QueueSettings;
use YesWiki\Kernel\Service\Queue\QueueStatistics;
use YesWiki\Kernel\Service\Queue\QueueTransport;
use YesWiki\Kernel\Service\RouteProvider;
use YesWiki\Render\Service\TemplateEngine;
use YesWiki\Test\Core\YesWikiTestCase;
use YesWiki\Test\Kernel\QueueTestKit;
use YesWiki\Test\Kernel\QueueTestMessage;
use YesWiki\Test\Kernel\QueueTestTask;
use YesWiki\Test\Kernel\QueueTestToleratedMessage;

require_once 'tests/YesWikiTestCase.php';
require_once 'tests/src/Kernel/QueueTestKit.php';
require_once 'tests/src/Kernel/QueueTestToleratedMessage.php';
require_once 'tests/src/Kernel/QueueTestTask.php';

/**
 * `/admin/jobs`: the admin sees what waits, what failed and whether anything consumes, and acts on it (messenger / scheduler, spec §11.3).
 *
 * The queue is a throw-away one (QueueTestKit); the rest -- the admin, the session token, the
 * templates -- is the dev wiki's.
 */
#[CoversClass(JobsController::class)]
#[CoversClass(JobsApiController::class)]
#[CoversClass(JobsOverview::class)]
class JobsControllerTest extends YesWikiTestCase
{
    private const POST_ACTIONS = ['consume', 'retry', 'ignore', 'retryType', 'ignoreType', 'delete', 'runTask', 'secret'];

    private QueueTestKit $kit;
    private QueueSettings $settings;
    private ?ConfigurationService $configuration = null;

    protected function setUp(): void
    {
        $this->kit = new QueueTestKit();
        putenv(QueueSettings::ENV_MODE);
        putenv(QueueSettings::ENV_BINARY);
        $this->settings = new QueueSettings(new ParameterBag(['queue_consumer' => '', 'queue_consume_secret' => '']), static fn (): array => []);
        $GLOBALS['yeswikiServices'] = $this->getWiki()->services;
    }

    protected function tearDown(): void
    {
        $this->getWiki()->services->get(AuthenticationService::class)->logout();
        putenv(QueueSettings::ENV_BINARY);
        $this->kit->dispose();
    }

    /** The routes are the ACL's first line; every action also guards itself (the provider tests below), because the ACL does not see a POST's token. */
    public function testEveryJobsRouteIsForAdminsOnly(): void
    {
        $paths = [];
        foreach ($this->getWiki()->services->get(RouteProvider::class)->get() as $route) {
            if (str_starts_with($route->getPath(), '/admin/jobs') || str_starts_with($route->getPath(), '/api/admin/jobs')) {
                $this->assertContains('@admins', (array)$route->getOption('acl'), $route->getPath());
                $paths[] = $route->getPath();
            }
        }
        sort($paths);
        $this->assertSame([
            '/admin/jobs',
            '/api/admin/jobs/consume',
            '/api/admin/jobs/failed/of/{type}/ignore',
            '/api/admin/jobs/failed/of/{type}/retry',
            '/api/admin/jobs/failed/{id}/delete',
            '/api/admin/jobs/failed/{id}/ignore',
            '/api/admin/jobs/failed/{id}/retry',
            '/api/admin/jobs/secret',
            '/api/admin/jobs/tab/{tab}',
            '/api/admin/jobs/tasks/{taskId}/run',
        ], $paths, 'every route is discovered, and each is for admins only');
    }

    public function testThePageAndItsTabsAreRefusedToSomeoneWhoIsNotAnAdmin(): void
    {
        $refused = [];
        foreach ([
            'the page' => fn () => $this->page()->jobs(),
            'a tab' => fn () => $this->api()->tab('failed'),
        ] as $what => $call) {
            try {
                $call();
            } catch (AccessDeniedHttpException) {
                $refused[] = $what;
            }
        }
        $this->assertSame(['the page', 'a tab'], $refused);
    }

    /** @return array<string, array{string}> */
    public static function postActions(): array
    {
        return array_combine(self::POST_ACTIONS, array_map(static fn (string $action): array => [$action], self::POST_ACTIONS));
    }

    /** Someone who is not an admin does nothing through any of the eight POST actions. */
    #[DataProvider('postActions')]
    public function testNoPostActionIsOpenToSomeoneWhoIsNotAnAdmin(string $action): void
    {
        $this->kit->send('a');
        $failed = $this->failedTolerated('inbox');

        try {
            $this->callPostAction($this->api(post: true), $action, $failed);
            $this->fail("{$action} answered someone who is not an admin");
        } catch (AccessDeniedHttpException) {
        }

        $this->assertSame([], $this->kit->handled);
        $this->assertSame('failed', $this->kit->statistics->messageState($failed));
        $this->assertSame('', $this->secretKept(), 'no secret was written');
    }

    /** An admin whose request does not carry the session's token (a forged cross-site POST) does nothing either. */
    #[DataProvider('postActions')]
    public function testNoPostActionRunsWithoutTheSessionToken(string $action): void
    {
        $this->signInAsAdmin();
        $this->kitWithAHalfHourlyTask();
        $this->kit->send('a');
        $failed = $this->failedTolerated('inbox');
        $waiting = count($this->kit->waiting());

        $response = $this->callPostAction($this->api(post: true, token: false), $action, $failed);

        $this->assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode(), $action);
        $this->assertSame('', $response->headers->get('HX-Trigger') ?? '', 'nothing changed: nothing to reload');
        $this->assertSame([], $this->kit->handled, 'nothing was consumed');
        $this->assertSame('failed', $this->kit->statistics->messageState($failed), 'the failed message is still there, still failed');
        $this->assertCount($waiting, $this->kit->waiting(), 'no task was queued, no message requeued');
        $this->assertSame('', $this->secretKept(), 'no secret was written');
        $this->assertSame(0, $this->auditRows(), 'nothing was audited');
    }

    /** The page as the wiki's own container builds it, over the dev wiki's queue: each service wires up and answers. */
    public function testTheContainerWiresTheJobsServices(): void
    {
        $this->signInAsAdmin();
        $services = $this->getWiki()->services;

        $this->assertInstanceOf(JobsOverview::class, $services->get(JobsOverview::class));
        $this->assertInstanceOf(QueueApiController::class, $services->get(QueueApiController::class));
        foreach ([
            'the page' => $services->get(JobsController::class)->jobs(),
            'a tab' => $services->get(JobsApiController::class)->tab('failed'),
        ] as $what => $response) {
            $this->assertSame(Response::HTTP_OK, $response->getStatusCode(), $what);
            $this->assertNotSame('', (string)$response->getContent(), $what);
        }
    }

    public function testAWorkerUnderTheBinaryIsSaidToBeSupervisedByIt(): void
    {
        $this->signInAsAdmin();
        putenv(QueueSettings::ENV_MODE . '=worker');

        $body = (string)$this->page()->jobs()->getContent();
        $this->assertStringContainsString(htmlspecialchars(_t('QUEUE_PACE_WORKER'), ENT_QUOTES), $body);

        putenv(QueueSettings::ENV_BINARY . '=1');
        $body = (string)$this->page()->jobs()->getContent();
        $this->assertStringContainsString(_t('QUEUE_MODE_WORKER'), $body);
        $this->assertStringContainsString(htmlspecialchars(_t('QUEUE_PACE_WORKER_SUPERVISED'), ENT_QUOTES), $body, 'there is no service to write: the binary keeps it up');
    }

    public function testThePageShowsTheModeAndItsOrigin(): void
    {
        $this->signInAsAdmin();
        putenv(QueueSettings::ENV_MODE . '=api');

        $body = (string)$this->page()->jobs()->getContent();

        $this->assertStringContainsString(_t('QUEUE_MODE_API'), $body);
        $this->assertStringContainsString(
            htmlspecialchars(_t('ADMIN_JOBS_ORIGIN_SET', ['origin' => _t('QUEUE_ORIGIN_ENV')]), ENT_QUOTES),
            $body,
            'the admin is told where the mode was set, so they know where to change it'
        );
        $this->assertStringContainsString(htmlspecialchars(_t('QUEUE_PACE_API'), ENT_QUOTES), $body);
        $this->assertStringContainsString('id="yw-jobs-tabs"', $body);
        $this->assertStringNotContainsString('Budget', $body, 'a pass has no time limit to show');
        $this->assertDoesNotMatchRegularExpression('/\b(ADMIN_JOBS|QUEUE_(MODE|PACE|STATUS|STOP|ORIGIN))_[A-Z_]+\b/', $body, 'every label is translated');
    }

    public function testAFailedMessageCanBeRetried(): void
    {
        $this->signInAsAdmin();
        $id = $this->failed('a', 'RuntimeException: the remote answered 500');
        $this->assertStringContainsString(_t('QUEUE_HEALTH_FAILED'), $this->statusOf($this->page()->jobs()), 'a failed message raises the failures alert on the page');

        $response = $this->api(post: true)->retry((string)$id);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertSame('pending', $this->kit->statistics->messageState($id));
        $this->assertSame('ywJobsChanged', $response->headers->get('HX-Trigger'), 'the open tab reloads');
        $this->assertStringContainsString(htmlspecialchars(_t('ADMIN_JOBS_RETRIED', ['count' => 1]), ENT_QUOTES), (string)$response->getContent());
        $this->assertStringNotContainsString(_t('QUEUE_HEALTH_FAILED'), (string)$response->getContent(), 'the alert is gone from what the page swaps in');
    }

    /** A backup in `failed` follows nothing any more: the page offers no button to send it again, and the route refuses to (NotReplayableByHand). */
    public function testABackupIsNeverRetriedFromThePage(): void
    {
        $this->signInAsAdmin();
        $sent = $this->kit->transport->send(new Envelope(new CreateArchive('uid', true, true, [], [], null)))->last(TransportMessageIdStamp::class);
        $this->assertNotNull($sent);
        $id = (int)$sent->getId();
        $this->kit->transport->moveToFailed($id, 'RuntimeException: disk exploded');

        $this->assertStringNotContainsString("failed/{$id}/retry", (string)$this->api()->tab('failed')->getContent());
        $response = $this->api(post: true)->retry((string)$id);

        $this->assertStringContainsString(htmlspecialchars(_t('ADMIN_JOBS_NOT_REPLAYABLE'), ENT_QUOTES), (string)$response->getContent());
        $this->assertSame('failed', $this->kit->statistics->messageState($id));
    }

    public function testRetryAllLeavesTheKilledOnesToTheirOwnAlert(): void
    {
        $this->signInAsAdmin();
        $failed = $this->failed('a', 'RuntimeException: boom');
        $killed = $this->killed($this->kit->send('b'));

        $this->api(post: true)->retry('all');

        $this->assertSame('pending', $this->kit->statistics->messageState($failed));
        $this->assertSame('killed', $this->kit->statistics->messageState($killed), 'retried in the mode that killed it, it would only be killed again');
    }

    /** Routine failures newer than the ones the alert counts must not fill the batch: the alert's own buttons would then act on nothing and never clear it. */
    public function testRetryAllReachesTheFailuresTheAlertCountsBehindRoutineOnes(): void
    {
        $this->signInAsAdmin();
        $counted = [$this->failed('a', 'RuntimeException: boom'), $this->failed('b', 'RuntimeException: boom'), $this->failed('c', 'RuntimeException: boom')];
        for ($i = 0; $i < JobsOverview::BULK_LIMIT + 3; $i++) {
            $this->failedTolerated("inbox {$i}");
        }

        $this->api(post: true)->retry('all');

        foreach ($counted as $id) {
            $this->assertSame('pending', $this->kit->statistics->messageState($id));
        }
    }

    public function testIgnoreLeavesATraceDeleteDoesNot(): void
    {
        $this->signInAsAdmin();
        $ignored = $this->failed('a', 'RuntimeException: boom');
        $deleted = $this->failed('b', 'RuntimeException: bang');

        $this->api(post: true)->ignore((string)$ignored);
        $this->api(post: true)->delete((string)$deleted);

        $this->assertNull($this->kit->statistics->failedMessage($ignored));
        $this->assertNull($this->kit->statistics->failedMessage($deleted));
        $this->assertSame(0, $this->kit->statistics->failedCount());
        $traces = array_filter($this->kit->statistics->history(10), static fn (array $row): bool => in_array($row['messageId'], [$ignored, $deleted], true));
        $this->assertCount(1, $traces, 'only the ignored message left a row');
        $trace = array_values($traces)[0];
        $this->assertSame($ignored, $trace['messageId']);
        $this->assertSame(QueueRecorder::IGNORED, $trace['status']);
        $this->assertSame('RuntimeException: boom', $trace['error'], 'the trace keeps what it failed with');

        $again = $this->failed('a', 'RuntimeException: boom');
        $this->assertNotNull($this->queueFailedCheck()->failure(), 'ignoring mutes nothing: the next failure alerts again');
        $this->assertNotNull($this->kit->statistics->failedMessage($again));
    }

    public function testEveryFailedMessageOfOneTypeCanBeRetriedEvenWhenTheTypeIsTolerated(): void
    {
        $this->signInAsAdmin();
        $dead = [$this->failedTolerated('inbox one'), $this->failedTolerated('inbox two')];
        $killed = $this->killed($this->tolerated('killed one'));
        $other = $this->failed('a', 'RuntimeException: boom');

        $this->api(post: true)->retry('all');
        $this->assertSame('failed', $this->kit->statistics->messageState($dead[0]), '"all" leaves a tolerated type alone');

        $response = $this->api(post: true)->retryType('QueueTestToleratedMessage');

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        foreach ($dead as $id) {
            $this->assertSame('pending', $this->kit->statistics->messageState($id));
        }
        $this->assertSame('killed', $this->kit->statistics->messageState($killed), 'a killed one is the killed alert\'s');
        $this->assertSame('pending', $this->kit->statistics->messageState($other), 'the earlier "all" took this one');
        $this->assertSame('ywJobsChanged', $response->headers->get('HX-Trigger'));
        $this->assertStringContainsString(htmlspecialchars(_t('ADMIN_JOBS_RETRIED', ['count' => 2]), ENT_QUOTES), (string)$response->getContent());
    }

    public function testEveryFailedMessageOfOneTypeCanBeIgnoredAndTheOthersStay(): void
    {
        $this->signInAsAdmin();
        $dead = [$this->failedTolerated('inbox one'), $this->failedTolerated('inbox two')];
        $other = $this->failed('a', 'RuntimeException: boom');

        $response = $this->api(post: true)->ignoreType('QueueTestToleratedMessage');

        foreach ($dead as $id) {
            $this->assertNull($this->kit->statistics->failedMessage($id));
        }
        $this->assertNotNull($this->kit->statistics->failedMessage($other), 'another type is not touched');
        $ignored = array_filter($this->kit->statistics->history(10), static fn (array $row): bool => $row['status'] === QueueRecorder::IGNORED);
        $this->assertCount(2, $ignored, 'each one is kept in the history as ignored');
        $this->assertSame(QueueTestToleratedMessage::class, array_values($ignored)[0]['type']);
        $this->assertStringContainsString(htmlspecialchars(_t('ADMIN_JOBS_IGNORED', ['count' => 2]), ENT_QUOTES), (string)$response->getContent());
    }

    public function testAnActionOnATypeWithNothingFailedSaysSo(): void
    {
        $this->signInAsAdmin();
        $other = $this->failed('a', 'RuntimeException: boom');

        foreach (['QueueTestToleratedMessage', 'NoSuchMessage'] as $type) {
            $this->assertStringContainsString(htmlspecialchars(_t('ADMIN_JOBS_GONE'), ENT_QUOTES), (string)$this->api(post: true)->retryType($type)->getContent());
            $this->assertStringContainsString(htmlspecialchars(_t('ADMIN_JOBS_GONE'), ENT_QUOTES), (string)$this->api(post: true)->ignoreType($type)->getContent());
        }
        $this->assertSame('failed', $this->kit->statistics->messageState($other));
    }

    public function testAnActionOnATypeActsOnAtMostTheBulkLimit(): void
    {
        $this->signInAsAdmin();
        for ($i = 0; $i < JobsOverview::BULK_LIMIT + 3; $i++) {
            $this->failedTolerated("inbox {$i}");
        }

        $this->api(post: true)->ignoreType('QueueTestToleratedMessage');

        $this->assertSame(3, $this->kit->statistics->failedCount(), 'what is left takes another click');
    }

    public function testACheckThatOffersTheFailedMessagesOfATypeGetsTheButtons(): void
    {
        $this->signInAsAdmin();
        $check = HealthCheck::named('module-delivery-failed')
            ->degraded()
            ->linkedTo('admin/jobs')
            ->offers(QueueHealthChecks::offeringFailedOf(QueueTestToleratedMessage::class), 'something-the-page-does-not-know')
            ->runs(static fn (): string => 'some deliveries failed');
        $plain = HealthCheck::named('module-other')->linkedTo('admin/jobs')->runs(static fn (): string => 'wrong');
        $health = $this->healthOf($check, $plain);

        $body = (string)$this->page($health)->jobs()->getContent();

        $alerts = preg_split('/(?=<li class="yw-jobs__alert )/', $body) ?: [];
        $offering = current(array_filter($alerts, static fn (string $alert): bool => str_contains($alert, 'data-check="module-delivery-failed"')));
        $this->assertIsString($offering);
        $this->assertStringContainsString('api/admin/jobs/failed/of/QueueTestToleratedMessage/retry', $offering);
        $this->assertStringContainsString('api/admin/jobs/failed/of/QueueTestToleratedMessage/ignore', $offering);
        $this->assertStringNotContainsString('failed/all/', $offering, 'the generic "all" buttons are not the ones offered');
        $other = current(array_filter($alerts, static fn (string $alert): bool => str_contains($alert, 'data-check="module-other"')));
        $this->assertIsString($other);
        $this->assertStringNotContainsString('failed/of/', $other);
    }

    public function testConsumeNowRunsAPass(): void
    {
        $this->signInAsAdmin();
        $this->kit->send('a');
        $this->kit->send('b');

        $response = $this->api(post: true)->consume();

        $this->assertSame(['a', 'b'], $this->kit->handled);
        $pass = $this->kit->statistics->lastPass();
        $this->assertNotNull($pass);
        $this->assertSame('request', $pass['mode'], 'the mode of the wiki, which ends by itself');
        $this->assertFalse($pass['beforeResponse'], 'an admin who asked for the pass is not a visitor kept waiting');
        $this->assertStringContainsString('id="yw-jobs-status"', (string)$response->getContent(), 'the alerts come back refreshed');
        $this->assertStringContainsString(htmlspecialchars(_t('ADMIN_JOBS_CONSUMED', [
            'handled' => 2,
            'remaining' => 0,
            'reason' => _t('QUEUE_STOP_EMPTY'),
        ]), ENT_QUOTES), (string)$response->getContent());
    }

    /** The button has no deadline, no memory ceiling and no averages rule: it stops on an empty queue, however long the messages are said to take. */
    public function testConsumeNowEmptiesTheQueueWhateverTheAverages(): void
    {
        $this->signInAsAdmin();
        $this->kitAt('2026-10-23 12:00:00');
        QueueTestKit::recordRun($this->kit->db, QueueTestMessage::class, QueueRecorder::HANDLED, 60_000, '2026-10-23 11:30:00');
        foreach (['a', 'b', 'c'] as $value) {
            $this->kit->send($value);
        }

        $response = $this->api(post: true)->consume();

        $this->assertSame(['a', 'b', 'c'], $this->kit->handled);
        $this->assertStringContainsString(htmlspecialchars(_t('ADMIN_JOBS_CONSUMED', [
            'handled' => 3,
            'remaining' => 0,
            'reason' => _t('QUEUE_STOP_EMPTY'),
        ]), ENT_QUOTES), (string)$response->getContent());
    }

    /** The button's pass may run for long: the admin's session is released before it, or every other page of that admin would wait for it to end. */
    public function testConsumeNowReleasesTheSessionBeforeThePass(): void
    {
        $sessionWhileHandling = [];
        $this->kit->dispose();
        $this->kit = new QueueTestKit([QueueTestMessage::class => static function () use (&$sessionWhileHandling): void {
            $sessionWhileHandling[] = session_status();
        }]);
        $hadSession = $this->sessionIsOpen();
        if (!$hadSession) {
            @session_start();
        }
        if (!$this->sessionIsOpen()) {
            $this->markTestSkipped('a session cannot be opened here');
        }
        // Signed in once the session is open, which would otherwise load over the login.
        $this->signInAsAdmin();
        $this->kit->send('a');

        try {
            $response = $this->api(post: true)->consume();
        } finally {
            if ($hadSession && !$this->sessionIsOpen()) {
                @session_start();
            } elseif (!$hadSession) {
                session_write_close();
            }
        }

        $this->assertSame([PHP_SESSION_NONE], $sessionWhileHandling, 'released while the message runs');
        $this->assertStringContainsString(htmlspecialchars(_t('ADMIN_JOBS_CONSUMED', [
            'handled' => 1,
            'remaining' => 0,
            'reason' => _t('QUEUE_STOP_EMPTY'),
        ]), ENT_QUOTES), (string)$response->getContent(), 'the answer is rendered all the same');
    }

    private function sessionIsOpen(): bool
    {
        return session_status() === PHP_SESSION_ACTIVE;
    }

    /** The admin's button is not the consumer the wiki waits for: pressed during a fallback, it must not end it. */
    public function testConsumeNowIsRecordedAsManual(): void
    {
        $this->signInAsAdmin();
        putenv(QueueSettings::ENV_MODE . '=api');
        $this->kit->send('a');

        $this->api(post: true)->consume();

        $this->assertSame('manual', $this->kit->statistics->lastPass()['origin'] ?? null);
    }

    /** A blocking mode has no time limit of its own: the button runs the pass a request would, once its response has left. */
    public function testABlockingModeIsConsumedAsTheEndOfARequestWould(): void
    {
        $this->signInAsAdmin();
        putenv(QueueSettings::ENV_MODE . '=worker');
        $this->kit->send('a');

        $this->api(post: true)->consume();

        $this->assertSame(['a'], $this->kit->handled);
        $this->assertSame('request', $this->kit->statistics->lastPass()['mode'] ?? null);
        $this->assertSame('manual', $this->kit->statistics->lastPass()['origin'] ?? null);
    }

    public function testGeneratingASecretStoresItAndAuditsIt(): void
    {
        $this->signInAsAdmin();

        $body = (string)$this->api(post: true)->secret()->getContent();

        if (preg_match('/\b([0-9a-f]{48})\b/', $body, $shown) !== 1) {
            $this->fail('the new secret is shown');
        }
        $this->assertSame($shown[1], $this->secretKept(), 'the secret shown is the one the configuration keeps');
        $this->assertStringContainsString('X-YesWiki-Queue-Secret: ' . $shown[1], $body, 'with the call to schedule');
        $this->assertSame(1, $this->auditRows(), 'the change of configuration is in the audit log');
        $this->assertSame(basename($this->kit->configFile()), $this->kit->db->scalar('SELECT target FROM ' . (new JournalSchema($this->kit->db))->table() . ' WHERE action = ?', null, ['config.write']));
    }

    /** A configuration that cannot be written keeps its old secret: the page must not show a secret nothing will accept, nor audit a write that did not happen. */
    public function testASecretThatCouldNotBeWrittenIsNeitherShownNorAudited(): void
    {
        $this->signInAsAdmin();
        $this->configuration = new class extends ConfigurationService {
            public function write(\YesWiki\Kernel\Entity\ConfigurationFile $config, ?string $file = null, string $arrayName = 'yeswikiConfig')
            {
                return false;
            }
        };

        $body = (string)$this->api(post: true)->secret()->getContent();

        $this->assertDoesNotMatchRegularExpression('/\b[0-9a-f]{48}\b/', $body);
        $this->assertStringContainsString(htmlspecialchars(_t('ADMIN_JOBS_SECRET_FAILED'), ENT_QUOTES), $body);
        $this->assertSame('', $this->secretKept());
        $this->assertSame(0, $this->auditRows());
    }

    public function testRawErrorTextsAreEscaped(): void
    {
        $this->signInAsAdmin();
        $this->failed('a', '<img src=x onerror=alert(1)>');
        $this->kit->recorder->messageIgnored(999, 'Some\\Message', '<script>alert(2)</script>');

        $pages = [
            (string)$this->page()->jobs()->getContent(),
            (string)$this->api()->tab('failed')->getContent(),
            (string)$this->api()->tab('history')->getContent(),
        ];
        foreach ($pages as $body) {
            $this->assertStringNotContainsString('<img src=x', $body);
            $this->assertStringNotContainsString('<script>alert(2)', $body);
        }
        $this->assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $pages[1]);
    }

    public function testEveryTabRenders(): void
    {
        $this->signInAsAdmin();
        $this->failed('a', 'RuntimeException: boom');
        $this->kit->send('b');
        $this->kit->consumer->drain(QueueConsumerMode::Api, (new QueuePassLimitsFactory(new NativeClock()))->forPass());

        foreach (JobsOverview::TABS as $tab) {
            $body = (string)$this->api()->tab($tab)->getContent();
            $this->assertStringContainsString('id="yw-jobs-tabs"', $body, $tab);
            $this->assertStringContainsString('aria-selected="true"', $body, $tab);
            $this->assertDoesNotMatchRegularExpression('/\b(ADMIN_JOBS|QUEUE_(MODE|PACE|STATUS|STOP|ORIGIN))_[A-Z_]+\b/', $body, $tab);
        }
        $this->assertStringContainsString(_t('QUEUE_STATUS_HANDLED'), (string)$this->api()->tab('history')->getContent());
        $this->assertStringContainsString('QueueTestMessage', (string)$this->api()->tab('types')->getContent(), 'a type with no label of its own shows its short class name');
    }

    public function testTheRailCountsBrokenQueueFindings(): void
    {
        $this->signInAsAdmin();
        $failing = static fn (string $id, string $route, bool $broken = true): HealthCheck => ($broken ? HealthCheck::named($id) : HealthCheck::named($id)->degraded())
            ->linkedTo($route)
            ->runs(static fn (): string => 'wrong');
        $health = $this->healthOf($failing('queue-one', 'admin/jobs'), $failing('federation-delivery', 'admin/jobs'), $failing('elsewhere', 'admin/updates'), $failing('queue-degraded', 'admin/jobs', broken: false), HealthCheck::named('queue-fine')->linkedTo('admin/jobs')->runs(static fn (): ?string => null));

        $body = (string)$this->page($health)->jobs()->getContent();

        $this->assertMatchesRegularExpression(
            '#admin/jobs"[^>]*>.*?<span class="yw-dashboard__count">2</span>#s',
            $body,
            'the rail entry counts the Broken findings that send the admin here, a module\'s included'
        );
        $this->assertMatchesRegularExpression('#admin/health"[^>]*>.*?<span class="yw-dashboard__count">3</span>#s', $body);
    }

    // --- The consume button's other answers ---------------------------------------------------

    public function testConsumeNowWhileAnotherPassHoldsTheLockSaysSo(): void
    {
        $this->signInAsAdmin();
        $this->kit->send('a');
        $this->kit->holdThePassLock();

        $body = (string)$this->api(post: true)->consume()->getContent();

        $this->assertSame([], $this->kit->handled, 'the pass in progress is not doubled');
        $this->assertStringContainsString('yw-alert--warning', $body);
        $this->assertStringContainsString(htmlspecialchars(_t('ADMIN_JOBS_CONSUME_LOCKED'), ENT_QUOTES), $body);
    }

    public function testConsumeNowOnAWikiThatIsPausedSaysSo(): void
    {
        $this->signInAsAdmin();
        $this->kit->send('a');
        $config = (new ConfigurationService())->getConfiguration($this->kit->configFile());
        $config->load();
        $config['wiki_status'] = 'hibernate';
        (new ConfigurationService())->write($config);

        $body = (string)$this->api(post: true)->consume()->getContent();

        $this->assertSame([], $this->kit->handled, 'a paused wiki handles nothing');
        $this->assertStringContainsString('yw-alert--warning', $body);
        $this->assertStringContainsString(htmlspecialchars(_t('ADMIN_JOBS_CONSUME_PAUSED'), ENT_QUOTES), $body);
    }

    public function testConsumeNowOnAQueueThatCannotBeReadSaysTheyFailed(): void
    {
        $this->signInAsAdmin();
        $this->kit->dropTables();

        $response = $this->api(post: true)->consume();

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertStringContainsString('yw-alert--danger', (string)$response->getContent());
        $this->assertStringContainsString(htmlspecialchars(_t('ADMIN_JOBS_CONSUME_ERROR'), ENT_QUOTES), (string)$response->getContent());
    }

    // --- A queue that cannot be read -----------------------------------------------------------

    /** The admin opens /admin/jobs on a wiki that was not migrated yet: the page tells it, with the alert that says what to do, instead of a 500. */
    public function testThePageSaysWhenTheQueueCannotBeRead(): void
    {
        $this->signInAsAdmin();
        $this->kit->dropTables();

        $response = $this->page()->jobs();
        $body = (string)$response->getContent();

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertStringContainsString(htmlspecialchars(rtrim(_t('ADMIN_JOBS_UNREADABLE', ['error' => '']), ': '), ENT_QUOTES), $body);
        $this->assertStringNotContainsString('class="yw-jobs__tiles"', $body, 'no tile of numbers it could not read');
        $this->assertStringContainsString('data-check="queue-tables"', $body, 'the alert that names the missing tables');
    }

    /** @return array<string, array{string}> */
    public static function everyTab(): array
    {
        return array_combine(JobsOverview::TABS, array_map(static fn (string $tab): array => [$tab], JobsOverview::TABS));
    }

    #[DataProvider('everyTab')]
    public function testATabSaysWhenTheQueueCannotBeRead(string $tab): void
    {
        $this->signInAsAdmin();
        $this->kit->dropTables();

        $response = $this->api()->tab($tab);
        $body = (string)$response->getContent();

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertStringContainsString(htmlspecialchars(rtrim(_t('ADMIN_JOBS_UNREADABLE', ['error' => '']), ': '), ENT_QUOTES), $body);
        $this->assertStringNotContainsString('<tbody', $body, 'no row');
    }

    // --- What the top of the page computes ----------------------------------------------------

    public function testTheTilesCountWhatTheQueueHoldsAndWhatItDid(): void
    {
        $clock = $this->kitAt('2026-10-23 12:00:00');
        $overview = $this->overview();
        $empty = $overview->status()['tiles'];
        $this->assertSame(0, $empty['pending']);
        $this->assertNull($empty['oldest'], 'nothing waits, so nothing is old');
        $this->assertNull($empty['rate'], 'no run, no rate: not 0 %, not a division by zero');

        $this->kit->send('a');
        $this->kit->send('b');
        $failed = $this->failed('c', 'RuntimeException: boom');
        $clock->sleep(3 * 3600);
        foreach (['handled', 'handled', 'handled', 'failed'] as $i => $status) {
            QueueTestKit::recordRun($this->kit->db, 'Some\\Message', $status, 200, '2026-10-23 12:30:00', 100 + $i);
        }

        $tiles = $overview->status()['tiles'];

        $this->assertSame(2, $tiles['pending']);
        $this->assertMatchesRegularExpression('/\b3\b/', (string)$tiles['oldest'], 'the oldest waits since three hours');
        $this->assertSame(1, $tiles['failed'], 'the failed table\'s own count, whatever the history says');
        $this->assertSame(3, $tiles['handled']);
        $this->assertSame(number_format(75, 1, _t('ADMIN_JOBS_DECIMAL_SEPARATOR'), ''), $tiles['rate'], '3 handled out of 4 ended');
        $this->assertNotNull($tiles['duration']);
        $this->assertNotNull($this->kit->statistics->failedMessage($failed));
    }

    public function testThePendingTileTurnsRedOnAStalledQueueAndOrangeOnABacklog(): void
    {
        $level = fn (HealthCheck ...$checks): string => $this->overview($this->healthOf(...$checks))->status()['tiles']['pendingLevel'];
        $check = static fn (string $id): HealthCheck => HealthCheck::named($id)->linkedTo('admin/jobs')->runs(static fn (): string => 'wrong');

        $this->assertSame('', $level());
        $this->assertSame('', $level($check('queue-failed')), 'another alert says nothing of what waits');
        $this->assertSame('warning', $level($check('queue-backlog')));
        $this->assertSame('danger', $level($check('queue-stalled')));
        $this->assertSame('danger', $level($check('queue-backlog'), $check('queue-stalled')), 'the worse one wins');
    }

    public function testTheBannerSaysWhetherTheConsumerIsLate(): void
    {
        $clock = $this->kitAt('2026-10-23 12:00:00');
        putenv(QueueSettings::ENV_MODE . '=worker');
        QueueTestKit::recordPass($this->kit->db, QueueConsumerMode::Worker, '2026-10-23 11:59:00', '2026-10-23 12:00:00', QueuePassReport::EMPTY);
        $clock->sleep(60);

        $fresh = $this->overview()->status()['mode'];

        $this->assertFalse($fresh['late'], 'seen a minute ago, a worker is not late');
        $this->assertFalse($fresh['running']);
        $this->assertNotNull($fresh['lastPass']);

        $clock->sleep(3600);

        $this->assertTrue($this->overview()->status()['mode']['late'], 'not seen for an hour, a worker is');
    }

    public function testTheBannerSaysAPassIsRunningWhileItHasNotEnded(): void
    {
        $clock = $this->kitAt('2026-10-23 12:00:00');
        $this->assertFalse($this->overview()->status()['mode']['running'], 'no pass at all');
        QueueTestKit::recordPass($this->kit->db, QueueConsumerMode::Request, '2026-10-23 12:00:00', '2026-10-23 12:00:00', '');
        $clock->sleep(5);

        $this->assertTrue($this->overview()->status()['mode']['running']);
    }

    public function testTheBannerTellsWhetherAnApiWikiHasASecret(): void
    {
        putenv(QueueSettings::ENV_MODE . '=api');

        $without = $this->overview()->status()['mode'];
        $this->settings = new QueueSettings(new ParameterBag(['queue_consumer' => '', 'queue_consume_secret' => 'a-secret']), static fn (): array => []);
        $with = $this->overview()->status()['mode'];

        $this->assertTrue($without['api']);
        $this->assertFalse($without['hasSecret']);
        $this->assertTrue($with['hasSecret']);
        $this->assertFalse($with['visitorsWait'], 'no visitor consumes in this mode');
    }

    /** @return array<string, array{string, string, string, list<string>}> check id, mode, secret, the buttons */
    public static function alertButtons(): array
    {
        return [
            'a stalled queue' => ['queue-stalled', 'request', '', ['consume', 'docs']],
            'a backlog' => ['queue-backlog', 'request', '', ['consume', 'docs']],
            'php limit' => ['queue-php-limit', 'request', '', ['consume', 'docs']],
            'failures' => ['queue-failed', 'request', '', ['retry-all', 'view-failed', 'ignore-all']],
            'killed messages' => ['queue-killed', 'request', '', ['view-failed', 'consume', 'docs']],
            'an overdue task' => ['queue-task-overdue', 'request', '', ['consume', 'view-tasks']],
            'a failing task' => ['queue-task-failing', 'request', '', ['view-tasks', 'view-history']],
            'the api mode with no secret' => ['queue-config', 'api', '', ['secret', 'docs']],
            'the api mode with a secret' => ['queue-config', 'api', 'a-secret', ['docs']],
            'a mode that needs no secret' => ['queue-config', 'worker', '', ['docs']],
            'missing tables' => ['queue-tables', 'request', '', ['docs']],
            'a check nobody here knows' => ['module-something', 'request', '', ['view-failed', 'docs']],
        ];
    }

    /** @param list<string> $buttons */
    #[DataProvider('alertButtons')]
    public function testEachAlertOffersTheButtonsThatAnswerIt(string $id, string $mode, string $secret, array $buttons): void
    {
        putenv(QueueSettings::ENV_MODE . '=' . $mode);
        $this->settings = new QueueSettings(new ParameterBag(['queue_consumer' => '', 'queue_consume_secret' => $secret]), static fn (): array => []);
        $health = $this->healthOf(HealthCheck::named($id)->linkedTo('admin/jobs')->runs(static fn (): string => 'wrong'));

        $alerts = $this->overview($health)->status()['alerts'];

        $this->assertSame([$id], array_column($alerts, 'id'));
        $this->assertSame($buttons, $alerts[0]['actions']);
    }

    // --- Actions on messages that are not what the admin saw ----------------------------------

    public function testIgnoreAllActsOnlyOnWhatTheAlertCounts(): void
    {
        $this->signInAsAdmin();
        $counted = [$this->failed('a', 'RuntimeException: boom'), $this->failed('b', 'RuntimeException: bang')];
        $killed = $this->killed($this->kit->send('c'));
        $tolerated = $this->failedTolerated('inbox');

        $response = $this->api(post: true)->ignore('all');

        foreach ($counted as $id) {
            $this->assertNull($this->kit->statistics->failedMessage($id));
        }
        $this->assertSame('killed', $this->kit->statistics->messageState($killed), 'the killed alert\'s');
        $this->assertSame('failed', $this->kit->statistics->messageState($tolerated), 'a tolerated type is not what the alert counts');
        $ignored = array_filter($this->kit->statistics->history(10), static fn (array $row): bool => $row['status'] === QueueRecorder::IGNORED);
        $this->assertEqualsCanonicalizing($counted, array_column($ignored, 'messageId'), 'each one stays in the history as ignored');
        $this->assertStringContainsString(htmlspecialchars(_t('ADMIN_JOBS_IGNORED', ['count' => 2]), ENT_QUOTES), (string)$response->getContent());
    }

    /** @return array<string, array{string}> */
    public static function actionsOnOneMessage(): array
    {
        return ['retry' => ['retry'], 'ignore' => ['ignore'], 'delete' => ['delete']];
    }

    /** A message that is waiting, or that someone else already dealt with, is not failed: the answer says so and nothing happens to it. */
    #[DataProvider('actionsOnOneMessage')]
    public function testAnActionOnAMessageThatIsNotFailedSaysSo(string $action): void
    {
        $this->signInAsAdmin();
        $waiting = $this->kit->send('x');

        foreach ([$waiting, 999999] as $id) {
            $body = (string)$this->api(post: true)->{$action}((string)$id)->getContent();
            $this->assertStringContainsString(htmlspecialchars(_t('ADMIN_JOBS_GONE'), ENT_QUOTES), $body, "{$action} {$id}");
        }
        $this->assertSame('pending', $this->kit->statistics->messageState($waiting));
        $this->assertSame(0, $this->kit->statistics->failedCount());
    }

    public function testDeleteTellsItDeletedAndKeepsNoTrace(): void
    {
        $this->signInAsAdmin();
        $id = $this->failed('a', 'RuntimeException: boom');

        $response = $this->api(post: true)->delete((string)$id);

        $this->assertStringContainsString(htmlspecialchars(_t('ADMIN_JOBS_DELETED'), ENT_QUOTES), (string)$response->getContent());
        $this->assertSame('ywJobsChanged', $response->headers->get('HX-Trigger'));
        $this->assertNull($this->kit->statistics->failedMessage($id));
    }

    public function testRunningATaskThatIsNotScheduledQueuesNothing(): void
    {
        $this->signInAsAdmin();
        $this->kitWithAHalfHourlyTask();
        $waiting = count($this->kit->waiting());

        $body = (string)$this->api(post: true)->runTask('nope')->getContent();

        $this->assertStringContainsString(htmlspecialchars(_t('ADMIN_JOBS_TASK_UNKNOWN'), ENT_QUOTES), $body);
        $this->assertCount($waiting, $this->kit->waiting());
    }

    // --- Helpers -----------------------------------------------------------------------------

    /** Replaces the kit by one whose clock stands at that time; answers the clock. */
    private function kitAt(string $time): MockClock
    {
        $this->kit->dispose();
        $clock = new MockClock($time, 'UTC');
        $this->kit = new QueueTestKit(clock: $clock);

        return $clock;
    }

    private function overview(?HealthService $health = null): JobsOverview
    {
        return $this->services(Request::create('/?admin/jobs'), $health)->get(JobsOverview::class);
    }

    /** The health the page reads when the only checks are these ones. */
    private function healthOf(HealthCheck ...$checks): HealthService
    {
        return new HealthService([new class(array_values($checks)) implements ProvidesHealthChecks {
            /** @param list<HealthCheck> $checks */
            public function __construct(private array $checks)
            {
            }

            public function healthChecks(): array
            {
                return $this->checks;
            }
        }]);
    }

    /** One of the eight POST actions, on that failed message, its type or the kit's task. */
    private function callPostAction(JobsApiController $api, string $action, int $failed): Response
    {
        return match ($action) {
            'consume', 'secret' => $api->{$action}(),
            'retryType', 'ignoreType' => $api->{$action}('QueueTestToleratedMessage'),
            'runTask' => $api->runTask('test-task'),
            default => $api->{$action}((string)$failed),
        };
    }

    /** The consume secret the kit's configuration file keeps; '' when none. */
    private function secretKept(): string
    {
        $config = (new ConfigurationService())->getConfiguration($this->kit->configFile());
        $config->load();

        return (string)($config['queue_consume_secret'] ?? '');
    }

    /** How many `config.write` audit entries the kit's Journal holds. */
    private function auditRows(): int
    {
        return (int)$this->kit->db->scalar('SELECT COUNT(*) FROM ' . (new JournalSchema($this->kit->db))->table() . ' WHERE action = ?', null, ['config.write']);
    }

    private function queueFailedCheck(): HealthCheck
    {
        foreach ($this->kit->healthChecks($this->settings)->healthChecks() as $check) {
            if ($check->id() === 'queue-failed') {
                return $check;
            }
        }
        $this->fail('No queue-failed check');
    }

    /** A message of the kit's queue, moved to `failed` with that cause; answers its id. */
    private function failed(string $value, string $error): int
    {
        $id = $this->kit->send($value);
        $this->kit->transport->moveToFailed($id, $error);

        return $id;
    }

    /** A tolerated-type message of the kit's queue, moved to `failed` with that cause; answers its id. */
    private function failedTolerated(string $value, string $error = 'RuntimeException: HTTP 410'): int
    {
        $id = $this->tolerated($value);
        $this->kit->transport->moveToFailed($id, $error);

        return $id;
    }

    /** A tolerated-type message waiting in the kit's queue; answers its id. */
    private function tolerated(string $value): int
    {
        $stamp = $this->kit->transport->send(new Envelope(new QueueTestToleratedMessage($value)))->last(TransportMessageIdStamp::class);
        $this->assertNotNull($stamp);

        return (int)$stamp->getId();
    }

    /** That waiting message, failed as a consumer killed in `request` leaves it; answers its id. */
    private function killed(int $id): int
    {
        QueueTestKit::failAsKilled($this->kit->transport, $id, QueueConsumerMode::Request);

        return $id;
    }

    private function signInAsAdmin(): void
    {
        $services = $this->getWiki()->services;
        $acl = $services->get(AclService::class);
        $admin = current(array_filter($services->get(UserManager::class)->getAll(), static fn ($user) => $acl->isAdmin($user['name'])));
        $this->assertNotFalse($admin, 'the jobs page needs an admin to render for');
        $services->get(AuthenticationService::class)->login($admin);
    }

    public function testAConsumerThatHasNotBeatenYetIsSeenWhenItStarted(): void
    {
        $this->signInAsAdmin();
        // A blocking consumer writes its first heartbeat a minute after it starts: until then its row was last seen when it started.
        $this->kit->recorder->passStarted(QueueConsumerMode::Worker, null, false);

        $body = (string)$this->api()->tab('consumers')->getContent();

        $this->assertMatchesRegularExpression('/' . preg_quote(htmlspecialchars(_t('ADMIN_JOBS_RUNNING_SEEN', ['ago' => '']), ENT_QUOTES), '/') . '\S/', $body, 'seen when it started, not "seen" followed by nothing');
    }

    /** The banner keeps the mode the admin chose, its last pass is that consumer's, and the red alert that says why the requests consume comes first. */
    public function testTheFallbackAlertComesFirstAndTheBannerShowsTheChosenModesLastPass(): void
    {
        $this->signInAsAdmin();
        putenv(QueueSettings::ENV_MODE . '=worker');
        $this->kit->recorder->passStarted(QueueConsumerMode::Request, null, false, QueuePassOrigin::Fallback, QueueConsumerMode::Worker);
        $this->kit->send('a');

        $status = $this->statusOf($this->page()->jobs());

        preg_match_all('/data-check="([^"]+)"/', $status, $checks);
        $this->assertSame('queue-fallback', $checks[1][0] ?? null, 'the first alert');
        $this->assertStringContainsString(_t('QUEUE_MODE_WORKER'), $status, 'the banner still shows the chosen mode');
        $this->assertStringContainsString(htmlspecialchars(_t('ADMIN_JOBS_LAST_SEEN', ['mode' => _t('QUEUE_MODE_WORKER')]), ENT_QUOTES), $status);
        $this->assertMatchesRegularExpression('/<strong class="yw-text-danger">\s*' . preg_quote(_t('ADMIN_JOBS_NEVER'), '/') . '/', $status, 'the worker was never seen');
    }

    public function testTheConsumersTabMarksFallbackPasses(): void
    {
        $this->signInAsAdmin();
        $this->kit->recorder->passStarted(QueueConsumerMode::Request, null, false, QueuePassOrigin::Fallback, QueueConsumerMode::Worker);

        $body = (string)$this->api()->tab('consumers')->getContent();

        $this->assertStringContainsString(htmlspecialchars(_t('ADMIN_JOBS_PASS_FALLBACK', ['mode' => 'worker']), ENT_QUOTES), $body);
    }

    /** The consumers tab names why each pass stopped: a reason with no label would show its translation key. */
    public function testEveryStopReasonHasALabelInEachLanguage(): void
    {
        $reasons = array_values((new \ReflectionClass(QueuePassReport::class))->getConstants());
        $this->assertContains(QueuePassReport::CONFIG_CHANGED, $reasons);

        foreach (['fr', 'en'] as $language) {
            $translations = require "src/lang/yeswiki_{$language}.php";
            foreach ([...$reasons, ''] as $reason) {
                $key = $reason === '' ? 'QUEUE_STOP_RUNNING' : 'QUEUE_STOP_' . strtoupper(str_replace('-', '_', $reason));
                $this->assertArrayHasKey($key, $translations, "the {$language} label of the stop reason '{$reason}'");
            }
            foreach (['ADMIN_JOBS_RUN_TASK_HINT', 'ADMIN_JOBS_TASK_QUEUED'] as $key) {
                $this->assertArrayHasKey($key, $translations, "the {$language} wording of the task run: {$key}");
            }
        }
    }

    /** Running a task by hand leaves its next run on its slot, and the answer says when, as the Tasks tab does. */
    public function testRunningATaskSaysWhenItIsNextDue(): void
    {
        $this->signInAsAdmin();
        $clock = $this->kitWithAHalfHourlyTask();
        $clock->sleep(10 * 60);

        $response = $this->api(post: true)->runTask('test-task');

        $next = $this->kit->ticker->task('test-task')['nextDueAt'] ?? null;
        $this->assertSame('2026-10-23 12:30:00', $next?->format('Y-m-d H:i:s'), 'the schedule, not the launch plus an interval');
        $shown = $this->nextRunShownOnTheTasksTab();
        $this->assertStringContainsString(htmlspecialchars($shown, ENT_QUOTES), (string)$response->getContent(), 'the same words as the tab for the next run');
        $this->assertStringContainsString(
            htmlspecialchars(_t('ADMIN_JOBS_TASK_QUEUED', ['task' => 'Test task test-task', 'next' => $shown]), ENT_QUOTES),
            (string)$response->getContent(),
            'in the sentence that says the task is waiting'
        );
        $this->assertMatchesRegularExpression('/\b20\b/', $shown, 'in the 20 minutes the schedule had left, not in half an hour');
    }

    /** The Tasks tab tells that running a task leaves its next run where it was. */
    public function testTheTasksTabSaysThatRunningKeepsTheSchedule(): void
    {
        $this->signInAsAdmin();
        $this->kitWithAHalfHourlyTask();

        $body = (string)$this->api()->tab('tasks')->getContent();

        $hint = htmlspecialchars(_t('ADMIN_JOBS_RUN_TASK_HINT'), ENT_QUOTES);
        $this->assertStringContainsString($hint, $body);
    }

    /** The "next run" cell of the first row of the Tasks tab, as the admin reads it. */
    private function nextRunShownOnTheTasksTab(): string
    {
        $tab = (string)$this->api()->tab('tasks')->getContent();
        if (preg_match('/<td>\s*(\S[^<]*?)\s*<\/td>\s*<td>\s*<button/', $tab, $cell) !== 1) {
            $this->fail('the Tasks tab shows no next run');
        }

        return $cell[1];
    }

    /** Replaces the kit by one whose schedule declares `test-task` every 30 minutes, seen by the Scheduler at 12:00; answers its clock. */
    private function kitWithAHalfHourlyTask(): MockClock
    {
        $this->kit->dispose();
        $clock = new MockClock('2026-10-23 12:00:00', 'UTC');
        $this->kit = new QueueTestKit(clock: $clock, schedule: new class implements ProvidesSchedule {
            public function recurringMessages(): array
            {
                return [RecurringMessageTimezoned::every('30 minutes', new QueueTestTask('test-task'))];
            }
        });
        $this->kit->ticker->tick();

        return $clock;
    }

    private function page(?HealthService $health = null): JobsController
    {
        $controller = new JobsController();
        $controller->setServices($this->services(Request::create('/?admin/jobs'), $health));

        return $controller;
    }

    private function api(bool $post = false, bool $token = true): JobsApiController
    {
        $request = Request::create('/?api/admin/jobs', $post ? 'POST' : 'GET');
        if ($post && $token) {
            $request->headers->set(RequestGuard::HEADER, $this->getWiki()->services->get(RequestGuard::class)->token());
        }
        $controller = new JobsApiController();
        $controller->setServices($this->services($request));

        return $controller;
    }

    /** The wiki's own services for the admin, the session and the templates; the kit's for the queue. */
    private function services(Request $request, ?HealthService $health = null): ContainerInterface
    {
        $wiki = $this->getWiki()->services;
        $currentRequest = new CurrentRequest();
        $currentRequest->replace($request);
        $settings = $this->settings;
        $health ??= new HealthService([$this->kit->healthChecks($settings)]);
        $kit = $this->kit;
        $configuration = $this->configuration ?? new ConfigurationService();
        $overview = new JobsOverview($settings, $kit->statistics, $health, $kit->ticker, $wiki->get(LanguageService::class), $kit->fallback($settings), $kit->clock);

        return new ServiceLocator([
            AclService::class => static fn (): AclService => $wiki->get(AclService::class),
            AuthenticationService::class => static fn (): AuthenticationService => $wiki->get(AuthenticationService::class),
            RequestGuard::class => static fn (): RequestGuard => $wiki->get(RequestGuard::class),
            TemplateEngine::class => static fn (): TemplateEngine => $wiki->get(TemplateEngine::class),
            PageContext::class => static fn (): PageContext => $wiki->get(PageContext::class),
            CurrentRequest::class => static fn (): CurrentRequest => $currentRequest,
            HealthService::class => static fn (): HealthService => $health,
            JobsOverview::class => static fn (): JobsOverview => $overview,
            QueueSettings::class => static fn (): QueueSettings => $settings,
            QueueStatistics::class => static fn (): QueueStatistics => $kit->statistics,
            QueueTransport::class => static fn (): QueueTransport => $kit->transport,
            QueueRecorder::class => static fn (): QueueRecorder => $kit->recorder,
            QueueScheduleTicker::class => static fn (): QueueScheduleTicker => $kit->ticker,
            QueueConsumer::class => static fn (): QueueConsumer => $kit->consumer,
            QueuePassLimitsFactory::class => static fn (): QueuePassLimitsFactory => new QueuePassLimitsFactory(new NativeClock()),
            Journal::class => static fn (): Journal => $kit->journal,
            ConfigurationService::class => static fn (): ConfigurationService => $configuration,
        ]);
    }

    private function statusOf(Response $response): string
    {
        $body = (string)$response->getContent();
        $start = strpos($body, 'id="yw-jobs-status"');

        return $start === false ? '' : substr($body, $start, (int)strpos($body, 'id="yw-jobs-tabs"') - $start);
    }
}
