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
 * Regression test for ticket 18 (contact absorbed into core): contact-form submission moves to POST /api/contact/mail, routed through Mailer::send() instead of a direct send_mail() call.
 */
#[CoversMethod(ContactApiController::class, 'sendContactMail')]
class ApiControllerContactTest extends YesWikiTestCase
{
    private const PRIVATE_PAGE_TAG = 'ApiControllerContactTestPrivatePage';
    private const PUBLIC_PAGE_TAG = 'ApiControllerContactTestPublicPage';

    /** The fixtures go when the tests do. */
    public static function tearDownAfterClass(): void
    {
        $pageManager = self::getWiki()->services->get(PageManager::class);
        foreach ([self::PRIVATE_PAGE_TAG, self::PUBLIC_PAGE_TAG] as $tag) {
            $pageManager->deleteOrphaned($tag);
        }
    }

    protected function tearDown(): void
    {
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
    public function testMissingPageTagIsRejected(YesWikiRuntime $wiki): void
    {
        $controller = $wiki->services->get(ContactApiController::class);
        $response = $controller->sendContactMail(Request::create('/api/contact/mail', 'POST', []));

        $this->assertSame(400, $response->getStatusCode());
    }

    #[\PHPUnit\Framework\Attributes\Depends('testWikiExisting')]
    public function testValidationFailureDoesNotAttemptToSend(YesWikiRuntime $wiki): void
    {
        $GLOBALS['yeswikiServices'] = $wiki->services;
        $controller = $wiki->services->get(ContactApiController::class);

        $response = $controller->sendContactMail(Request::create('/api/contact/mail', 'POST', [
            'pageTag' => self::PUBLIC_PAGE_TAG,
            'type' => 'contact',
            'nbactionmail' => '1',
            'name' => 'Tester',
            'subject' => 'Hello',
        ]));

        $content = $response->getContent();
        $this->assertIsString($content);
        $body = json_decode($content, true);
        $this->assertSame('danger', $body['type']);
        $this->assertStringContainsString(_t('CONTACT_ENTER_SENDER_MAIL'), $body['message']);
    }

    #[\PHPUnit\Framework\Attributes\Depends('testWikiExisting')]
    public function testReadAclDeniedPreventsSending(YesWikiRuntime $wiki): void
    {
        $GLOBALS['yeswikiServices'] = $wiki->services;
        $controller = $wiki->services->get(ContactApiController::class);

        $response = $controller->sendContactMail(Request::create('/api/contact/mail', 'POST', [
            'pageTag' => self::PRIVATE_PAGE_TAG,
            'type' => 'contact',
            'nbactionmail' => '1',
            'name' => 'Tester',
            'email' => 'tester@example.com',
            'subject' => 'Hello',
            'message' => 'This is a long enough test message.',
        ]));

        $content = $response->getContent();
        $this->assertIsString($content);
        $body = json_decode($content, true);
        $this->assertSame('danger', $body['type']);
        $this->assertStringContainsString(_t('LOGIN_NOT_AUTORIZED'), $body['message']);
    }

    public function testMailerSendIsReachableAndReturnsBoolWithoutThrowing(): void
    {
        $wiki = $this->getWiki();
        $GLOBALS['yeswikiServices'] = $wiki->services;
        $mailer = $wiki->services->get(Mailer::class);

        $threw = false;
        try {
            $mailer->send('sender@example.com', 'Sender', 'receiver@example.com', 'Subject', 'Body');
        } catch (\Throwable $e) {
            $threw = true;
        }

        $this->assertFalse($threw, 'Mailer::send() must report a transport failure by returning false, not by throwing');
    }
}
