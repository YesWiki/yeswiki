<?php

namespace YesWiki\Test\Identity;

use Symfony\Component\HttpFoundation\Request;
use YesWiki\Content\Service\PageManager;
use YesWiki\Core\ApiResponse;
use YesWiki\Identity\Api\AuthApiController;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Kernel\Service\CurrentRequest;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\JournalSchema;
use YesWiki\Kernel\Service\RuntimeConfig;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** POST /api/login goes through the same door as the form: the local provider and the attempt limit. */
class ApiLoginTest extends YesWikiTestCase
{
    private const TAG = 'ApiLoginUser';
    private const PASSWORD = 'a-long-enough-password';

    private Request $requestWas;

    private $sectionWas;

    private int $journalFloor = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $services = self::getWiki()->services;
        $this->requestWas = $services->get(CurrentRequest::class)->get();
        $config = $services->get(RuntimeConfig::class);
        $this->sectionWas = $config['sso_providers'];
        $db = $services->get(DbService::class);
        $this->journalFloor = (int)$db->scalar("SELECT COALESCE(MAX({$db->quoteIdentifier('id')}), 0) FROM {$this->journal()}", 0);
        $services->get(UserManager::class)->create(self::TAG, 'api-login@example.com', self::PASSWORD);
    }

    protected function tearDown(): void
    {
        $services = self::getWiki()->services;
        $services->get(AuthenticationService::class)->logout();
        $config = $services->get(RuntimeConfig::class);
        if ($this->sectionWas === null) {
            unset($config['sso_providers']);
        } else {
            $config['sso_providers'] = $this->sectionWas;
        }
        $services->get(CurrentRequest::class)->replace($this->requestWas);
        if ($user = $services->get(UserManager::class)->getOneByName(self::TAG)) {
            $services->get(UserManager::class)->delete($user);
        }
        $services->get(PageManager::class)->deleteOrphaned(self::TAG);
        $db = $services->get(DbService::class);
        $db->query("DELETE FROM {$this->journal()} WHERE {$db->quoteIdentifier('id')} > {$this->journalFloor} AND {$db->quoteIdentifier('action')} IN ('login.failed', 'login.throttled')");
        parent::tearDown();
    }

    private function journal(): string
    {
        $services = self::getWiki()->services;

        return $services->get(DbService::class)->quoteIdentifier($services->get(JournalSchema::class)->table());
    }

    private function post(string $username, string $password): ApiResponse
    {
        $services = self::getWiki()->services;
        $services->get(CurrentRequest::class)->replace(new Request([], ['username' => $username, 'password' => $password]));
        $services->get(AuthenticationService::class)->startNewRequest();

        return $services->get(AuthApiController::class)->login();
    }

    public function testAGoodPasswordSignsIn(): void
    {
        $response = $this->post(self::TAG, self::PASSWORD);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(self::TAG, json_decode((string)$response->getContent(), true)['user']);
    }

    public function testAWrongPasswordIsRefused(): void
    {
        $this->assertSame(401, $this->post(self::TAG, 'nope')->getStatusCode());
        $this->assertSame(401, $this->post('Nobody', 'nope')->getStatusCode());
    }

    public function testItAnswers403WhenLocalIsOff(): void
    {
        self::getWiki()->services->get(RuntimeConfig::class)['sso_providers'] = ['local' => ['type' => 'local', 'enabled' => false]];

        $this->assertSame(403, $this->post(self::TAG, self::PASSWORD)->getStatusCode());
    }

    public function testItAnswers429OnceTheLoginIsBlocked(): void
    {
        for ($attempt = 0; $attempt < 5; ++$attempt) {
            $this->assertSame(401, $this->post(self::TAG, 'nope')->getStatusCode());
        }

        $response = $this->post(self::TAG, self::PASSWORD);
        $this->assertSame(429, $response->getStatusCode());
        $this->assertSame('too_many_attempts', json_decode((string)$response->getContent(), true)['error']);
    }
}
