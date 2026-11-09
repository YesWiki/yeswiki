<?php

namespace YesWiki\Test\Kernel;

use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Route;
use YesWiki\Core\ApiResponse;
use YesWiki\Kernel\Api\QueueApiController;
use YesWiki\Kernel\Queue\QueueConsumerMode;
use YesWiki\Kernel\Service\CurrentRequest;
use YesWiki\Kernel\Service\Queue\QueueConsumer;
use YesWiki\Kernel\Service\Queue\QueuePassLimitsFactory;
use YesWiki\Kernel\Service\Queue\QueueRecorder;
use YesWiki\Kernel\Service\Queue\QueueSettings;
use YesWiki\Kernel\Service\RouteProvider;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';
require_once 'tests/src/Kernel/QueueTestKit.php';

/** `GET|POST /api/queue/consume`: an online cron drains the queue by presenting the wiki's secret. */
#[CoversClass(QueueApiController::class)]
class QueueApiControllerTest extends YesWikiTestCase
{
    private const SECRET = 'a-long-random-secret';

    private QueueTestKit $kit;

    protected function setUp(): void
    {
        // The wiki loads its configuration before the kit points YESWIKI_CONFIG_FILE at its own.
        $this->getWiki();
        $this->kit = new QueueTestKit();
    }

    protected function tearDown(): void
    {
        $this->kit->dispose();
    }

    /** The route is what makes the `api` mode work for a cron that is nobody: public, and reachable by both verbs a cron service can send. */
    public function testTheRouteIsPublicAndTakesGetAndPost(): void
    {
        $routes = array_values(array_filter(
            $this->getWiki()->services->get(RouteProvider::class)->get()->all(),
            static fn (Route $route): bool => $route->getPath() === '/api/queue/consume'
        ));

        $this->assertCount(1, $routes);
        $this->assertEqualsCanonicalizing(['GET', 'POST'], $routes[0]->getMethods());
        $this->assertSame(['public'], (array)$routes[0]->getOption('acl'), 'the secret is the guard, not a login');
    }

    public function testWrongSecretIs401(): void
    {
        $this->kit->send('a');

        $response = $this->call(self::SECRET, 'not-the-secret');

        $this->assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
        $this->assertSame(['error' => 'Unauthorized'], $this->body($response));
        $this->assertSame([], $this->kit->handled, 'a caller without the secret consumes nothing');
    }

    public function testNoSecretSentIs401(): void
    {
        $this->assertSame(Response::HTTP_UNAUTHORIZED, $this->call(self::SECRET, null)->getStatusCode());
    }

    public function testEmptyConfiguredSecretIs401(): void
    {
        $this->kit->send('a');

        foreach ([null, ''] as $sent) {
            foreach ([$this->call('', $sent), $this->call('', null, 'GET', $sent)] as $response) {
                $this->assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode(), 'empty equals empty must not open the route');
                $this->assertSame(['error' => 'Unauthorized'], $this->body($response));
            }
        }
        $this->assertSame([], $this->kit->handled);
    }

    public function testRightSecretDrains(): void
    {
        $this->kit->send('a');
        $this->kit->send('b');

        $response = $this->call(self::SECRET, self::SECRET);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $body = $this->body($response);
        $this->assertSame(2, $body['handled']);
        $this->assertSame(0, $body['remaining']);
        $this->assertSame('empty', $body['stopReason']);
        $this->assertIsInt($body['durationMs']);
        $this->assertSame(['a', 'b'], $this->kit->handled);
        $this->assertSame('api', $this->kit->statistics->lastPass()['mode'] ?? null);
        $this->assertNotNull($this->kit->statistics->lastSeenAt(QueueConsumerMode::Api), 'the call is the cron: it is seen');
    }

    /** The API's pass has no time limit: messages the history says are long are all taken, the averages bound nothing. */
    public function testLongMessagesAreAllTaken(): void
    {
        QueueTestKit::recordRun($this->kit->db, QueueTestMessage::class, QueueRecorder::HANDLED, 120_000, '2026-10-23 11:00:00');
        $this->kit->send('a');
        $this->kit->send('b');

        $body = $this->body($this->call(self::SECRET, self::SECRET));

        $this->assertSame(['a', 'b'], $this->kit->handled);
        $this->assertSame(2, $body['handled']);
        $this->assertSame(0, $body['remaining']);
        $this->assertSame('empty', $body['stopReason']);
    }

    public function testGetWithTheSecretInTheQueryDrains(): void
    {
        $this->kit->send('a');

        $response = $this->call(self::SECRET, null, 'GET', self::SECRET);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertSame(1, $this->body($response)['handled']);
        $this->assertSame(['a'], $this->kit->handled);
        $this->assertSame('api', $this->kit->statistics->lastPass()['mode'] ?? null);
    }

    public function testWrongSecretInTheQueryIs401(): void
    {
        $this->kit->send('a');

        $this->assertSame(Response::HTTP_UNAUTHORIZED, $this->call(self::SECRET, null, 'GET', 'not-the-secret')->getStatusCode());
        $this->assertSame(Response::HTTP_UNAUTHORIZED, $this->call(self::SECRET, null, 'GET', [self::SECRET])->getStatusCode(), 'secret[]= is no secret');
        $this->assertSame([], $this->kit->handled);
    }

    public function testTheHeaderWinsOverTheQuery(): void
    {
        $this->assertSame(Response::HTTP_OK, $this->call(self::SECRET, self::SECRET, 'GET', 'not-the-secret')->getStatusCode());
        $this->assertSame(Response::HTTP_UNAUTHORIZED, $this->call(self::SECRET, 'not-the-secret', 'GET', self::SECRET)->getStatusCode());
    }

    /** @return array<string, mixed> */
    private function body(Response $response): array
    {
        $decoded = json_decode((string)$response->getContent(), true);
        $this->assertIsArray($decoded);

        return $decoded;
    }

    /**
     * The route as the kernel calls it: the secret is in the configuration, the caller's in a header or the query.
     *
     * @param string|list<string>|null $query
     */
    private function call(string $configured, ?string $sent, string $method = 'POST', string|array|null $query = null): ApiResponse
    {
        $request = Request::create('/api/queue/consume', $method, $query === null ? [] : [QueueApiController::SECRET_QUERY => $query]);
        if ($sent !== null) {
            $request->headers->set('X-YesWiki-Queue-Secret', $sent);
        }
        $currentRequest = new CurrentRequest();
        $currentRequest->replace($request);
        $settings = new QueueSettings(new ParameterBag(['queue_consume_secret' => $configured]));

        $controller = new QueueApiController();
        $controller->setServices(new ServiceLocator([
            CurrentRequest::class => static fn (): CurrentRequest => $currentRequest,
            QueueSettings::class => static fn (): QueueSettings => $settings,
            QueueConsumer::class => fn (): QueueConsumer => $this->kit->consumer,
            QueuePassLimitsFactory::class => static fn (): QueuePassLimitsFactory => new QueuePassLimitsFactory(new NativeClock()),
        ]));

        return $controller->consume();
    }
}
