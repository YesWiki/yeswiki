<?php

namespace YesWiki\Test\Bazar\Service;

use PHPUnit\Framework\Attributes\DataProvider;
use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Bazar\Service\FormManager;
use YesWiki\Bazar\Service\SearchManager;
use YesWiki\Core\Controller\AuthController;
use YesWiki\Core\Service\AclService;
use YesWiki\Core\Service\PageManager;
use YesWiki\Core\Service\UserManager;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * A field a reader may not read stays hidden from them in search filters and in the entry's translations.
 */
class HiddenFieldDisclosureTest extends YesWikiTestCase
{
    private const OWNER = 'HiddenFieldDisclosureOwner';
    private const TEMPLATE = "texte***bf_titre***Titre***60***255*** *** ***text***1*** *** *** * *** * *** *** *** ***\n"
        . "texte***bf_secret***Secret***60***255*** *** ***text***0*** *** ***@admins*** * *** *** *** ***\n"
        . "texte***bf_mine***Mine***60***255*** *** ***text***0*** *** ***%*** * *** *** *** ***\n"
        . 'champs_mail***bf_mail***Email*** *** *** ***form*** ***0***0*** *** * *** * *** *** *** ***';

    private static string $formId;
    private static string $anonymousTag;
    private static string $ownedTag;
    private $wiki;
    private array $savedConfig;

    public static function setUpBeforeClass(): void
    {
        $wiki = self::getWiki();
        $GLOBALS['wiki'] = $wiki;
        $auth = $wiki->services->get(AuthController::class);
        $users = $wiki->services->get(UserManager::class);
        $entries = $wiki->services->get(EntryManager::class);
        $acls = $wiki->services->get(AclService::class);
        $auth->logout();
        if (!$users->getOneByName(self::OWNER)) {
            $users->create(['name' => self::OWNER, 'email' => 'hiddenfielddisclosure@example.com', 'password' => 'a-long-enough-password']);
        }
        self::$formId = $wiki->services->get(FormManager::class)->create(['bn_label_nature' => 'Hidden field disclosure', 'bn_template' => self::TEMPLATE, 'bn_condition' => '', 'bn_description' => '']);
        self::$anonymousTag = $entries->create(self::$formId, ['antispam' => 1, 'bf_titre' => 'Public title', 'bf_secret' => 'topsecret', 'bf_mine' => 'mymine', 'bf_mail' => 'hidden@example.org'])['id_fiche'];
        $auth->login($users->getOneByName(self::OWNER));
        self::$ownedTag = $entries->create(self::$formId, ['antispam' => 1, 'bf_titre' => 'Owned title', 'bf_secret' => 'ownersecret', 'bf_mine' => 'ownermine', 'bf_mail' => 'owner@example.org'])['id_fiche'];
        $auth->logout();
        foreach ([self::$anonymousTag, self::$ownedTag] as $tag) {
            $acls->save($tag, 'read', '*');
        }
        $pages = $wiki->services->get(PageManager::class);
        $page = $pages->getOne(self::$anonymousTag, null, false, true);
        $body = json_decode($page['body'], true);
        $body['__translation'] = ['en' => ['bf_titre' => 'Translated title', 'bf_secret' => 'translated secret', 'bf_mine' => 'translated mine', 'bf_mail' => 'translated@example.org']];
        $pages->save(self::$anonymousTag, json_encode($body), '', true);
    }

    public static function tearDownAfterClass(): void
    {
        $wiki = self::getWiki();
        $wiki->services->get(AuthController::class)->logout();
        foreach ([self::$anonymousTag, self::$ownedTag] as $tag) {
            $wiki->services->get(EntryManager::class)->delete($tag, true);
            $wiki->services->get(AclService::class)->delete($tag);
        }
        $wiki->services->get(FormManager::class)->delete(self::$formId);
        $users = $wiki->services->get(UserManager::class);
        if ($user = $users->getOneByName(self::OWNER)) {
            $users->delete($user);
        }
    }

