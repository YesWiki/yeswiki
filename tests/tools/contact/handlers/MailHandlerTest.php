<?php

namespace YesWiki\Test\Contact\Handlers;

use YesWiki\Core\Controller\AuthController;
use YesWiki\Core\Service\AclService;
use YesWiki\Core\Service\PageManager;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * The contact mail handler only lets a logged in user name the receiver of a mail.
 */
class MailHandlerTest extends YesWikiTestCase
{
    private const PAGE_TAG = 'MailHandlerTestPage';
    private const PAGE_BODY = '{{contact mail="owner@example.org" entete="Site"}}';

    private $wiki;
    private $pageManager;
    private $aclService;
    private $previousGet;
    private $previousPost;
    private $previousServer;
    private $previousTag;
    private $previousPage;
    private $previousMethod;

    protected function setUp(): void
    {
        $this->wiki = $this->getWiki();
        $GLOBALS['wiki'] = $this->wiki;
        $this->pageManager = $this->wiki->services->get(PageManager::class);
        $this->aclService = $this->wiki->services->get(AclService::class);

        $this->previousGet = $_GET;
        $this->previousPost = $_POST;
        $this->previousServer = $_SERVER;
        $this->previousTag = $this->wiki->tag;
        $this->previousPage = $this->wiki->page;
        $this->previousMethod = $this->wiki->method;

        $this->pageManager->save(self::PAGE_TAG, self::PAGE_BODY, '', true);
        $this->aclService->save(self::PAGE_TAG, 'read', '*');

        $this->wiki->tag = self::PAGE_TAG;
        $this->wiki->page = $this->pageManager->getOne(self::PAGE_TAG);
        $this->wiki->method = 'mail';

        $_GET = [];
        $_POST = [];
        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
        $this->wiki->services->get(AuthController::class)->logout();
    }

    protected function tearDown(): void
    {
        $this->wiki->services->get(AuthController::class)->logout();
        $this->pageManager->deleteOrphaned(self::PAGE_TAG);
        $this->aclService->delete(self::PAGE_TAG);

        $_GET = $this->previousGet;
        $_POST = $this->previousPost;
        $_SERVER = $this->previousServer;
        $this->wiki->tag = $this->previousTag;
        $this->wiki->page = $this->previousPage;
        $this->wiki->method = $this->previousMethod;
    }

    /**
     * a message shorter than ten characters is refused by check_parameters_mail,
     * so an allowed request stops there instead of reaching the mail transport.
     */
    private function post(array $parameters): string
    {
        $_POST = array_merge([
            'email' => 'sender@example.org',
            'name' => 'Sender',
            'subject' => 'A subject',
            'entete' => 'Site',
            'message' => 'short',
        ], $parameters);

        return $this->wiki->Method('mail');
    }

    public function testAnAnonymousRequestCannotNameTheReceiver()
    {
        $output = $this->post(['mail' => 'victim@example.net']);

        $this->assertStringContainsString(_t('LOGIN_NOT_AUTORIZED'), $output);
        $this->assertStringNotContainsString(_t('CONTACT_MESSAGE_SUCCESSFULLY_SENT'), $output);
    }

    public function testAnAnonymousRequestCannotSendThePageToAChosenAddress()
    {
        $output = $this->post(['mail' => 'victim@example.net', 'type' => 'mail']);

        $this->assertStringContainsString(_t('LOGIN_NOT_AUTORIZED'), $output);
        $this->assertStringNotContainsString(_t('CONTACT_MESSAGE_SUCCESSFULLY_SENT'), $output);
    }

    public function testTheContactActionFormStaysOpenToAnonymousVisitors()
    {
        $output = $this->post(['type' => 'contact', 'nbactionmail' => '1']);

        $this->assertStringNotContainsString(_t('LOGIN_NOT_AUTORIZED'), $output);
        $this->assertStringContainsString(_t('CONTACT_ENTER_MESSAGE'), $output);
    }

    public function testALoggedInUserMayNameTheReceiver()
    {
        if (empty($this->wiki->services->get(AuthController::class)->connectFirstAdmin())) {
            $this->markTestSkipped('no admin account in the test wiki');
        }

        $output = $this->post(['mail' => 'colleague@example.org']);

        $this->assertStringNotContainsString(_t('LOGIN_NOT_AUTORIZED'), $output);
        $this->assertStringContainsString(_t('CONTACT_ENTER_MESSAGE'), $output);
    }

    public function testAReadRestrictedPageRefusesAnonymousContact()
    {
        $this->aclService->save(self::PAGE_TAG, 'read', '@admins');

        $output = $this->post(['type' => 'contact', 'nbactionmail' => '1']);

        $this->assertStringContainsString(_t('LOGIN_NOT_AUTORIZED'), $output);
    }
}
