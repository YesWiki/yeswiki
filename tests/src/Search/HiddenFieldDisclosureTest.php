<?php

namespace YesWiki\Test\Search;

use PHPUnit\Framework\Attributes\DataProvider;
use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Field\EmailField;
use YesWiki\Content\Service\EntryManager;
use YesWiki\Content\Service\FormManager;
use YesWiki\Content\Service\PageManager;
use YesWiki\Identity\Service\AclService;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Search\Service\SearchManager;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * A field a reader may not read stays hidden from them in search filters and in the Content's translations.
 */
class HiddenFieldDisclosureTest extends YesWikiTestCase
{
    private const OWNER = 'HiddenFieldDisclosureOwner';

    private static int $formId;
    private static string $anonymousTag;
    private static string $ownedTag;

    public static function setUpBeforeClass(): void
    {
        $services = self::getWiki()->services;
        $auth = $services->get(AuthenticationService::class);
        $users = $services->get(UserManager::class);
        $entries = $services->get(EntryManager::class);
        $auth->logout();
        if (!$users->getOneByName(self::OWNER)) {
            $users->create(self::OWNER, 'hiddenfielddisclosure@example.com', 'Aa1!aaaaDisclosure');
        }
        $forms = $services->get(FormManager::class);
        self::$formId = $forms->findNewId();
        $forms->create([
            'id' => self::$formId,
            'label' => 'HiddenFieldDisclosureTest',
            'template' => json_encode([
                ['type' => 'texte', 'name' => 'bf_titre', 'label' => 'Titre'],
                ['type' => 'texte', 'name' => 'bf_secret', 'label' => 'Secret', 'read_access' => '@admins'],
                ['type' => 'texte', 'name' => 'bf_mine', 'label' => 'Mine', 'read_access' => '%'],
                ['type' => 'champs_mail', 'name' => 'bf_mail', 'label' => 'Email', 'show_contact_form' => 'form'],
            ]),
            'condition' => '',
        ]);
        self::$anonymousTag = $entries->create(self::$formId, ['antispam' => 1, 'bf_titre' => 'Public title', 'bf_secret' => 'topsecret', 'bf_mine' => 'mymine', 'bf_mail' => 'hidden@example.org'])['tag'];
        $auth->login($users->getOneByName(self::OWNER));
        self::$ownedTag = $entries->create(self::$formId, ['antispam' => 1, 'bf_titre' => 'Owned title', 'bf_secret' => 'ownersecret', 'bf_mine' => 'ownermine', 'bf_mail' => 'owner@example.org'])['tag'];
        $auth->logout();
        foreach ([self::$anonymousTag, self::$ownedTag] as $tag) {
            $services->get(AclService::class)->save($tag, 'read', '*');
        }
        $pages = $services->get(PageManager::class);
        $stored = $pages->getOne(self::$anonymousTag, null, false, true)['body'];
        $body = is_array($stored) ? $stored : PageBody::decode($stored);
        $body['__translation'] = ['en' => ['bf_titre' => 'Translated title', 'bf_secret' => 'translated secret', 'bf_mine' => 'translated mine', 'bf_mail' => 'translated@example.org']];
        $pages->save(self::$anonymousTag, $body, '', true, null, $pages->getOne(self::$anonymousTag, null, false, true)['type'] ?? null);
    }

    public static function tearDownAfterClass(): void
    {
        $services = self::getWiki()->services;
        $services->get(AuthenticationService::class)->logout();
        foreach ([self::$anonymousTag, self::$ownedTag] as $tag) {
            $services->get(EntryManager::class)->delete($tag, true);
            $services->get(AclService::class)->delete($tag);
        }
        $services->get(FormManager::class)->delete(self::$formId);
        $users = $services->get(UserManager::class);
        if ($user = $users->getOneByName(self::OWNER)) {
            $users->delete($user);
        }
    }

    protected function setUp(): void
    {
        $this->getWiki()->services->get(AuthenticationService::class)->logout();
    }

    protected function tearDown(): void
    {
        $this->getWiki()->services->get(AuthenticationService::class)->logout();
    }

    /** @return list<string> the tags a guarded search returns */
    private function found(array $params): array
    {
        $tags = array_keys($this->getWiki()->services->get(SearchManager::class)->search($params + ['formsIds' => [self::$formId]], true, true));
        sort($tags);

        return $tags;
    }

    private function loginAsOwner(): void
    {
        $services = $this->getWiki()->services;
        $services->get(AuthenticationService::class)->login($services->get(UserManager::class)->getOneByName(self::OWNER));
    }

    public function testTheEmailFieldIsInContactFormMode(): void
    {
        $fields = array_filter(
            $this->getWiki()->services->get(FormManager::class)->getOne(self::$formId)['prepared'],
            fn ($field) => $field instanceof EmailField
        );

        $this->assertTrue(current($fields)->getShowContactForm());
    }

    /** @return array<string, array{array<string, string>}> */
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
    public function testAnAnonymousFilterCannotProbeAHiddenValue(array $params): void
    {
        $this->assertNotContains(self::$anonymousTag, $this->found($params));
    }

    public function testAnAnonymousFilterOnAPublicFieldStillWorks(): void
    {
        $this->assertSame([self::$anonymousTag], $this->found(['queries' => 'bf_titre=Public title']));
    }

    public function testAHiddenFieldFiltersAsTheEmptyValueTheReaderSees(): void
    {
        $this->assertContains(self::$anonymousTag, $this->found(['queries' => 'bf_secret=']));
    }

    public function testAnOwnerFiltersOnTheirOwnEntry(): void
    {
        $this->loginAsOwner();

        $this->assertSame([self::$ownedTag], $this->found(['queries' => 'bf_mine=ownermine']));
        $this->assertSame([self::$ownedTag], $this->found(['queries' => 'bf_secret=ownersecret']));
        $this->assertSame([], $this->found(['queries' => 'bf_mine=mymine']));
    }

    public function testTheTranslationOfAnUnreadableFieldIsNotServed(): void
    {
        $page = $this->getWiki()->services->get(PageManager::class)->getOne(self::$anonymousTag, null, false);
        $body = is_array($page['body']) ? $page['body'] : PageBody::decode($page['body']);
        $translations = $body['__translation']['en'] ?? [];

        $this->assertSame('Translated title', $translations['bf_titre'] ?? null);
        $this->assertArrayNotHasKey('bf_secret', $translations);
        $this->assertArrayNotHasKey('bf_mine', $translations);
        $this->assertArrayNotHasKey('bf_mail', $translations);
    }
}
