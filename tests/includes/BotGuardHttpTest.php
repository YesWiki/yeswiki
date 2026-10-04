<?php

namespace YesWiki\Test\Core;

use AltchaOrg\Altcha\Algorithm\Pbkdf2;
use AltchaOrg\Altcha\Altcha;
use AltchaOrg\Altcha\Challenge;
use AltchaOrg\Altcha\Payload;
use AltchaOrg\Altcha\SolveChallengeOptions;
use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Bazar\Service\FormManager;
use YesWiki\Core\Service\AclService;
use YesWiki\Core\Service\BotGuard;
use YesWiki\Core\Service\DbService;
use YesWiki\Core\Service\PageManager;
use YesWiki\Core\Service\UserManager;

require_once 'tests/YesWikiTestCase.php';

/**
 * Sends anonymous submissions to every guarded entry point through a real PHP server.
 */
class BotGuardHttpTest extends YesWikiTestCase
{
    private const PORT = 8771;
    private const PAGES = [
        'BotGuardTestEdit' => 'Texte de départ',
        'BotGuardTestBazar' => '{{bazar vue="saisir" id="{form}" voirmenu="0"}}',
        'BotGuardTestContact' => '{{contact mail="nobody@example.org"}}',
        'BotGuardTestSubscribe' => '{{abonnement mail="list@example.org"}}',
        'BotGuardTestSignup' => '{{usersettings}}',
        'BotGuardTestLostPassword' => '{{lostpassword}}',
        'BotGuardTestComments' => 'Une page à commenter',
        'BotGuardTestMailForm' => '{{bazar vue="saisir" id="{mailForm}" voirmenu="0"}}',
    ];
    private const MEMBER = 'BotGuardTestMember';
    private const MEMBER_PASSWORD = 'Un mot de passe 1!';

    private static $server;
    private static string $formId;
    private static string $mailFormId;
    private static int $maxTripleId;
    private string $cookieJar;

    public static function setUpBeforeClass(): void
    {
        $wiki = self::getWiki();
        self::assertFileExists(BotGuard::ALTCHA_SCRIPT, 'the ALTCHA script is vendored by `yarn install`: run it before the tests');
        $GLOBALS['wiki'] = $wiki;
        $pageManager = $wiki->services->get(PageManager::class);
        $aclService = $wiki->services->get(AclService::class);
        $db = $wiki->services->get(DbService::class);
        self::$formId = $wiki->services->get(FormManager::class)->create([
            'bn_label_nature' => 'BotGuard test form',
            'bn_template' => 'texte***bf_titre***Titre***60***255*** *** ***text***1*** *** *** * *** * *** *** *** ***',
            'bn_condition' => '',
        ]);
        self::$mailFormId = $wiki->services->get(FormManager::class)->create([
            'bn_label_nature' => 'BotGuard mail form',
            'bn_template' => "texte***bf_titre***Titre***60***255*** *** ***text***1*** *** *** * *** * *** *** *** ***\nchamps_mail***bf_mail***Email*** *** *** *** *** *** ***1*** *** *** * *** * *** *** *** ***",
            'bn_condition' => '',
        ]);
        self::$maxTripleId = (int)($db->loadSingle('SELECT MAX(id) AS id FROM' . $db->prefixTable('triples'))['id'] ?? 0);
        foreach (self::PAGES as $tag => $body) {
            $pageManager->save($tag, strtr($body, ['{form}' => self::$formId, '{mailForm}' => self::$mailFormId]), '', true);
            $aclService->save($tag, 'write', '*');
            $aclService->save($tag, 'read', '*');
        }
        $aclService->save('BotGuardTestComments', 'comment', '+');
        $wiki->services->get(UserManager::class)->create(self::MEMBER, 'botguardmember@example.org', self::MEMBER_PASSWORD);
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
        $db = $wiki->services->get(DbService::class);
        foreach (array_keys(self::PAGES) as $tag) {
            $wiki->services->get(PageManager::class)->deleteOrphaned($tag);
        }
        $entryManager = $wiki->services->get(EntryManager::class);
        foreach ($entryManager->search(['formsIds' => [self::$formId]]) as $entry) {
            $entryManager->delete($entry['id_fiche'], true);
        }
        $wiki->services->get(FormManager::class)->delete(self::$formId);
        $wiki->services->get(FormManager::class)->delete(self::$mailFormId);
        $db->query('DELETE FROM' . $db->prefixTable('pages') . "WHERE comment_on = 'BotGuardTestComments'");
        $db->query('DELETE FROM' . $db->prefixTable('users') . "WHERE name LIKE 'BotGuardTest%'");
        $db->query('DELETE FROM' . $db->prefixTable('triples') . 'WHERE id > ' . self::$maxTripleId . " AND resource LIKE 'botGuard:%'");
    }

