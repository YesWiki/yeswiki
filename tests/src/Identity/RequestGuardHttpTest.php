<?php

namespace YesWiki\Test\Identity;

use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Service\PageManager;
use YesWiki\Identity\Service\AclService;
use YesWiki\Identity\Service\RequestGuard;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\RuntimeConfig;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** Forged and genuine requests sent to the dev wiki, and which ones the request guard lets through. */
class RequestGuardHttpTest extends YesWikiTestCase
{
    private const PAGE = 'RequestGuardHttpPage';
    private const MEMBER = 'RequestGuardHttpMember';
    private const MEMBER_PASSWORD = 'Un mot de passe 1!';

    private static string $baseUrl = '';
    private string $cookieJar = '';

    public static function setUpBeforeClass(): void
    {
        $services = self::getWiki()->services;
        self::$baseUrl = (string)$services->get(RuntimeConfig::class)['base_url'];
        $services->get(PageManager::class)->save(self::PAGE, [PageBody::CONTENT => 'Texte de départ'], '', true);
        $services->get(AclService::class)->save(self::PAGE, 'read', '*');
        $services->get(AclService::class)->save(self::PAGE, 'write', '*');
        $userManager = $services->get(UserManager::class);
        if (!$userManager->getOneByName(self::MEMBER)) {
            $userManager->create(self::MEMBER, 'requestguardhttp@example.org', self::MEMBER_PASSWORD);
        }
    }

    public static function tearDownAfterClass(): void
    {
        $services = self::getWiki()->services;
        $services->get(PageManager::class)->deleteOrphaned(self::PAGE);
        $services->get(AclService::class)->delete(self::PAGE);
        $userManager = $services->get(UserManager::class);
        if ($user = $userManager->getOneByName(self::MEMBER)) {
            $userManager->delete($user);
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->cookieJar = (string)tempnam(sys_get_temp_dir(), 'requestguard-cookies');
        [$status] = $this->request('PagePrincipale');
        if ($status === 0) {
            $this->markTestSkipped('the dev wiki at ' . self::$baseUrl . ' is not being served');
        }
    }

    protected function tearDown(): void
    {
        @unlink($this->cookieJar);
        parent::tearDown();
    }

    /**
     * @param array<string, string>|null $post
     * @param list<string>               $headers
     *
     * @return array{int, string}
     */
    private function request(string $path, ?array $post = null, array $headers = []): array
    {
        $cookieJar = $this->cookieJar;
        if ($cookieJar === '') {
            $this->fail('setUp made no cookie jar');
        }
        $curl = curl_init(self::$baseUrl . $path);
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_COOKIEJAR => $cookieJar,
            CURLOPT_COOKIEFILE => $cookieJar,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_NOPROXY => '*',
            CURLOPT_FOLLOWLOCATION => false,
        ]);
        if ($post !== null) {
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($post));
        }
        $body = curl_exec($curl);

        return [(int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE), (string)$body];
    }

    private function signIn(): void
    {
        [$status] = $this->request('api/login', ['username' => self::MEMBER, 'password' => self::MEMBER_PASSWORD]);
        $this->assertSame(200, $status, 'the member signs in');
    }

    private function pageContent(): string
    {
        $db = self::getWiki()->services->get(DbService::class);
        $row = $db->loadSingle('SELECT body FROM ' . $db->prefixTable('pages') . " WHERE tag = ? AND latest = 'Y'", [self::PAGE]);

        return $row === null ? '' : PageBody::content(PageBody::decode((string)$row['body']));
    }

    public function testAnotherSiteCannotSignSomeoneIn(): void
    {
        [$status, $body] = $this->request('api/login', ['username' => self::MEMBER, 'password' => self::MEMBER_PASSWORD], ['Origin: https://evil.example']);

        $this->assertSame(403, $status);
        $this->assertSame(_t('CROSS_SITE_REQUEST_REFUSED'), json_decode($body, true)['error'] ?? null);
    }

    public function testAnotherSiteCannotPostAsTheSignedInMember(): void
    {
        $this->signIn();

        [$status] = $this->request(self::PAGE . '/edit', ['body' => 'Forgé', 'submit' => 'Sauver'], ['Sec-Fetch-Site: cross-site']);

        $this->assertSame(403, $status);
        $this->assertStringNotContainsString('Forgé', $this->pageContent());
    }

    public function testAPostWithoutTheTokenIsRefusedOnceSignedIn(): void
    {
        $this->signIn();

        [$status] = $this->request(self::PAGE . '/edit', ['body' => 'Sans jeton', 'submit' => 'Sauver']);
        [$apiStatus] = $this->request('api/triples/' . self::PAGE, ['property' => 'x', 'value' => 'y']);

        $this->assertSame(403, $status);
        $this->assertSame(403, $apiStatus);
        $this->assertStringNotContainsString('Sans jeton', $this->pageContent());
    }

    public function testTheWikiOwnFormsAndScriptsCarryTheToken(): void
    {
        $this->signIn();

        [, $html] = $this->request(self::PAGE . '/edit');
        $this->assertMatchesRegularExpression('/<form[^>]*id="ACEditor"[^>]*>\s*<input type="hidden" name="' . RequestGuard::FIELD . '" value="[^"]+">/s', $html);
        $this->assertStringContainsString(RequestGuard::SCRIPT, $html);
        $this->assertSame(1, preg_match('/"antiCsrfToken":"([^"]+)"/', $html, $token));

        [$status] = $this->request('api/triples/' . self::PAGE, ['property' => 'x', 'value' => 'y'], [RequestGuard::HEADER . ': ' . json_decode('"' . ($token[1] ?? '') . '"')]);
        $this->assertNotSame(403, $status);
    }

    public function testSigningOutTakesASignedLink(): void
    {
        $this->signIn();
        [, $html] = $this->request(self::PAGE . '/edit');
        $this->assertSame(1, preg_match('/"antiCsrfToken":"([^"]+)"/', $html, $token));

        $this->request('user/logout');
        $this->assertSame(200, $this->request('api/auth/me')[0], 'an unsigned link leaves the member signed in');

        $this->request('user/logout&' . RequestGuard::FIELD . '=' . rawurlencode(json_decode('"' . ($token[1] ?? '') . '"')));
        $this->assertSame(401, $this->request('api/auth/me')[0], 'a signed link signs out');
    }
}