    protected function setUp(): void
    {
        $this->wiki = $this->getWiki();
        $GLOBALS['wiki'] = $this->wiki;
        $this->savedConfig = array_intersect_key($this->wiki->config, array_flip(['other_languages', 'supported_langs', 'default_language']));
        unset($this->wiki->config['supported_langs']);
        $this->wiki->config['default_language'] = 'fr';
        $this->wiki->config['other_languages'] = ['en'];
        $this->wiki->services->get(AuthController::class)->logout();
    }

    protected function tearDown(): void
    {
        foreach (['other_languages', 'supported_langs', 'default_language'] as $key) {
            unset($this->wiki->config[$key]);
        }
        $this->wiki->config = array_merge($this->wiki->config, $this->savedConfig);
        $this->wiki->lang = 'fr';
        $this->wiki->services->get(AuthController::class)->logout();
    }

    /** @return list<string> the tags a guarded search returns */
    private function found(array $params): array
    {
        $tags = array_keys($this->wiki->services->get(SearchManager::class)->search($params + ['formsIds' => [self::$formId]], true, true));
        sort($tags);

        return $tags;
    }

    private function loginAsOwner(): void
    {
        $users = $this->wiki->services->get(UserManager::class);
        $this->wiki->services->get(AuthController::class)->login($users->getOneByName(self::OWNER));
    }

    public static function hiddenValueProbes(): array
    {
        return [
            'equality on a group-restricted field' => [['queries' => 'bf_secret=topsecret']],
            'regex on a group-restricted field' => [['queries' => 'bf_secret=/^top/']],
            'comparison on a group-restricted field' => [['queries' => 'bf_secret>=t']],
            'keyword on a group-restricted field' => [['keywords' => 'topsecret', 'searchfields' => 'bf_secret']],
            'equality on an owner-only field' => [['queries' => 'bf_mine=mymine']],
            'equality on a contact-form email' => [['queries' => 'bf_mail=hidden@example.org']],
            'regex on a contact-form email' => [['queries' => 'bf_mail=/^hidden/']],
        ];
    }

    #[DataProvider('hiddenValueProbes')]
    public function testAnAnonymousFilterCannotProbeAHiddenValue(array $params)
    {
        $this->assertNotContains(self::$anonymousTag, $this->found($params));
    }

    public function testAnAnonymousFilterCannotProbeAHiddenTranslation()
    {
        $this->wiki->lang = 'en';

        $this->assertSame([self::$anonymousTag], $this->found(['queries' => 'bf_titre=Translated title']));
        $this->assertSame([], $this->found(['queries' => 'bf_secret=translated secret']));
    }

    public function testAnAnonymousFilterOnAPublicFieldStillWorks()
    {
        $this->assertSame([self::$anonymousTag], $this->found(['queries' => 'bf_titre=Public title']));
    }

    public function testAHiddenFieldFiltersAsTheEmptyValueTheReaderSees()
    {
        $this->assertContains(self::$anonymousTag, $this->found(['queries' => 'bf_secret=']));
    }

    public function testAnOwnerFiltersOnTheirOwnEntry()
    {
        $this->loginAsOwner();

        $this->assertSame([self::$ownedTag], $this->found(['queries' => 'bf_mine=ownermine']));
        $this->assertSame([self::$ownedTag], $this->found(['queries' => 'bf_secret=ownersecret']));
        $this->assertSame([], $this->found(['queries' => 'bf_mine=mymine']));
    }

    public function testTheTranslationOfAnUnreadableFieldIsNotServed()
    {
        $page = $this->wiki->services->get(PageManager::class)->getOne(self::$anonymousTag, null, false);
        $translations = json_decode($page['body'], true)['__translation']['en'] ?? [];

        $this->assertSame('Translated title', $translations['bf_titre'] ?? null);
        $this->assertArrayNotHasKey('bf_secret', $translations);
        $this->assertArrayNotHasKey('bf_mine', $translations);
        $this->assertArrayNotHasKey('bf_mail', $translations);
    }

    public function testTheOwnerStillReadsTheirRestrictedFields()
    {
        $this->loginAsOwner();
        $page = $this->wiki->services->get(PageManager::class)->getOne(self::$ownedTag, null, false);

        $this->assertSame('ownersecret', json_decode($page['body'], true)['bf_secret']);
    }
}
