<?php

namespace YesWiki\Test\Core;

use YesWiki\Core\Service\AclService;
use YesWiki\Core\Service\DbService;
use YesWiki\Core\Service\PageManager;
use YesWiki\Core\Service\RequestGuard;
use YesWiki\Core\Service\UserManager;

require_once 'tests/YesWikiTestCase.php';

/**
 * Sends forged and genuine requests to a real PHP server and checks which ones the request guard lets through.
 */
class RequestGuardHttpTest extends YesWikiTestCase
{
    private const PORT = 8772;
    private const PAGE = 'RequestGuardTestPage';
    private const MEMBER = 'RequestGuardHttpMember';
    private const MEMBER_PASSWORD = 'Un mot de passe 1!';

    private static $server;
    private string $cookieJar;

    public static function setUpBeforeClass(): void
    {
        $wiki = self::getWiki();
        $GLOBALS['wiki'] = $wiki;
        $wiki->services->get(PageManager::class)->save(self::PAGE, 'Texte de départ', '', true);
        $wiki->services->get(AclService::class)->save(self::PAGE, 'write', '*');
        $wiki->services->get(AclService::class)->save(self::PAGE, 'read', '*');
        $wiki->services->get(UserManager::class)->create(self::MEMBER, 'requestguardhttp@example.org', self::MEMBER_PASSWORD);
        self::$server = proc_open(
            ['php', '-S', '127.0.0.1:' . self::PORT],
            [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
            $pipes,
            getcwd()
        );
        for ($i = 0; $i < 50 && !@fsockopen('127.0.0.1', self::PORT); $i++) {
            usleep(100000);
        }
    }

    public static function tearDownAfterClass(): void
    {
        proc_terminate(self::$server);
        $wiki = self::getWiki();
        $wiki->services->get(PageManager::class)->deleteOrphaned(self::PAGE);
        $db = $wiki->services->get(DbService::class);
        $db->query('DELETE FROM' . $db->prefixTable('users') . "WHERE name = '" . self::MEMBER . "'");
    }

    protected function setUp(): void
    {
        $this->cookieJar = tempnam(sys_get_temp_dir(), 'requestguard');
    }

    protected function tearDown(): void
    {
        @unlink($this->cookieJar);
    }

    private function request(string $query, ?array $post = null, array $headers = []): array
    {
        $curl = curl_init('http://127.0.0.1:' . self::PORT . '/?' . $query);
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_COOKIEJAR => $this->cookieJar,
            CURLOPT_COOKIEFILE => $this->cookieJar,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 60,
        ]);
        if ($post !== null) {
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($post));
        }
        $body = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);

        return [$status, (string)$body];
    }

    private function signIn(): void
    {
        [$status] = $this->request('api/login', ['username' => self::MEMBER, 'password' => self::MEMBER_PASSWORD]);
        $this->assertSame(200, $status, 'the member signs in');
    }

    public function testAnotherSiteCannotSignSomeoneIn()
    {
        [$status, $body] = $this->request('api/login', ['username' => self::MEMBER, 'password' => self::MEMBER_PASSWORD], ['Origin: https://evil.example']);

        $this->assertSame(403, $status);
        $this->assertSame(_t('CROSS_SITE_REQUEST_REFUSED'), json_decode($body, true)['error'] ?? null);
    }

    public function testAnotherSiteCannotPostAsTheSignedInMember()
    {
        $this->signIn();

        [$status] = $this->request(self::PAGE . '/edit', ['body' => 'Forgé', 'submit' => 'Sauver'], ['Sec-Fetch-Site: cross-site']);

        $this->assertSame(403, $status);
        $this->assertStringNotContainsString('Forgé', $this->request(self::PAGE . '/raw')[1]);
    }

    public function testAPostWithoutTheTokenIsRefusedOnceSignedIn()
    {
        $this->signIn();

        [$status] = $this->request(self::PAGE . '/edit', ['body' => 'Sans jeton', 'submit' => 'Sauver']);
        [$apiStatus] = $this->request('api/triples/' . self::PAGE, ['property' => 'x', 'value' => 'y']);

        $this->assertSame(403, $status);
        $this->assertSame(403, $apiStatus);
        $this->assertStringNotContainsString('Sans jeton', $this->request(self::PAGE . '/raw')[1]);
    }

    public function testTheWikiOwnFormsAndScriptsCarryTheToken()
    {
        $this->signIn();

        [, $html] = $this->request(self::PAGE . '/edit');
        $this->assertMatchesRegularExpression('/<form[^>]*id="ACEditor"[^>]*>\s*<input type="hidden" name="' . RequestGuard::FIELD . '" value="[^"]+">/s', $html);
        $this->assertStringContainsString('javascripts/csrf-guard.js', $html);
        $this->assertSame(1, preg_match('/"antiCsrfToken":"([^"]+)"/', $html, $token));

        [$status] = $this->request('api/triples/' . self::PAGE, ['property' => 'x', 'value' => 'y'], [RequestGuard::HEADER . ': ' . json_decode('"' . $token[1] . '"')]);
        $this->assertNotSame(403, $status);
    }
}
