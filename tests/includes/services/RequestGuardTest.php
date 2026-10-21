<?php

namespace YesWiki\Test\Core\Service;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Request;
use YesWiki\Core\Controller\AuthController;
use YesWiki\Core\Service\RequestGuard;
use YesWiki\Core\Service\UserManager;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

class RequestGuardTest extends YesWikiTestCase
{
    private const MEMBER = 'RequestGuardTestMember';

    private RequestGuard $guard;
    private string $baseUrl;

    protected function setUp(): void
    {
        $wiki = $this->getWiki();
        $this->guard = $wiki->services->get(RequestGuard::class);
        $this->baseUrl = $wiki->config['base_url'];
        $this->guard->token();
        $this->forgetLoggedUser();
        $userManager = $wiki->services->get(UserManager::class);
        if (!$userManager->getOneByName(self::MEMBER)) {
            $userManager->create(self::MEMBER, 'requestguardmember@example.org', 'Un mot de passe 1!');
        }
    }

    protected function tearDown(): void
    {
        $this->forgetLoggedUser();
        $userManager = $this->getWiki()->services->get(UserManager::class);
        if ($user = $userManager->getOneByName(self::MEMBER)) {
            $userManager->delete($user);
        }
    }

    private function forgetLoggedUser(): void
    {
        unset($_SESSION['user']);
        (new \ReflectionProperty(AuthController::class, 'loggedUserCache'))->setValue($this->getWiki()->services->get(AuthController::class), null);
    }

    private function request(string $method, array $headers = [], array $post = [], array $query = []): Request
    {
        $parts = parse_url($this->baseUrl);
        $server = ['REQUEST_METHOD' => $method, 'HTTP_HOST' => $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : ''), 'HTTPS' => ($parts['scheme'] ?? '') === 'https' ? 'on' : 'off'];
        foreach ($headers as $name => $value) {
            $server['HTTP_' . strtoupper(str_replace('-', '_', $name))] = $value;
        }