    protected function setUp(): void
    {
        $this->cookieJar = tempnam(sys_get_temp_dir(), 'botguard-cookies');
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

    /**
     * The fields of the first form matching the XPath, as the browser would send them.
     */
    private function formFields(string $html, string $formXPath): array
    {
        $doc = new \DOMDocument();
        @$doc->loadHTML('<?xml encoding="utf-8"?>' . $html);
        $xpath = new \DOMXPath($doc);
        $form = $xpath->query($formXPath)->item(0);
        $this->assertNotNull($form, "no form matching $formXPath");
        $fields = [];
        foreach ($xpath->query('.//input|.//textarea|.//select', $form) as $input) {
            $name = $input->getAttribute('name');
            $type = strtolower($input->getAttribute('type'));
            if ($name === '' || in_array($type, ['submit', 'button', 'file'], true) || (in_array($type, ['checkbox', 'radio'], true) && !$input->hasAttribute('checked'))) {
                continue;
            }
            $fields[$name] = $input->nodeName === 'textarea' ? $input->textContent : $input->getAttribute('value');
            if ($input->hasAttribute('required') && $fields[$name] === '') {
                $fields[$name] = match ($type) {
                    'email' => 'robot@example.org',
                    'url' => 'https://example.org',
                    'date' => '2030-01-01',
                    'number' => '1',
                    default => 'x',
                };
            }
        }
        foreach ($xpath->query('.//altcha-widget', $form) as $widget) {
            $this->assertStringContainsString(BotGuard::ALTCHA_SCRIPT, $html, 'a page showing the ALTCHA widget must load its script');
            $challenge = Challenge::fromArray(json_decode($widget->getAttribute('challenge'), true));
            $solution = (new Altcha('unused'))->solveChallenge(new SolveChallengeOptions(algorithm: new Pbkdf2(), challenge: $challenge));
            $fields[$widget->getAttribute('name')] = (new Payload($challenge, $solution))->toBase64();
        }

        return $fields;
    }

    private function withoutGuard(array $fields): array
    {
        return array_filter($fields, fn ($name) => !preg_match('/^yw[0-9a-f]{10}$/', $name), ARRAY_FILTER_USE_KEY);
    }

    private function assertRefused(string $html, string $message = ''): void
    {
        $this->assertTrue($this->isRefused($html), $message ?: 'the submission should have been refused');
    }

    private function assertNotRefused(string $html, string $message = ''): void
    {
        $this->assertFalse($this->isRefused($html), $message ?: 'the submission should have gone through');
    }

    private function isRefused(string $html): bool
    {
        return str_contains(html_entity_decode($html, ENT_QUOTES), _t('BOT_GUARD_REFUSED'));
    }

    private function pageBody(string $tag): string
    {
        $db = self::getWiki()->services->get(DbService::class);

        $result = $db->query('SELECT body FROM' . $db->prefixTable('pages') . "WHERE tag = '" . $db->escape($tag) . "' AND latest = 'Y'");

        return mysqli_fetch_assoc($result)['body'] ?? '';
    }

    private function entryTitles(): array
    {
        $db = self::getWiki()->services->get(DbService::class);
        $result = $db->query('SELECT body FROM' . $db->prefixTable('pages') . "WHERE latest = 'Y' AND body LIKE '%BotGuard test%'");
        $titles = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $titles[] = json_decode($row['body'], true)['bf_titre'] ?? null;
        }

        return $titles;
    }

    public function testPageEdit()
    {
        [, $html] = $this->request('BotGuardTestEdit/edit');
        $this->assertSame(1, substr_count($html, 'class="yw-bot-guard-fields"'));
        $fields = $this->formFields($html, '//form[@id="ACEditor"]');
        $fields['submit'] = 'Sauver';

        $this->assertStringNotContainsString('<altcha-widget', preg_replace('/<form[^>]*id="ACEditor".*?<\/form>/s', '', $html), 'the guard must not leak into the other forms of the page');

        $this->request('BotGuardTestEdit/edit', ['body' => 'robot'] + $this->withoutGuard($fields));
        $this->assertSame('Texte de départ', $this->pageBody('BotGuardTestEdit'));

        sleep(BotGuard::MIN_AGE + 1);
        $this->request('BotGuardTestEdit/edit', ['body' => 'humain'] + $fields);
        $this->assertSame('humain', $this->pageBody('BotGuardTestEdit'));
    }

