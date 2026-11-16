<?php

namespace YesWiki\Test\Kernel;

use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\HttpFoundation\JsonResponse;
use YesWiki\Core\YesWikiRuntime;
use YesWiki\Kernel\Service\ErrorFallbackPage;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** End-users 15: a 403, 404, 405 or 500 answers with a page in the wiki's layout and language, JSON for the API and htmx, and a standalone page when the layout cannot be drawn. */
#[CoversClass(YesWikiRuntime::class)]
#[CoversClass(ErrorFallbackPage::class)]
class ErrorPagesTest extends YesWikiTestCase
{
    private mixed $debugWas = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->debugWas = self::getWiki()->config['debug'] ?? false;
    }

    protected function tearDown(): void
    {
        $wiki = self::getWiki();
        $wiki->config['debug'] = $this->debugWas;
        $wiki->request->headers->remove('HX-Request');
        parent::tearDown();
    }

    public function testAnUnknownScreenIsAPageWithAWayHome(): void
    {
        $response = self::getWiki()->errorResponse(404, route: 'dashboard/nothing');
        $page = (string)$response->getContent();

        $this->assertSame(404, $response->getStatusCode());
        $this->assertStringContainsString(htmlspecialchars(_t('ERROR_PAGE_404_TITLE')), $page);
        $this->assertStringContainsString(htmlspecialchars(_t('HTMX_ERROR_NOT_FOUND')), $page, 'the sentence an htmx toast says for a 404');
        $this->assertStringContainsString('name="q"', $page, 'a 404 offers a search');
        $this->assertStringContainsString('yw-error-page__actions', $page);
        $this->assertStringContainsString('<html', $page, 'in the wiki layout, not a bare fragment');
        $this->assertStringContainsString('no-store', (string)$response->headers->get('Cache-Control'));
    }

    public function testTheApiStillAnswersInJson(): void
    {
        $response = self::getWiki()->errorResponse(404, route: 'api/nothing');

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(['error' => _t('HTMX_ERROR_NOT_FOUND')], json_decode((string)$response->getContent(), true));
    }

    public function testAFailureShowsItsIncidentAndNoTraceUnlessDebugging(): void
    {
        $wiki = self::getWiki();
        $wiki->config['debug'] = false;
        $failure = new \RuntimeException('connection to /var/secret/db failed');

        $response = $wiki->errorResponse(500, 'abcd1234', $failure, 'admin/files');
        $page = (string)$response->getContent();

        $this->assertSame(500, $response->getStatusCode());
        $this->assertStringContainsString(htmlspecialchars(_t('HTMX_ERROR_SERVER', ['status' => '500'])), $page, 'word for word the toast of end-users 03');
        $this->assertStringContainsString('abcd1234', $page);
        $this->assertStringNotContainsString('/var/secret', $page);

        $wiki->config['debug'] = true;
        $this->assertStringContainsString('yw-error-page__trace', (string)$wiki->errorResponse(500, 'abcd1234', $failure, 'admin/files')->getContent());
    }

    public function testAnHtmxRequestGetsWhatItsToastShows(): void
    {
        $wiki = self::getWiki();
        $wiki->request->headers->set('HX-Request', 'true');

        $response = $wiki->errorResponse(500, 'abcd1234', new \RuntimeException('boom'), 'admin/files');
        $body = json_decode((string)$response->getContent(), true);

        $this->assertSame('abcd1234', $body['incident'] ?? null);
        $this->assertStringContainsString('abcd1234', (string)($body['error'] ?? ''));
    }

    public function testThe403LooksLikeTheOthers(): void
    {
        $page = (string)self::getWiki()->errorResponse(403, route: 'admin/files')->getContent();

        $this->assertStringContainsString(htmlspecialchars(_t('ERROR_NO_ACCESS')), $page);
        $this->assertStringContainsString(htmlspecialchars(_t('HTMX_ERROR_FORBIDDEN')), $page);
        $this->assertStringNotContainsString('name="q"', $page);
    }

    public function testTheStandalonePageIsAWholeDocument(): void
    {
        $response = ErrorFallbackPage::response(500, 'abcd1234', 'https://wiki.example.org/?');
        $page = (string)$response->getContent();

        $this->assertStringStartsWith('<!doctype html>', $page);
        $this->assertStringContainsString('(500)', $page);
        $this->assertStringContainsString('abcd1234', $page);
        $this->assertStringContainsString('href="https://wiki.example.org/?"', $page);
        $this->assertStringContainsString('no-store', (string)$response->headers->get('Cache-Control'));
        $this->assertStringNotContainsString('data-incident', (string)ErrorFallbackPage::response(503, null, '')->getContent(), 'no incident, no line for it');
        $this->assertStringNotContainsString('%', (string)ErrorFallbackPage::response(503, null, '')->getContent());
    }
}
