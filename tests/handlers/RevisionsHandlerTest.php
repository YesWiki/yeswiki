<?php

namespace YesWiki\Test\Handlers;

use Symfony\Component\HttpFoundation\Request;
use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Service\PageManager;
use YesWiki\Identity\Service\AclService;
use YesWiki\Kernel\Exception\ExitException;
use YesWiki\Kernel\Service\CurrentRequest;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Render\Service\Performer;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * The revisions handler restores an old body only on a POST carrying a valid token, and only a revision of the page it is on (GHSA-vj5g-974q-7ff3).
 *
 * A valid token cannot be presented from the command line -- the checker reads the real request -- so each case here is a refusal or a markup assertion.
 */
class RevisionsHandlerTest extends YesWikiTestCase
{
    private const PAGE_TAG = 'RevisionsHandlerTestPage';
    private const OTHER_TAG = 'RevisionsHandlerTestOtherPage';
    private const OLD_BODY = 'first version';
    private const CURRENT_BODY = 'second version';

    private \YesWiki\Core\YesWikiRuntime $wiki;
    private PageManager $pageManager;
    private AclService $aclService;
    private DbService $dbService;
    private string $oldRevisionId = '';
    private string $otherRevisionId = '';
    private ?Request $previousRequest = null;
    private string $previousTag = '';
    private string $previousMethod = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->wiki = $this->getWiki();
        $this->pageManager = $this->wiki->services->get(PageManager::class);
        $this->aclService = $this->wiki->services->get(AclService::class);
        $this->dbService = $this->wiki->services->get(DbService::class);

        $pageContext = $this->wiki->services->get(PageContext::class);
        $this->previousRequest = $this->wiki->services->get(CurrentRequest::class)->get();
        $this->previousTag = $pageContext->getTag();
        $this->previousMethod = $pageContext->getRawMethod();

        foreach ([self::PAGE_TAG, self::OTHER_TAG] as $tag) {
            $this->pageManager->save($tag, [PageBody::CONTENT => self::OLD_BODY], '', true);
            $this->pageManager->save($tag, [PageBody::CONTENT => self::CURRENT_BODY], '', true);
            $this->aclService->save($tag, 'read', '*');
            $this->aclService->save($tag, 'write', '*');
        }
        $this->oldRevisionId = $this->firstRevisionId(self::PAGE_TAG);
        $this->otherRevisionId = $this->firstRevisionId(self::OTHER_TAG);

        $pageContext->setTag(self::PAGE_TAG);
        $pageContext->setMethod('revisions');
        unset($_SESSION['user']);
    }

    protected function tearDown(): void
    {
        foreach ([self::PAGE_TAG, self::OTHER_TAG] as $tag) {
            $this->pageManager->deleteOrphaned($tag);
            $this->aclService->delete($tag);
        }
        if ($this->previousRequest !== null) {
            $this->wiki->services->get(CurrentRequest::class)->replace($this->previousRequest);
        }
        $pageContext = $this->wiki->services->get(PageContext::class);
        $pageContext->setTag($this->previousTag);
        $pageContext->setMethod($this->previousMethod);
        parent::tearDown();
    }

    private function firstRevisionId(string $tag): string
    {
        $row = $this->dbService->loadSingle(
            'SELECT id FROM ' . $this->dbService->prefixTable('pages')
            . " WHERE tag = ? AND latest = 'N' ORDER BY id ASC LIMIT 1",
            [$tag]
        );

        return (string)($row['id'] ?? '');
    }

    private function currentBody(string $tag): string
    {
        $page = $this->pageManager->getOne($tag, null, false, true);

        return $page === null ? '' : PageBody::content($page['body'] ?? []);
    }

    /** @param array<string, string> $parameters */
    private function runHandler(string $method, array $parameters = []): string
    {
        $this->wiki->services->get(CurrentRequest::class)->replace(
            Request::create('/?' . self::PAGE_TAG . '/revisions', $method, $parameters)
        );

        try {
            return (string)$this->wiki->services->get(Performer::class)->run('revisions', 'handler', []);
        } catch (\Throwable $thrown) {
            if (ExitException::in($thrown) === null) {
                throw $thrown;
            }

            return '';
        }
    }

    public function testTheFixtureHasARevisionToRestore(): void
    {
        $this->assertNotSame('', $this->oldRevisionId);
        $this->assertSame(self::CURRENT_BODY, $this->currentBody(self::PAGE_TAG));
    }

    public function testAGetRequestDoesNotRestore(): void
    {
        $this->runHandler('GET', ['restoreRevisionId' => $this->oldRevisionId]);

        $this->assertSame(self::CURRENT_BODY, $this->currentBody(self::PAGE_TAG));
    }

    public function testAPostWithoutATokenDoesNotRestore(): void
    {
        $this->runHandler('POST', ['restoreRevisionId' => $this->oldRevisionId]);

        $this->assertSame(self::CURRENT_BODY, $this->currentBody(self::PAGE_TAG));
    }

    public function testARevisionOfAnotherPageIsNeverRestored(): void
    {
        $this->runHandler('POST', ['restoreRevisionId' => $this->otherRevisionId]);

        $this->assertSame(self::CURRENT_BODY, $this->currentBody(self::PAGE_TAG), 'the page in the url was rolled back');
        $this->assertSame(self::CURRENT_BODY, $this->currentBody(self::OTHER_TAG), 'another page was rolled back');
    }

    public function testTheRestoreButtonIsAPostFormCarryingAToken(): void
    {
        $output = $this->runHandler('GET');

        $this->assertStringNotContainsString('restoreRevisionId=', $output, 'a GET link would restore on being followed');
        $this->assertMatchesRegularExpression(
            '/<form method="post"[^>]*>\s*<input type="hidden" name="csrf-token" value="[^"]+">/',
            $output,
            'the revisions restore button is not a POST form carrying a csrf token'
        );
    }
}
