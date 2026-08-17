<?php

namespace YesWiki\Test\Core\Controller;

use PHPUnit\Framework\Attributes\CoversMethod;
use Symfony\Component\HttpFoundation\Request;
use YesWiki\Contact\Api\ContactApiController;
use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Service\PageManager;
use YesWiki\Identity\Service\AclService;
use YesWiki\Kernel\Service\Mailer;
use YesWiki\Test\Core\YesWikiTestCase;
use YesWiki\YesWikiRuntime;

require_once 'tests/YesWikiTestCase.php';

/**
 * Regression test for ticket 18 (contact absorbed into core): contact-form submission
 * moves to POST /api/contact/mail, routed through Mailer::send() instead of a direct
 * send_mail() call. Doesn't exercise an actual successful send (no SMTP transport in
 * this sandbox, matching LoginRelatedActionsTest's established "don't trigger a real
 * send_mail() call" precedent) -- covers the validation/ACL/routing paths that don't
 * require one, plus a direct Mailer::send() unit test.
 */
#[CoversMethod(ContactApiController::class, 'sendContactMail')]
class ApiControllerContactTest extends YesWikiTestCase
{
    private const PRIVATE_PAGE_TAG = 'ApiControllerContactTestPrivatePage';
    private const PUBLIC_PAGE_TAG = 'ApiControllerContactTestPublicPage';

    /**
     * The fixtures go when the tests do.
     *
     * phpunit runs against a real wiki -- the developer's own -- so a fixture left behind is a
     * page in somebody's index, for ever.
     */
    public static function tearDownAfterClass(): void
    {
        $pageManager = self::getWiki()->services->get(PageManager::class);
        foreach ([self::PRIVATE_PAGE_TAG, self::PUBLIC_PAGE_TAG] as $tag) {
            $pageManager->deleteOrphaned($tag);
        }
    }

    protected function tearDown(): void
    {
        // avoid leaking $GLOBALS['wiki'] into later tests (same convention as
        // FiltertagsActionTest's established $GLOBALS['wiki'] workaround)
        unset($GLOBALS['wiki']);
    }

    public function testWikiExisting(): YesWikiRuntime
    {
        $wiki = $this->getWiki();
        $this->assertTrue($wiki->services->has(ContactApiController::class));

        $pageManager = $wiki->services->get(PageManager::class);
        $aclService = $wiki->services->get(AclService::class);

        $pageManager->save(self::PRIVATE_PAGE_TAG, [PageBody::CONTENT => '{{contact mail="test@example.com"}}'], '', true);
        $aclService->save(self::PRIVATE_PAGE_TAG, 'read', '@admins');

        $pageManager->save(self::PUBLIC_PAGE_TAG, [PageBody::CONTENT => '{{contact mail="test@example.com"}}'], '', true);
        $aclService->save(self::PUBLIC_PAGE_TAG, 'read', '*');

        return $wiki;
    }

    #[\PHPUnit\Framework\Attributes\Depends('testWikiExisting')]
    public function testMissingPageTagIsRejected(YesWikiRuntime $wiki)
    {
        $controller = $wiki->services->get(ContactApiController::class);
        $response = $controller->sendContactMail(Request::create('/api/contact/mail', 'POST', []));

        $this->assertSame(400, $response->getStatusCode());
    }

    #[\PHPUnit\Framework\Attributes\Depends('testWikiExisting')]
    public function testValidationFailureDoesNotAttemptToSend(YesWikiRuntime $wiki)
    {
        // contact.functions.php's parseMails()/FindMailFromWikiPage() (reached via the
        // mail-from-page-body lookup, run before validation) read $GLOBALS['wiki']
        // directly, only populated by the production HTTP bootstrap -- same pre-existing,
        // out-of-scope $GLOBALS['wiki']-reliance class of issue as ticket 11's {{aceditor}} tests
        $GLOBALS['yeswikiServices'] = $wiki->services;
        $controller = $wiki->services->get(ContactApiController::class);
        // no 'email'/'message': check_parameters_mail() must reject before Mailer::send() is ever called
        $response = $controller->sendContactMail(Request::create('/api/contact/mail', 'POST', [
            'pageTag' => self::PUBLIC_PAGE_TAG,
            'type' => 'contact',
            'nbactionmail' => '1',
            'name' => 'Tester',
            'subject' => 'Hello',
        ]));

        $body = json_decode($response->getContent(), true);
        $this->assertSame('danger', $body['type']);
        $this->assertStringContainsString(_t('CONTACT_ENTER_SENDER_MAIL'), $body['message']);
    }

    #[\PHPUnit\Framework\Attributes\Depends('testWikiExisting')]
    public function testReadAclDeniedPreventsSending(YesWikiRuntime $wiki)
    {
        $GLOBALS['yeswikiServices'] = $wiki->services;
        $controller = $wiki->services->get(ContactApiController::class);
        // the plain-contact path resolves its receiver from the page body, which requires
        // read access to that page -- a requester without it must be denied, not attempt to send
        $response = $controller->sendContactMail(Request::create('/api/contact/mail', 'POST', [
            'pageTag' => self::PRIVATE_PAGE_TAG,
            'type' => 'contact',
            'nbactionmail' => '1',
            'name' => 'Tester',
            'email' => 'tester@example.com',
            'subject' => 'Hello',
            'message' => 'This is a long enough test message.',
        ]));

        $body = json_decode($response->getContent(), true);
        $this->assertSame('danger', $body['type']);
        $this->assertStringContainsString(_t('LOGIN_NOT_AUTORIZED'), $body['message']);
    }

    public function testMailerSendIsReachableAndReturnsBoolWithoutThrowing()
    {
        $wiki = $this->getWiki();
        $GLOBALS['yeswikiServices'] = $wiki->services;
        $mailer = $wiki->services->get(Mailer::class);

        // no real SMTP/sendmail transport in this sandbox: this exercises that
        // Mailer::send() (the seam ticket 18 introduces) is reachable and fails
        // gracefully (returns false) rather than throwing, without actually
        // depending on a working mail transport being configured
        $result = $mailer->send('sender@example.com', 'Sender', 'receiver@example.com', 'Subject', 'Body');
        $this->assertIsBool($result);
    }
}
