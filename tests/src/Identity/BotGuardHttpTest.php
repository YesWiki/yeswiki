<?php

namespace YesWiki\Test\Identity;

use AltchaOrg\Altcha\Algorithm\Pbkdf2;
use AltchaOrg\Altcha\Altcha;
use AltchaOrg\Altcha\Challenge;
use AltchaOrg\Altcha\Payload;
use AltchaOrg\Altcha\SolveChallengeOptions;
use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Service\EntryManager;
use YesWiki\Content\Service\FormManager;
use YesWiki\Content\Service\PageManager;
use YesWiki\Identity\Service\AclService;
use YesWiki\Identity\Service\BotGuard;
use YesWiki\Identity\Service\InputFilter;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\RuntimeConfig;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** Submissions to every guarded form of the dev wiki, over HTTP. */
class BotGuardHttpTest extends YesWikiTestCase
{
    private const FORM_ID = '999975';
    private const PAGES = [
        'BotGuardHttpEdit' => 'Texte de départ',
        'BotGuardHttpBazar' => '{{bazar view="saisir" id="' . self::FORM_ID . '"}}',
        'BotGuardHttpContact' => '{{contact mail="nobody@example.org"}}',
        'BotGuardHttpSubscribe' => '{{subscribe mail="list@example.org"}}',
        'BotGuardHttpComments' => 'Une page à commenter',
    ];
    private const MEMBER = 'BotGuardHttpMember';
    private const MEMBER_PASSWORD = 'Un mot de passe 1!';

    private static string $baseUrl = '';
    private static int $maxTripleId = 0;
    private string $cookieJar = '';

    public static function setUpBeforeClass(): void
    {
        $wiki = self::getWiki();
        $services = $wiki->services;
        self::$baseUrl = (string)$services->get(RuntimeConfig::class)['base_url'];
        $db = $services->get(DbService::class);
        self::$maxTripleId = (int)($db->loadSingle('SELECT MAX(id) AS id FROM ' . $db->prefixTable('triples'))['id'] ?? 0);
        $services->get(FormManager::class)->create([
            'id' => self::FORM_ID,
            'label' => 'BotGuard http form',
            'entry_title_template' => '{{bf_titre}}',
            'template' => 'texte***bf_titre***Titre***60***255*** *** ***text***1*** *** *** * *** * *** *** *** ***',
        ]);
        $pageManager = $services->get(PageManager::class);
        $aclService = $services->get(AclService::class);
        foreach (self::PAGES as $tag => $content) {
            $pageManager->save($tag, [PageBody::CONTENT => $content], '', true);
            $aclService->save($tag, 'read', '*');
            $aclService->save($tag, 'write', '*');
        }
        $aclService->save('BotGuardHttpComments', 'comment', '+');
        $userManager = $services->get(UserManager::class);
        if (!$userManager->getOneByName(self::MEMBER)) {
            $userManager->create(self::MEMBER, 'botguardhttpmember@example.com', self::MEMBER_PASSWORD);
        }
    }

