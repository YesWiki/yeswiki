<?php

namespace YesWiki\Test\Kernel;

use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\HttpFoundation\Request;
use YesWiki\Kernel\Service\CurrentRequest;
use YesWiki\Kernel\Service\FlashMessages;
use YesWiki\Kernel\Service\FlashMessageService;
use YesWiki\Kernel\Service\SignedTokens;
use YesWiki\Kernel\Service\WikiCookies;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** Replicas 14: what a save says is shown by whichever replica serves the redirect, once, from a cookie rather than a session. */
#[CoversClass(FlashMessages::class)]
#[CoversClass(FlashMessageService::class)]
class FlashMessagesTest extends YesWikiTestCase
{
    private Request $requestWas;

    /** @var array<string, mixed> */
    private array $cookiesWere;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requestWas = self::getWiki()->services->get(CurrentRequest::class)->get();
        $this->cookiesWere = $_COOKIE;
        $_COOKIE = [];
    }

    protected function tearDown(): void
    {
        self::getWiki()->services->get(CurrentRequest::class)->replace($this->requestWas);
        $_COOKIE = $this->cookiesWere;
        parent::tearDown();
    }

    public function testASaveFollowedByARedirectShowsItsMessageOnceOnTheNextReplica(): void
    {
        $this->request()->add('success', 'Enregistré');
        $cookie = $_COOKIE[FlashMessages::COOKIE] ?? null;
        $this->assertIsString($cookie, 'a message not shown yet leaves in the cookie');

        $next = $this->request([FlashMessages::COOKIE => $cookie]);
        $this->assertSame('<div class="alert alert-success" role="alert">Enregistré<br /></div>', $next->display());
        $this->assertArrayNotHasKey(FlashMessages::COOKIE, $_COOKIE, 'and the cookie goes once it is shown');

        $this->assertSame('', $this->request([])->display(), 'a reload shows nothing');
    }

    public function testAMessageShownInItsOwnPageNeverReachesTheCookie(): void
    {
        $messages = $this->request();
        $messages->add('error', 'Refusé');

        $this->assertStringContainsString('Refusé', $messages->display());
        $this->assertArrayNotHasKey(FlashMessages::COOKIE, $_COOKIE);
    }

    public function testTheAlertsAreGroupedByKindAndKeepTheirHtml(): void
    {
        $messages = $this->request();
        $messages->add('error', 'e<b>x</b>');
        $messages->add('success', 'ok');
        $messages->add('error', 'again');

        $this->assertSame(
            '<div class="alert alert-danger" role="alert">e<b>x</b><br />again<br /></div><div class="alert alert-success" role="alert">ok<br /></div>',
            $messages->display(),
            'the markup SimpleFlash wrote, raw where it was raw'
        );
    }

    public function testACookieThisWikiDidNotSignShowsNothing(): void
    {
        $forged = rtrim(strtr(base64_encode((string)json_encode([['error', '<script>alert(1)</script>']])), '+/', '-_'), '=') . '.forged';

        $this->assertSame('', $this->request([FlashMessages::COOKIE => $forged])->display());
    }

    public function testTheToastIsTheOldOneSlotNoticeAndSurvivesTheRedirect(): void
    {
        $messages = $this->request();
        $notice = new FlashMessageService($messages);
        $notice->setMessage('first');
        $notice->setMessage('second');

        $next = new FlashMessageService($this->request([FlashMessages::COOKIE => (string)$_COOKIE[FlashMessages::COOKIE]]));
        $this->assertSame('second', $next->getMessage(), 'the last one wins, as SetMessage() did');
        $this->assertSame('', $next->getMessage());
    }

    public function testOverThreeKilobytesTheOldestGo(): void
    {
        $messages = $this->request();
        foreach (range(1, 40) as $n) {
            $messages->add('info', "message {$n} " . str_repeat('x', 80));
        }

        $shown = $this->request([FlashMessages::COOKIE => (string)$_COOKIE[FlashMessages::COOKIE]])->display();
        $this->assertStringContainsString('message 40 ', $shown);
        $this->assertStringNotContainsString('message 1 ', $shown);
        $this->assertLessThan(4096, strlen((string)($_COOKIE[FlashMessages::COOKIE] ?? '')));
    }

    /** @param array<string, string> $cookies */
    private function request(array $cookies = []): FlashMessages
    {
        $_COOKIE = $cookies;
        $services = self::getWiki()->services;
        $request = $services->get(CurrentRequest::class);
        $request->replace(new Request([], [], [], $cookies));

        return new FlashMessages($services->get(SignedTokens::class), $services->get(WikiCookies::class), $request);
    }
}
