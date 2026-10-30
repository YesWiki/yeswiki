<?php

namespace YesWiki\Test\Admin;

use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use YesWiki\Admin\Api\JobsApiController;
use YesWiki\Admin\Controller\JobsController;
use YesWiki\Admin\Service\JobsOverview;
use YesWiki\Identity\Service\AclService;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Identity\Service\RequestGuard;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Kernel\Health\HealthCheck;
use YesWiki\Kernel\Health\ProvidesHealthChecks;
use YesWiki\Kernel\Queue\QueueConsumerMode;
use YesWiki\Kernel\Queue\QueuePassOrigin;
use YesWiki\Kernel\Queue\QueuePassReport;
use YesWiki\Kernel\Service\ConfigurationService;
use YesWiki\Kernel\Service\CurrentRequest;
use YesWiki\Kernel\Service\HealthService;
use YesWiki\Kernel\Service\Journal;
use YesWiki\Kernel\Service\LanguageService;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Kernel\Service\Queue\QueueBudgetFactory;
use YesWiki\Kernel\Service\Queue\QueueConsumer;
use YesWiki\Kernel\Service\Queue\QueueHealthChecks;
use YesWiki\Kernel\Service\Queue\QueueRecorder;
use YesWiki\Kernel\Service\Queue\QueueScheduleTicker;
use YesWiki\Kernel\Service\Queue\QueueSettings;
use YesWiki\Kernel\Service\Queue\QueueStatistics;
use YesWiki\Kernel\Service\Queue\QueueTransport;
use YesWiki\Kernel\Service\RouteProvider;
use YesWiki\Render\Service\TemplateEngine;
use YesWiki\Test\Core\YesWikiTestCase;
use YesWiki\Test\Kernel\QueueTestKit;
use YesWiki\Test\Kernel\QueueTestToleratedMessage;