    public static function tearDownAfterClass(): void
    {
        $services = self::getWiki()->services;
        $pageManager = $services->get(PageManager::class);
        $db = $services->get(DbService::class);
        foreach (array_keys(self::PAGES) as $tag) {
            $pageManager->deleteOrphaned($tag);
            $services->get(AclService::class)->delete($tag);
        }
        foreach ($db->loadAll('SELECT DISTINCT tag FROM ' . $db->prefixTable('pages') . ' WHERE parent = ?', ['BotGuardHttpComments']) as $row) {
            $pageManager->deleteOrphaned((string)$row['tag']);
        }
        $entryManager = $services->get(EntryManager::class);
        foreach (['botguard-http-robot', 'botguard-http-human'] as $tag) {
            if ($entryManager->isEntry($tag)) {
                $entryManager->delete($tag, true);
            }
        }
        $services->get(FormManager::class)->delete(self::FORM_ID);
        $userManager = $services->get(UserManager::class);
        foreach (['BotGuardHttpMember', 'BotGuardHttpRobot', 'BotGuardHttpHuman'] as $name) {
            if ($user = $userManager->getOneByName($name)) {
                $userManager->delete($user);
            }
        }
        $db->query('DELETE FROM ' . $db->prefixTable('triples') . ' WHERE id > ? AND resource LIKE ?', [self::$maxTripleId, BotGuard::RESOURCE_PREFIX . 'token:%']);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->cookieJar = (string)tempnam(sys_get_temp_dir(), 'botguard-cookies');
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
        $url = str_starts_with($path, 'http') ? $path : self::$baseUrl . $path;
        $curl = curl_init($url);
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

    /**
     * The fields and action of the first form matching the XPath.
     *
     * @return array{array<string, string>, string}
     */
    private function form(string $html, string $formXPath): array
    {
        $doc = new \DOMDocument();
        @$doc->loadHTML('<?xml encoding="utf-8"?>' . $html);
        $xpath = new \DOMXPath($doc);
        $form = $this->elements($xpath, $formXPath)[0] ?? null;
        $this->assertInstanceOf(\DOMElement::class, $form, "no form matching $formXPath");
        $fields = [];
        foreach ($this->elements($xpath, './/input|.//textarea|.//select', $form) as $input) {
            $name = $input->getAttribute('name');
            $type = strtolower($input->getAttribute('type'));
            if ($name === '' || in_array($type, ['submit', 'button', 'file'], true) || (in_array($type, ['checkbox', 'radio'], true) && !$input->hasAttribute('checked'))) {
                continue;
            }
            $fields[$name] = $input->nodeName === 'textarea' ? $input->textContent : $input->getAttribute('value');
        }
        foreach ($this->elements($xpath, './/altcha-widget', $form) as $widget) {
            $this->assertStringContainsString(BotGuard::ALTCHA_SCRIPT, $html, 'a page showing the ALTCHA widget loads its script');
            $challenge = Challenge::fromArray(json_decode($widget->getAttribute('challenge'), true));
            $solution = (new Altcha('unused'))->solveChallenge(new SolveChallengeOptions(algorithm: new Pbkdf2(), challenge: $challenge));
            $this->assertNotNull($solution);
            $fields[$widget->getAttribute('name')] = (new Payload($challenge, $solution))->toBase64();
        }
        $action = html_entity_decode($form->getAttribute('action'), ENT_QUOTES);

        return [$fields, $action];
    }

    /**
     * The DOMElements an XPath expression selects, and nothing else.
     *
     * @return list<\DOMElement>
     */
    private function elements(\DOMXPath $xpath, string $expression, ?\DOMNode $context = null): array
    {
        $found = $xpath->query($expression, $context);
        $this->assertNotFalse($found, "invalid XPath $expression");
        $elements = [];
        foreach ($found as $node) {
            if ($node instanceof \DOMElement) {
                $elements[] = $node;
            }
        }

        return $elements;
    }

    /**
     * @param array<string, string> $fields
     *
     * @return array<string, string>
     */
    private function withoutGuard(array $fields): array
    {
        return array_filter($fields, fn ($name) => !preg_match('/^yw[0-9a-f]{10}$/', (string)$name), ARRAY_FILTER_USE_KEY);
    }

    private function isRefused(string $html): bool
    {
        return str_contains(html_entity_decode(stripcslashes($html), ENT_QUOTES), _t('BOT_GUARD_REFUSED'));
    }

    private function waitPastTheMinimumAge(): void
    {
        sleep(BotGuard::MIN_AGE + 1);
    }

    private function pageContent(string $tag): string
    {
        $db = self::getWiki()->services->get(DbService::class);
        $row = $db->loadSingle('SELECT body FROM ' . $db->prefixTable('pages') . " WHERE tag = ? AND latest = 'Y'", [$tag]);

        return $row === null ? '' : PageBody::content(PageBody::decode((string)$row['body']));
    }

    private function logInAsMember(): void
    {
        [$status] = $this->request('api/login', ['username' => self::MEMBER, 'password' => self::MEMBER_PASSWORD]);
        $this->assertSame(200, $status);
    }

    public function testPageEdit(): void
    {
        [, $html] = $this->request('BotGuardHttpEdit/edit');
        $this->assertSame(1, substr_count($html, 'class="yw-bot-guard-fields"'));
        [$fields] = $this->form($html, '//form[@id="ACEditor"]');
        $fields['submit'] = InputFilter::EDIT_PAGE_SUBMIT_VALUE;

        [, $refused] = $this->request('BotGuardHttpEdit/edit', ['body' => 'robot'] + $this->withoutGuard($fields));
        $this->assertTrue($this->isRefused($refused));
        $this->assertSame('Texte de départ', trim($this->pageContent('BotGuardHttpEdit')));

        $this->waitPastTheMinimumAge();
        $this->request('BotGuardHttpEdit/edit', ['body' => 'humain'] + $fields);
        $this->assertSame('humain', trim($this->pageContent('BotGuardHttpEdit')));
    }

    public function testBazarEntry(): void
    {
        [, $html] = $this->request('BotGuardHttpBazar');
        $this->assertSame(1, substr_count($html, 'class="yw-bot-guard-fields"'), 'only the entry form carries the guard');
        [$fields] = $this->form($html, '//form[@id="bazar-form-' . self::FORM_ID . '"]');
        $fields['valider'] = '1';

        [, $refused] = $this->request('BotGuardHttpBazar', ['bf_titre' => 'BotGuard http robot'] + $this->withoutGuard($fields));
        $this->assertTrue($this->isRefused($refused));
        $this->assertStringContainsString('BotGuard http robot', $refused, 'the visitor keeps what was typed');

        $this->waitPastTheMinimumAge();
        $this->request('BotGuardHttpBazar', ['bf_titre' => 'BotGuard http human'] + $fields);
        $entryManager = self::getWiki()->services->get(EntryManager::class);
        $this->assertTrue($entryManager->isEntry('botguard-http-human'));
        $this->assertFalse($entryManager->isEntry('botguard-http-robot'));
    }

    public function testContactAndSubscribe(): void
    {
        foreach (['BotGuardHttpContact', 'BotGuardHttpSubscribe'] as $tag) {
            [, $html] = $this->request($tag);
            $this->assertSame(1, substr_count($html, 'class="yw-bot-guard-fields"'), $tag);
            [$fields] = $this->form($html, '//form[contains(@class,"ajax-mail-form")]');
            $fields = ['pageTag' => $tag, 'name' => 'Une personne', 'email' => 'personne@example.org', 'subject' => 'Bonjour', 'message' => 'Un message assez long pour passer.'] + $fields;

            [, $refused] = $this->request('api/contact/mail', $this->withoutGuard($fields));
            $answer = json_decode($refused, true);
            $this->assertSame(_t('BOT_GUARD_REFUSED'), $answer['message'] ?? null, $tag);
            $this->assertStringContainsString('yw-bot-guard-fields', $answer['botGuard'] ?? '', 'fresh fields for the next message');

            $this->waitPastTheMinimumAge();
            [, $sent] = $this->request('api/contact/mail', $fields);
            $answer = json_decode($sent, true);
            $this->assertNotSame(_t('BOT_GUARD_REFUSED'), $answer['message'] ?? null, $tag);
            $this->assertContains($answer['message'] ?? null, [
                _t('CONTACT_MESSAGE_SUCCESSFULLY_SENT'),
                _t('CONTACT_SUBSCRIBE_ORDER_SENT'),
                _t('CONTACT_MESSAGE_NOT_SENT'),
            ], "$tag: the submission reaches the mailer");
        }
    }

    public function testSignup(): void
    {
        [, $html] = $this->request('user/signup');
        $this->assertSame(1, substr_count($html, 'class="yw-bot-guard-fields"'));
        [$fields, $action] = $this->form($html, '//form[.//input[@name="confpassword"]]');
        $fields = ['email' => 'botguardhttp@example.com', 'password' => self::MEMBER_PASSWORD, 'confpassword' => self::MEMBER_PASSWORD] + $fields;
        $userManager = self::getWiki()->services->get(UserManager::class);

        $this->request($action, ['name' => 'BotGuardHttpRobot'] + $this->withoutGuard($fields));
        $this->assertNull($userManager->getOneByName('BotGuardHttpRobot'));

        $this->waitPastTheMinimumAge();
        $this->request($action, ['name' => 'BotGuardHttpHuman'] + $fields);
        $this->assertNotNull($userManager->getOneByName('BotGuardHttpHuman'));
    }

    public function testLostPassword(): void
    {
        [, $html] = $this->request('user/lost-password');
        $this->assertSame(1, substr_count($html, 'class="yw-bot-guard-fields"'));
        [$fields] = $this->form($html, '//form[.//input[@name="subStep"]]');
        $fields = ['email' => 'nobody@example.org'] + $fields;

        [, $refused] = $this->request('user/lost-password', $this->withoutGuard($fields));
        $this->assertTrue($this->isRefused($refused));

        $this->waitPastTheMinimumAge();
        [, $answer] = $this->request('user/lost-password', $fields);
        $this->assertFalse($this->isRefused($answer));
    }

    public function testALoggedInMemberCommentsOnlyWithTheFullGuard(): void
    {
        $this->logInAsMember();
        $ajax = ['X-Requested-With: XMLHttpRequest'];

        [, $html] = $this->request('BotGuardHttpComments');
        $this->assertSame(1, substr_count($html, 'class="yw-bot-guard-fields"'));
        [$fields, $action] = $this->form($html, '//form[@id="post-comment"]');
        $this->assertNotSame($fields, $this->withoutGuard($fields), 'a token for a logged-in member too');

        [$status, $refused] = $this->request($action, ['body' => 'Commentaire de robot'] + $this->withoutGuard($fields), $ajax);
        $this->assertSame(400, $status);
        $this->assertSame(_t('BOT_GUARD_REFUSED'), json_decode($refused, true)['error'] ?? null);
        $this->assertStringContainsString('yw-bot-guard-fields', json_decode($refused, true)['botGuard'] ?? '', 'fresh fields come back');

        $this->waitPastTheMinimumAge();
        [$status, $saved] = $this->request($action, ['body' => 'Commentaire humain'] + $fields, $ajax);
        $this->assertSame(200, $status, $saved);

        [$status] = $this->request($action, ['body' => 'Le même jeton rejoué'] + $fields, $ajax);
        $this->assertSame(400, $status);
    }
}