    public function testBazarEntry()
    {
        [, $html] = $this->request('BotGuardTestBazar');
        $this->assertSame(1, substr_count($html, 'class="yw-bot-guard-fields"'), 'only the entry form carries the guard');
        $fields = $this->formFields($html, '//form[@id="bazar-form-' . self::$formId . '"]');
        $titleField = array_key_exists('bf_titre', $fields) ? 'bf_titre' : array_key_first($fields);

        [, $refused] = $this->request('BotGuardTestBazar', [$titleField => 'BotGuard test robot'] + $this->withoutGuard($fields));
        $this->assertRefused($refused);
        $this->assertStringContainsString('BotGuard test robot', $refused, 'the visitor keeps what was typed');

        sleep(BotGuard::MIN_AGE + 1);
        $this->request('BotGuardTestBazar', [$titleField => 'BotGuard test human'] + $fields);
        $titles = $this->entryTitles();
        $this->assertContains('BotGuard test human', $titles);
        $this->assertNotContains('BotGuard test robot', $titles);
    }

    public function testContactAndSubscribe()
    {
        $ajax = ['X-Requested-With: XMLHttpRequest'];
        foreach (['BotGuardTestContact', 'BotGuardTestSubscribe'] as $tag) {
            [, $html] = $this->request($tag);
            $this->assertSame(1, substr_count($html, 'class="yw-bot-guard-fields"'), $tag);
            $fields = ['message' => 'Un message assez long pour passer.'] + $this->formFields($html, '//form[contains(@class,"ajax-mail-form")]');

            [, $refused] = $this->request($tag . '/mail', $this->withoutGuard($fields), $ajax);
            $this->assertRefused($refused, $tag);
            $this->assertStringContainsString('yw-bot-guard-fields', $refused, 'fresh fields for the next message');

            sleep(BotGuard::MIN_AGE + 1);
            [, $answer] = $this->request($tag . '/mail', $fields, $ajax);
            $this->assertNotRefused($answer, $tag);
            $reachedTheMailer = array_filter(
                ['CONTACT_MESSAGE_SUCCESSFULLY_SENT', 'CONTACT_SUBSCRIBE_ORDER_SENT', 'CONTACT_MESSAGE_NOT_SENT'],
                fn ($key) => str_contains(html_entity_decode($answer, ENT_QUOTES), _t($key))
            );
            $this->assertNotEmpty($reachedTheMailer, "$tag: the submission should reach the mailer, got: " . strip_tags($answer));
        }
    }

    public function testSignup()
    {
        [, $html] = $this->request('BotGuardTestSignup');
        $this->assertSame(1, substr_count($html, 'class="yw-bot-guard-fields"'));
        $fields = $this->formFields($html, '//form[.//input[@name="confpassword"]]');
        $fields = ['email' => 'botguardtest@example.org', 'password' => 'Un mot de passe 1!', 'confpassword' => 'Un mot de passe 1!'] + $fields;

        $this->request('BotGuardTestSignup', ['name' => 'BotGuardTestRobot'] + $this->withoutGuard($fields));
        $this->assertEmpty(self::getWiki()->services->get(UserManager::class)->getOneByName('BotGuardTestRobot'));

        sleep(BotGuard::MIN_AGE + 1);
        $this->request('BotGuardTestSignup', ['name' => 'BotGuardTestHuman'] + $fields);
        $this->assertNotEmpty(self::getWiki()->services->get(UserManager::class)->getOneByName('BotGuardTestHuman'));
    }

    public function testLostPassword()
    {
        [, $html] = $this->request('BotGuardTestLostPassword');
        $this->assertSame(1, substr_count($html, 'class="yw-bot-guard-fields"'));
        $fields = ['email' => 'nobody@example.org'] + $this->formFields($html, '//form[.//input[@name="subStep"]]');

        [, $refused] = $this->request('BotGuardTestLostPassword', $this->withoutGuard($fields));
        $this->assertRefused($refused);

        sleep(BotGuard::MIN_AGE + 1);
        [, $answer] = $this->request('BotGuardTestLostPassword', $fields);
        $this->assertNotRefused($answer);
        $this->assertStringContainsString(htmlspecialchars(_t('LOGIN_MESSAGE_SENT'), ENT_QUOTES), $answer);
    }

