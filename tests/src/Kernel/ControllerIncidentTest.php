<?php

namespace YesWiki\Test\Kernel;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\JournalSchema;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** A controller that throws answers an incident id the Journal holds too, and its trace only in debug, server paths hidden (end-users 01). */
class ControllerIncidentTest extends YesWikiTestCase
{
    private mixed $debugWas;

    protected function setUp(): void
    {
        parent::setUp();
        $this->debugWas = self::getWiki()->config['debug'] ?? false;
    }

    protected function tearDown(): void
    {
        self::getWiki()->config['debug'] = $this->debugWas;
        parent::tearDown();
    }

    /** @return array<string, mixed> the body the failing controller answers with */
    private function failingController(bool $debug, ?\Throwable $failure = null): array
    {
        $wiki = self::getWiki();
        $wiki->config['debug'] = $debug;
        $event = new ExceptionEvent($this->createStub(HttpKernelInterface::class), Request::create('/?api/broken'), HttpKernelInterface::MAIN_REQUEST, $failure ?? new \RuntimeException('secret path ' . __FILE__));
        $wiki->onDispatchException($event);
        $response = $event->getResponse();
        $this->assertNotNull($response);
        $this->assertSame(500, $response->getStatusCode());
        $body = json_decode((string)$response->getContent(), true);
        $this->assertIsArray($body);

        return $body;
    }

    public function testOutsideDebugTheBodySaysNothingOfTheServer(): void
    {
        $body = $this->failingController(false);
        $raw = (string)json_encode($body);

        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}$/', (string)$body['incident']);
        $this->assertStringContainsString((string)$body['incident'], (string)$body['error'], 'the visitor gets a sentence carrying the reference');
        $this->assertStringContainsString(_t('INCIDENT_UNEXPECTED'), (string)$body['error'], 'and, for a failure nobody foresaw, that the action did not go through');
        $this->assertStringContainsString(_t('ERROR_INCIDENT', ['id' => (string)$body['incident']]), (string)$body['error'], 'and whom to give the code to');
        $this->assertArrayNotHasKey('exceptionMessage', $body);
        $this->assertStringNotContainsString('secret path', $raw, 'no message');
        $this->assertStringNotContainsString(\YESWIKI_PROGRAM_DIR, $raw, 'no file path');
        $this->assertStringNotContainsString('#0 ', $raw, 'no frame');

        $db = self::getWiki()->services->get(DbService::class);
        $found = $db->loadAll('SELECT context FROM ' . self::getWiki()->services->get(JournalSchema::class)->table() . ' WHERE ' . $db->jsonAsText('context') . ' LIKE ?', ['%"incident":"' . $body['incident'] . '"%']);
        $this->assertNotEmpty($found, 'the webmaster finds the incident in the Journal');
    }

    public function testInDebugTheTraceIsShownWithoutTheServerPaths(): void
    {
        $body = $this->failingController(true);

        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}$/', (string)$body['incident']);
        $this->assertStringContainsString('secret path', (string)$body['exceptionMessage']);
        $this->assertStringNotContainsString(\YESWIKI_PROGRAM_DIR, (string)$body['exceptionMessage'], 'ThrowableFormatter hides the server paths');
    }

    public function testAKnownCauseIsSaidInPlainWords(): void
    {
        $database = $this->failingController(false, new \RuntimeException('query failed', 0, new \PDOException('SQLSTATE[HY000] [2002] Connection refused')));
        $this->assertStringContainsString(_t('INCIDENT_DATABASE'), (string)$database['error']);
        $this->assertStringNotContainsString('SQLSTATE', (string)$database['error']);

        $written = $this->failingController(false, new \YesWiki\Content\Exception\ReservedTagException(_t('RESERVED_TAG_CANNOT_BE_USED', ['tag' => 'api'])));
        $this->assertSame(0, strpos((string)$written['error'], _t('RESERVED_TAG_CANNOT_BE_USED', ['tag' => 'api'])), 'a failure written for the reader keeps its own sentence');
    }
}
