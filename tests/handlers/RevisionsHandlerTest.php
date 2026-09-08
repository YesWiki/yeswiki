<?php

namespace YesWiki\Test\Handlers;

use Symfony\Component\HttpFoundation\Request;
use YesWiki\Core\Exception\ExitException;
use YesWiki\Core\Service\AclService;
use YesWiki\Core\Service\DbService;
use YesWiki\Core\Service\PageManager;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * The revisions handler restores an old body only on a POST carrying a valid token.
 */
class RevisionsHandlerTest extends YesWikiTestCase
{
    private const PAGE_TAG = 'RevisionsHandlerTestPage';
    private const OTHER_TAG = 'RevisionsHandlerTestOtherPage';
    private const OLD_BODY = 'first version';
    private const CURRENT_BODY = 'second version';

    private $wiki;
    private $pageManager;
    private $aclService;
    private $dbService;
    private $oldRevisionId;
    private $otherRevisionId;
    private $previousRequest;
    private $previousTag;
    private $previousMethod;

    protected function setUp(): void
    {
        $this->wiki = $this->getWiki();
        $this->pageManager = $this->wiki->services->get(PageManager::class);
        $this->aclService = $this->wiki->services->get(AclService::class);
        $this->dbService = $this->wiki->services->get(DbService::class);

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $this->previousRequest = $this->wiki->request;
        $this->previousTag = $this->wiki->tag;
        $this->previousMethod = $this->wiki->method;

        foreach ([self::PAGE_TAG, self::OTHER_TAG] as $tag) {
            $this->pageManager->save($tag, self::OLD_BODY, '', true);
            $this->pageManager->save($tag, self::CURRENT_BODY, '', true);
            $this->aclService->save($tag, 'read', '*');
            $this->aclService->save($tag, 'write', '*');
        }
        $this->oldRevisionId = $this->firstRevisionId(self::PAGE_TAG);
        $this->otherRevisionId = $this->firstRevisionId(self::OTHER_TAG);

        $this->wiki->tag = self::PAGE_TAG;
        $this->wiki->method = 'revisions';
        unset($_SESSION['user']);
    }

    protected function tearDown(): void
    {
        foreach ([self::PAGE_TAG, self::OTHER_TAG] as $tag) {
            $this->pageManager->deleteOrphaned($tag);
            $this->aclService->delete($tag);
        }
        $this->wiki->request = $this->previousRequest;
        $this->wiki->tag = $this->previousTag;
        $this->wiki->method = $this->previousMethod;
    }

    private function firstRevisionId(string $tag): string
    {
        $row = $this->dbService->loadSingle(
            'SELECT id FROM ' . $this->dbService->prefixTable('pages')
            . " WHERE tag = '" . $this->dbService->escape($tag) . "' AND latest = 'N' ORDER BY id ASC LIMIT 1"
        );

        return $row['id'];
    }

    private function currentBody(string $tag): string
    {
        return $this->pageManager->getOne($tag)['body'];
    }

    private function runHandler(string $method, array $parameters = []): string
    {
        $this->wiki->request = Request::create('/?' . self::PAGE_TAG . '/revisions', $method, $parameters);

        try {
            return $this->wiki->Method('revisions');
        } catch (ExitException $e) {
            return '';
        }
    }

    public function testAGetRequestDoesNotRestore()
    {
        $this->runHandler('GET', ['restoreRevisionId' => $this->oldRevisionId]);

        $this->assertSame(self::CURRENT_BODY, $this->currentBody(self::PAGE_TAG));
    }

    public function testAPostWithoutATokenDoesNotRestore()
    {
        $this->runHandler('POST', ['restoreRevisionId' => $this->oldRevisionId]);

        $this->assertSame(self::CURRENT_BODY, $this->currentBody(self::PAGE_TAG));
    }

    public function testARevisionOfAnotherPageIsNeverRestored()
    {
        $this->runHandler('POST', ['restoreRevisionId' => $this->otherRevisionId]);

        $this->assertSame(self::CURRENT_BODY, $this->currentBody(self::PAGE_TAG), 'the page in the url was rolled back');
        $this->assertSame(self::CURRENT_BODY, $this->currentBody(self::OTHER_TAG), 'another page was rolled back');
    }

    public function testTheRestoreButtonIsAPostFormCarryingAToken()
    {
        $output = $this->runHandler('GET');

        $this->assertStringNotContainsString('restoreRevisionId=', $output);
        $this->assertMatchesRegularExpression(
            '/<form method="post"[^>]*>\s*<input type="hidden" name="csrf-token" value="[^"]+">/',
            $output,
            'revisions restore button is not a POST form carrying a csrf token'
        );
    }
}