        return new Request($query, $post, [], [], [], $server);
    }

    private function signIn(): void
    {
        $_SESSION['user'] = ['name' => self::MEMBER];
    }

    private function ownOrigin(): string
    {
        $parts = parse_url($this->baseUrl);

        return $parts['scheme'] . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');
    }

    public function testReadingIsNeverRefused()
    {
        $this->signIn();

        $this->assertNull($this->guard->refusal($this->request('GET', ['Sec-Fetch-Site' => 'cross-site', 'Origin' => 'https://evil.example'])));
        $this->assertNull($this->guard->refusal($this->request('HEAD', ['Sec-Fetch-Site' => 'cross-site'])));
    }

    public static function crossSiteHeaders(): array
    {
        return [
            'fetch metadata cross-site' => [['Sec-Fetch-Site' => 'cross-site']],
            'fetch metadata same-site' => [['Sec-Fetch-Site' => 'same-site']],
            'foreign origin' => [['Origin' => 'https://evil.example']],
            'opaque origin' => [['Origin' => 'null']],
            'foreign referer' => [['Referer' => 'https://evil.example/page']],
            'other subdomain' => [['Origin' => 'https://other.yeswiki.invalid']],
        ];
    }

    #[DataProvider('crossSiteHeaders')]
    public function testAPostFromAnotherSiteIsRefusedEvenAnonymously(array $headers)
    {
        $this->assertSame(_t('CROSS_SITE_REQUEST_REFUSED'), $this->guard->refusal($this->request('POST', $headers)));
    }

    public function testAnAnonymousPostFromTheWikiOrFromNoBrowserGoesThrough()
    {
        $this->assertNull($this->guard->refusal($this->request('POST', ['Sec-Fetch-Site' => 'same-origin'])));
        $this->assertNull($this->guard->refusal($this->request('POST', ['Sec-Fetch-Site' => 'none'])));
        $this->assertNull($this->guard->refusal($this->request('POST', ['Origin' => $this->ownOrigin()])));
        $this->assertNull($this->guard->refusal($this->request('POST')));
    }

    public function testFetchMetadataWinsOverAnOriginThatLooksOwn()
    {
        $this->assertNotNull($this->guard->refusal($this->request('POST', ['Sec-Fetch-Site' => 'cross-site', 'Origin' => $this->ownOrigin()])));
    }

    public function testAnApiKeyCallIsLeftToTheApi()
    {
        $this->signIn();

        $this->assertNull($this->guard->refusal($this->request('POST', ['Authorization' => 'Bearer secret', 'Sec-Fetch-Site' => 'cross-site'])));
    }

    public function testASignedInPostNeedsTheToken()
    {
        $this->signIn();
        $own = ['Sec-Fetch-Site' => 'same-origin'];
        $token = $this->guard->token();

        $this->assertSame(_t('CSRF_TOKEN_FAIL_ERROR'), $this->guard->refusal($this->request('POST', $own)));
        $this->assertSame(_t('CSRF_TOKEN_FAIL_ERROR'), $this->guard->refusal($this->request('POST', $own, [RequestGuard::FIELD => 'wrong'])));
        $this->assertSame(_t('CSRF_TOKEN_FAIL_ERROR'), $this->guard->refusal($this->request('POST', $own, [RequestGuard::FIELD => ['an', 'array']])));
        $this->assertNull($this->guard->refusal($this->request('POST', $own, [RequestGuard::FIELD => $token])));
        $this->assertNull($this->guard->refusal($this->request('POST', $own, ['csrfToken' => $token])));
        $this->assertNull($this->guard->refusal($this->request('DELETE', $own + [RequestGuard::HEADER => $token])));
    }

    public function testASignedLinkCarriesTheTokenInItsUrlOrAHeader()
    {
        $token = $this->guard->token();

        $this->assertFalse($this->guard->linkIsSigned($this->request('GET')));
        $this->assertFalse($this->guard->linkIsSigned($this->request('GET', [], [], [RequestGuard::FIELD => 'wrong'])));
        $this->assertTrue($this->guard->linkIsSigned($this->request('GET', [], [], [RequestGuard::FIELD => $token])));
        $this->assertTrue($this->guard->linkIsSigned($this->request('GET', [RequestGuard::HEADER => $token])));
        $this->assertTrue($this->linkSigned($this->guard->sign('https://w.example/?Page/claim'), 'https://w.example/?Page/claim&'));
        $this->assertTrue($this->linkSigned($this->guard->sign('/page'), '/page?'));
    }

    private function linkSigned(string $signed, string $prefix): bool
    {
        if (!str_starts_with($signed, $prefix . RequestGuard::FIELD . '=')) {
            return false;
        }
        $token = rawurldecode(substr($signed, strlen($prefix . RequestGuard::FIELD . '=')));

        return $this->guard->linkIsSigned($this->request('GET', [], [], [RequestGuard::FIELD => $token]));
    }

    public function testOnlyFormsPostingToTheWikiGetTheToken()
    {
        $html = implode("\n", [
            '<form method="post">A</form>',
            '<form action="?Page/edit" method="POST">B</form>',
            "<form method='post' action='/index.php?Page'>C</form>",
            '<form method="post" action="' . $this->baseUrl . 'Page/edit">D</form>',
            '<form action="?Recherche">E</form>',
            '<form method="post" action="https://evil.example/collect">F</form>',
            '<form method="post" action="//evil.example/collect">G</form>',
            '<form method="post" action="javascript:void(0)">H</form>',
            '<script>const t = \'<form method="post">I</form>\'</script>',
        ]);

        $armed = $this->guard->arm($html, $this->request('GET'));

        $field = '<input type="hidden" name="csrf-token" value="([^"]+)">';
        foreach (['A', 'B', 'C', 'D'] as $form) {
            $this->assertMatchesRegularExpression('#>' . $field . $form . '</form>#', $armed, "form $form");
            preg_match('#>' . $field . $form . '</form>#', $armed, $match);
            $this->assertTrue($this->guard->carriesToken($this->request('POST', [], [RequestGuard::FIELD => html_entity_decode($match[1])])), "form $form");
        }
        foreach (['E', 'F', 'G', 'H', 'I'] as $form) {
            $this->assertStringContainsString('>' . $form . '</form>', $armed, "form $form");
        }
        $this->assertSame(4, preg_match_all('#' . $field . '#', $armed));
    }
}
