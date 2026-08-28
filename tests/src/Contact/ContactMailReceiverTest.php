<?php

namespace YesWiki\Test\Contact;

use Symfony\Component\HttpFoundation\Request;
use YesWiki\Contact\Api\ContactApiController;
use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Service\PageManager;
use YesWiki\Identity\Service\AclService;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * The contact endpoint only lets a logged in user name the receiver of a mail (GHSA-36fx-49jj-57rw): otherwise the wiki relays mail to any address a stranger puts in the request.
 *
 * A message shorter than ten characters is refused by the form validator, so an allowed request stops there rather than reaching the mail transport -- which is how these cases tell "allowed" from "sent".
 */
class ContactMailReceiverTest extends YesWikiTestCase
{
    private const PAGE_TAG = 'MailHandlerTestPage';
    private const PAGE_BODY = '{{contact mail="owner@example.org" entete="Site"}}';

    private \YesWiki\Core\YesWikiRuntime $wiki;
    private PageManager $pageManager;
    private AclService $aclService;
    private ContactApiController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->wiki = $this->getWiki();
        $this->pageManager = $this->wiki->services->get(PageManager::class);
        $this->aclService = $this->wiki->services->get(AclService::class);

        $this->controller = new ContactApiController();
        $this->controller->setServices($this->wiki->services);

        $this->pageManager->save(self::PAGE_TAG, [PageBody::CONTENT => self::PAGE_BODY], '', true);
        $this->aclService->save(self::PAGE_TAG, 'read', '*');

        $this->wiki->services->get(AuthenticationService::class)->logout();
    }

    protected function tearDown(): void
    {
        $this->wiki->services->get(AuthenticationService::class)->logout();
        $this->pageManager->deleteOrphaned(self::PAGE_TAG);
        $this->aclService->delete(self::PAGE_TAG);
        parent::tearDown();
    }

    /** @param array<string, string> $parameters */
    private function post(array $parameters): string
    {
        $body = array_merge([
            'pageTag' => self::PAGE_TAG,
            'email' => 'sender@example.org',
            'name' => 'Sender',
            'subject' => 'A subject',
            'entete' => 'Site',
            'message' => 'short',
        ], $parameters);

        $response = $this->controller->sendContactMail(Request::create('/', 'POST', $body));
        $decoded = json_decode((string)$response->getContent(), true);

        return (string)($decoded['message'] ?? '');
    }

    public function testAnAnonymousRequestCannotNameTheReceiver(): void
    {
        $message = $this->post(['mail' => 'victim@example.net']);

        $this->assertStringContainsString(_t('LOGIN_NOT_AUTORIZED'), $message);
        $this->assertStringNotContainsString(_t('CONTACT_MESSAGE_SUCCESSFULLY_SENT'), $message);
    }

    public function testAnAnonymousRequestCannotSendThePageToAChosenAddress(): void
    {
        $message = $this->post(['mail' => 'victim@example.net', 'type' => 'mail']);

        $this->assertStringContainsString(_t('LOGIN_NOT_AUTORIZED'), $message);
        $this->assertStringNotContainsString(_t('CONTACT_MESSAGE_SUCCESSFULLY_SENT'), $message);
    }

    /** The contact form itself stays open: what is reserved is naming the address, not writing. */
    public function testTheContactActionFormStaysOpenToAnonymousVisitors(): void
    {
        $message = $this->post(['type' => 'contact', 'nbactionmail' => '1']);

        $this->assertStringNotContainsString(_t('LOGIN_NOT_AUTORIZED'), $message);
        $this->assertStringContainsString(_t('CONTACT_ENTER_MESSAGE'), $message);
    }

    public function testALoggedInUserMayNameTheReceiver(): void
    {
        if (empty($this->wiki->services->get(AuthenticationService::class)->connectFirstAdmin())) {
            $this->markTestSkipped('no admin account in the test wiki');
        }

        $message = $this->post(['mail' => 'colleague@example.org']);

        $this->assertStringNotContainsString(_t('LOGIN_NOT_AUTORIZED'), $message);
        $this->assertStringContainsString(_t('CONTACT_ENTER_MESSAGE'), $message);
    }

    public function testAReadRestrictedPageRefusesAnonymousContact(): void
    {
        $this->aclService->save(self::PAGE_TAG, 'read', '@admins');

        $message = $this->post(['type' => 'contact', 'nbactionmail' => '1']);

        $this->assertStringContainsString(_t('LOGIN_NOT_AUTORIZED'), $message);
    }
}