    public function testApiPutAndPatchRefuseAnonymousWrites()
    {
        $entryManager = self::getWiki()->services->get(EntryManager::class);
        $entry = $entryManager->create(self::$formId, ['bf_titre' => 'BotGuard test api']);

        foreach (['api_put', 'api_patch'] as $handler) {
            [$status] = $this->request($entry['id_fiche'] . '/' . $handler, ['bf_titre' => 'BotGuard test robot']);
            $this->assertSame(401, $status, $handler);
        }
        $this->assertSame($entry['bf_titre'], $entryManager->getOne($entry['id_fiche'], false, null, false, true)['bf_titre']);
    }

    public function testALoggedInMemberCommentsWithAltchaOnly()
    {
        [$status] = $this->request('api/login', ['username' => self::MEMBER, 'password' => self::MEMBER_PASSWORD]);
        $this->assertSame(200, $status);

        [, $html] = $this->request('BotGuardTestComments');
        $this->assertSame(1, substr_count($html, 'class="yw-bot-guard-fields"'));
        $fields = $this->formFields($html, '//form[@id="post-comment"]');
        $this->assertArrayHasKey(BotGuard::LOGGED_IN_ALTCHA_FIELD, $fields, 'the comment form carries an ALTCHA challenge');
        $this->assertSame([], array_filter(array_keys($fields), fn ($name) => preg_match('/^yw[0-9a-f]{10}$/', $name)), 'no token for a logged-in member');

        $without = $fields;
        unset($without[BotGuard::LOGGED_IN_ALTCHA_FIELD]);
        [$status, $refused] = $this->request('api/comments', ['body' => 'Commentaire de robot'] + $without);
        $this->assertSame(400, $status);
        $this->assertRefused(json_decode($refused, true)['error'] ?? '');
        $this->assertStringContainsString('altcha-widget', json_decode($refused, true)['botGuard'] ?? '', 'a fresh challenge comes back');

        [$status, $saved] = $this->request('api/comments', ['body' => 'Commentaire humain'] + $fields);
        $this->assertSame(200, $status, $saved);

        [$status] = $this->request('api/comments', ['body' => 'Le même défi rejoué'] + $fields);
        $this->assertSame(400, $status);
    }

    public function testALoggedInMemberSendsMailOnlyWithTheFullGuard()
    {
        $this->request('api/login', ['username' => self::MEMBER, 'password' => self::MEMBER_PASSWORD]);
        $ajax = ['X-Requested-With: XMLHttpRequest'];

        [, $html] = $this->request('BotGuardTestContact');
        $fields = ['message' => 'Un message assez long pour passer.'] + $this->formFields($html, '//form[contains(@class,"ajax-mail-form")]');
        $this->assertNotEmpty(array_filter(array_keys($fields), fn ($name) => preg_match('/^yw[0-9a-f]{10}$/', $name)), 'a token for a logged-in member too');

        [, $refused] = $this->request('BotGuardTestContact/mail', $this->withoutGuard($fields), $ajax);
        $this->assertRefused($refused);

        sleep(BotGuard::MIN_AGE + 1);
        [, $answer] = $this->request('BotGuardTestContact/mail', $fields, $ajax);
        $this->assertNotRefused($answer);
    }

    public function testAnEntryFormThatSendsMailAsksALoggedInMemberForEverything()
    {
        $this->request('api/login', ['username' => self::MEMBER, 'password' => self::MEMBER_PASSWORD]);

        [, $mailForm] = $this->request('BotGuardTestMailForm');
        [, $plainForm] = $this->request('BotGuardTestBazar');

        $this->assertMatchesRegularExpression('/name="yw[0-9a-f]{10}" value="\d+\./', $mailForm, 'a token where saving sends mail');
        $this->assertDoesNotMatchRegularExpression('/name="yw[0-9a-f]{10}" value="\d+\./', $plainForm, 'ALTCHA alone elsewhere');
        $this->assertStringContainsString('name="' . BotGuard::LOGGED_IN_ALTCHA_FIELD . '"', $plainForm);
    }
}
