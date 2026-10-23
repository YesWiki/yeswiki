<?php

namespace YesWiki\Test\Bazar\Controller;

use YesWiki\Bazar\Controller\EntryController;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * The entry form's cancel button leaves the form, whatever the tabs did to the browser history.
 */
class EntryCancelUrlTest extends YesWikiTestCase
{
    private $wiki;
    private array $server;

    protected function setUp(): void
    {
        $this->wiki = $this->getWiki();
        $GLOBALS['wiki'] = $this->wiki;
        $this->server = $_SERVER;
        $_SERVER['HTTP_HOST'] = parse_url($this->wiki->config['base_url'], PHP_URL_HOST);
        $_SERVER['HTTPS'] = 'on';
        $_SERVER['REQUEST_URI'] = '/?BazaR&vue=saisir&id=1';
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->server;
        $this->wiki->request->headers->remove('referer');
    }

    private function cancelUrl(string $incomingUrl, ?string $entryId = null): string
    {
        $controller = $this->wiki->services->get(EntryController::class);

        return (new \ReflectionMethod($controller, 'getCancelUrl'))->invoke($controller, $incomingUrl, $entryId);
    }

    public function testTheIncomingUrlComesFirst()
    {
        $this->wiki->request->headers->set('referer', $this->wiki->href('', 'SomeList', null, false));

        $this->assertSame('https://example.org/?Somewhere', $this->cancelUrl('https://example.org/?Somewhere'));
    }

    public function testThePageTheVisitorCameFromIsNext()
    {
        $referer = $this->wiki->href('', 'SomeList', null, false);
        $this->wiki->request->headers->set('referer', $referer);

        $this->assertSame($referer, $this->cancelUrl(''));
    }

    public function testARefererOutsideTheWikiIsIgnored()
    {
        $this->wiki->request->headers->set('referer', 'https://elsewhere.example.org/?Phish');

        $this->assertSame($this->wiki->Href('', 'MyEntry', null, false), $this->cancelUrl('', 'MyEntry'));
    }

    public function testTheFormItselfIsNotAPlaceToGoBackTo()
    {
        $this->wiki->request->headers->set('referer', 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] . '#tab-2');

        $this->assertSame($this->wiki->Href('', 'MyEntry', null, false), $this->cancelUrl('', 'MyEntry'));
    }
}