require_once 'tests/YesWikiTestCase.php';
require_once 'tests/src/Kernel/QueueTestKit.php';
require_once 'tests/src/Kernel/QueueTestToleratedMessage.php';

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
    private QueueTestKit $kit;
    private QueueSettings $settings;

    protected function setUp(): void
    {
        $this->kit = new QueueTestKit();
        putenv(QueueSettings::ENV_MODE);
        $this->settings = new QueueSettings(new ParameterBag(['queue_consumer' => '', 'queue_consume_secret' => '']), static fn (): array => []);
        $GLOBALS['yeswikiServices'] = $this->getWiki()->services;
    }

    protected function tearDown(): void
    {
        $this->getWiki()->services->get(AuthenticationService::class)->logout();
        $this->kit->dispose();
    }

    public function testNonAdminIsRefused(): void
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

        $this->kit->send('a');
        $calls = [
            'the page' => fn () => $this->page()->jobs(),
            'a tab' => fn () => $this->api()->tab('failed'),
            'consume' => fn () => $this->api(post: true)->consume(),
            'retry' => fn () => $this->api(post: true)->retry('1'),
            'retry a type' => fn () => $this->api(post: true)->retryType('QueueTestToleratedMessage'),
            'ignore a type' => fn () => $this->api(post: true)->ignoreType('QueueTestToleratedMessage'),
            'secret' => fn () => $this->api(post: true)->secret(),
        ];
        foreach ($calls as $what => $call) {
            try {
                $call();
                $this->fail("{$what} answered someone who is not an admin");
            } catch (AccessDeniedHttpException) {
            }
        }
        $this->assertSame([], $this->kit->handled);
    }

    /** The page as the wiki's own container builds it, over the dev wiki's queue: the services wire up and it renders. */
    public function testTheWikiWiresThePage(): void
    {
        $this->signInAsAdmin();

        $body = (string)$this->getWiki()->services->get(JobsController::class)->jobs()->getContent();

        $this->assertStringContainsString('id="yw-jobs-status"', $body);
        $this->assertStringContainsString('id="yw-jobs-tabs"', $body);
    }

    public function testAPostWithoutTheSessionTokenIsRefused(): void
    {
        $this->signInAsAdmin();
        $this->kit->send('a');

        $response = $this->api(post: true, token: false)->consume();

        $this->assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        $this->assertSame([], $this->kit->handled);
    }

    public function testThePageShowsTheModeAndItsOrigin(): void
    {
        $this->signInAsAdmin();
        putenv(QueueSettings::ENV_MODE . '=command');

        $body = (string)$this->page()->jobs()->getContent();

        $this->assertStringContainsString(_t('QUEUE_MODE_COMMAND'), $body);
        $this->assertStringContainsString(
            htmlspecialchars(_t('ADMIN_JOBS_ORIGIN_SET', ['origin' => _t('QUEUE_ORIGIN_ENV')]), ENT_QUOTES),
            $body,
            'the admin is told where the mode was set, so they know where to change it'
        );
        $this->assertStringContainsString(htmlspecialchars(_t('QUEUE_PACE_COMMAND'), ENT_QUOTES), $body);
        $this->assertStringContainsString('id="yw-jobs-tabs"', $body);
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

    public function testAnActionOnATypeNeedsTheSessionToken(): void
    {
        $this->signInAsAdmin();
        $id = $this->failedTolerated('inbox');

        $this->assertSame(Response::HTTP_FORBIDDEN, $this->api(post: true, token: false)->retryType('QueueTestToleratedMessage')->getStatusCode());
        $this->assertSame(Response::HTTP_FORBIDDEN, $this->api(post: true, token: false)->ignoreType('QueueTestToleratedMessage')->getStatusCode());
        $this->assertNotNull($this->kit->statistics->failedMessage($id));
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
        $health = new HealthService([new class([$check, $plain]) implements ProvidesHealthChecks {
            /** @param list<HealthCheck> $checks */
            public function __construct(private array $checks)
            {
            }

            public function healthChecks(): array
            {
                return $this->checks;
            }
        }]);

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
        $this->assertSame('request', $pass['mode'], 'the mode of the wiki, which is budgeted');
        $this->assertFalse($pass['beforeResponse'], 'an admin who asked for the pass is not a visitor kept waiting');
        $this->assertStringContainsString('id="yw-jobs-status"', (string)$response->getContent(), 'the alerts come back refreshed');
        $this->assertStringContainsString(htmlspecialchars(_t('ADMIN_JOBS_CONSUMED', [
            'handled' => 2,
            'remaining' => 0,
            'reason' => _t('QUEUE_STOP_EMPTY'),
        ]), ENT_QUOTES), (string)$response->getContent());
    }

    /** The admin's button is not the consumer the wiki waits for: pressed during a fallback, it must not end it. */
    public function testConsumeNowIsRecordedAsManual(): void
    {
        $this->signInAsAdmin();
        putenv(QueueSettings::ENV_MODE . '=command');
        $this->kit->send('a');

        $this->api(post: true)->consume();

        $this->assertSame('manual', $this->kit->statistics->lastPass()['origin'] ?? null);
    }

    public function testABlockingModeIsConsumedAsTheCommandWould(): void
    {
        $this->signInAsAdmin();
        putenv(QueueSettings::ENV_MODE . '=worker');
        $this->kit->send('a');

        $this->api(post: true)->consume();

        $this->assertSame(['a'], $this->kit->handled);
        $this->assertSame('command', $this->kit->statistics->lastPass()['mode'] ?? null);
    }

    public function testGeneratingASecretStoresIt(): void
    {
        $this->signInAsAdmin();

        $body = (string)$this->api(post: true)->secret()->getContent();

        if (preg_match('/\b([0-9a-f]{48})\b/', $body, $shown) !== 1) {
            $this->fail('the new secret is shown');
        }
        $config = (new ConfigurationService())->getConfiguration($this->kit->configFile());
        $config->load();
        $this->assertSame($shown[1], $config['queue_consume_secret'], 'the secret shown is the one the configuration keeps');
        $this->assertStringContainsString('X-YesWiki-Queue-Secret: ' . $shown[1], $body, 'with the call to schedule');
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
        $this->kit->consumer->drain(QueueConsumerMode::Command, (new QueueBudgetFactory())->forMode(QueueConsumerMode::Command, true));

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
        $health = new HealthService([new class([$failing('queue-one', 'admin/jobs'), $failing('federation-delivery', 'admin/jobs'), $failing('elsewhere', 'admin/updates'), $failing('queue-degraded', 'admin/jobs', broken: false), HealthCheck::named('queue-fine')->linkedTo('admin/jobs')->runs(static fn (): ?string => null)]) implements ProvidesHealthChecks {
            /** @param list<HealthCheck> $checks */
            public function __construct(private array $checks)
            {
            }

            public function healthChecks(): array
            {
                return $this->checks;
            }
        }]);

        $body = (string)$this->page($health)->jobs()->getContent();

        $this->assertMatchesRegularExpression(
            '#admin/jobs"[^>]*>.*?<span class="yw-dashboard__count">2</span>#s',
            $body,
            'the rail entry counts the Broken findings that send the admin here, a module\'s included'
        );
        $this->assertMatchesRegularExpression('#admin/health"[^>]*>.*?<span class="yw-dashboard__count">3</span>#s', $body);
    }

    // --- Helpers -----------------------------------------------------------------------------

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
        }
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
        $overview = new JobsOverview($settings, $kit->statistics, new QueueBudgetFactory(), $health, $kit->ticker, $wiki->get(LanguageService::class), $kit->fallback($settings));

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
            QueueBudgetFactory::class => static fn (): QueueBudgetFactory => new QueueBudgetFactory(),
            Journal::class => static fn (): Journal => $kit->journal,
            ConfigurationService::class => static fn (): ConfigurationService => new ConfigurationService(),
        ]);
    }

    private function statusOf(Response $response): string
    {
        $body = (string)$response->getContent();
        $start = strpos($body, 'id="yw-jobs-status"');

        return $start === false ? '' : substr($body, $start, (int)strpos($body, 'id="yw-jobs-tabs"') - $start);
    }
}
