<?php

namespace YesWiki\Test\Identity\SignIn;

use Symfony\Component\HttpFoundation\Request;
use YesWiki\Content\Service\PageManager;
use YesWiki\Identity\Entity\User;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Identity\Service\SignIn\ProfileSync;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Identity\Service\UserOperationsService;
use YesWiki\Kernel\Service\CurrentRequest;
use YesWiki\Kernel\Service\RuntimeConfig;
use YesWiki\Render\Service\MarkdownFormatterService;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** What a provider supplies cannot be edited in the wiki, by anyone, and the page says why. */
class LinkedProfileTest extends YesWikiTestCase
{
    private const LINKED = 'LinkedP';
    private const LOCAL = 'LocalP';
    private const PASSWORD = 'a-long-enough-password';

    private $services;
    private UserManager $users;
    private UserOperationsService $ops;
    private User $linked;
    /** @var mixed */
    private $sectionWas;
    private Request $requestWas;

    protected function setUp(): void
    {
        parent::setUp();
        $this->services = $this->getWiki()->services;
        $this->users = $this->services->get(UserManager::class);
        $this->ops = $this->services->get(UserOperationsService::class);
        $config = $this->services->get(RuntimeConfig::class);
        $this->sectionWas = $config['sso_providers'];
        $this->requestWas = $this->services->get(CurrentRequest::class)->get();
        $config['sso_providers'] = ['acme' => [
            'type' => 'oidc',
            'label' => 'Acme SSO',
            'extra_fields' => ['department' => 'bf_service'],
        ]];
        $this->cleanUp();
        $linked = $this->users->createLinked(self::LINKED, 'linkedp@example.com', ['first_name' => 'Marie', 'last_name' => 'Curie'], 'acme', 'subject-linkedp');
        $this->assertNotNull($linked);
        $this->linked = $linked;
        $this->users->create(self::LOCAL, 'localp@example.com', self::PASSWORD);
    }

    protected function tearDown(): void
    {
        self::actAs(null);
        $config = $this->services->get(RuntimeConfig::class);
        if ($this->sectionWas === null) {
            unset($config['sso_providers']);
        } else {
            $config['sso_providers'] = $this->sectionWas;
        }
        $this->services->get(CurrentRequest::class)->replace($this->requestWas);
        $this->cleanUp();
        parent::tearDown();
    }

    private function cleanUp(): void
    {
        foreach ([self::LINKED, self::LOCAL] as $tag) {
            $user = $this->users->getOneByName($tag);
            if ($user) {
                $this->users->delete($user);
            }
            $this->services->get(PageManager::class)->deleteOrphaned($tag);
        }
    }

    /** @return array<string, mixed> */
    private function bodyOf(User $user): array
    {
        $page = $this->services->get(PageManager::class)->getOne($user->getName(), null, true, true);

        return is_array($page['body'] ?? null) ? $page['body'] : [];
    }

    private function attemptToEditLinked(): void
    {
        $this->ops->update($this->linked, ['email' => 'hacked@example.com', 'first_name' => 'Evil', 'motto' => 'mine', 'password' => 'a-long-enough-password']);
        $user = $this->users->getOneByName(self::LINKED);
        $this->assertNotNull($user);
        $this->assertSame('linkedp@example.com', $user->getEmail());
        $this->assertSame('', $user->getPassword());
        $this->assertSame('mine', $user['motto']);
        $this->assertSame('Marie', $this->bodyOf($user)['first_name']);
    }

    public function testUpdateIgnoresManagedFieldsOnALinkedAccount(): void
    {
        $this->attemptToEditLinked();
    }

    public function testAnAdminIsBoundByTheSameRule(): void
    {
        self::actAs('WikiAdmin');
        $this->attemptToEditLinked();
    }

    public function testALocalAccountStaysEditable(): void
    {
        $local = $this->users->getOneByName(self::LOCAL);
        $this->assertNotNull($local);
        $this->ops->update($local, ['email' => 'localp2@example.com', 'first_name' => 'Pierre']);

        $user = $this->users->getOneByName(self::LOCAL);
        $this->assertSame('localp2@example.com', $user->getEmail());
        $this->assertSame('Pierre', $this->bodyOf($user)['first_name']);
        $this->assertSame([], $this->ops->managedKeys($user));
    }

    public function testSignInStillWrites(): void
    {
        $changed = $this->services->get(ProfileSync::class)->apply($this->linked, ['email' => 'fromidp@example.com', 'first_name' => 'Marie-Sklodowska']);

        $this->assertTrue($changed);
        $user = $this->users->getOneByName(self::LINKED);
        $this->assertSame('fromidp@example.com', $user->getEmail());
        $this->assertSame('Marie-Sklodowska', $this->bodyOf($user)['first_name']);
    }

    public function testManagedKeysNameTheProfileTheExtraFieldsAndThePassword(): void
    {
        $this->assertEqualsCanonicalizing(
            ['first_name', 'last_name', 'email', 'password', 'bf_service'],
            $this->ops->managedKeys($this->linked)
        );
    }

    public function testManagedKeysSurviveAProviderThatIsNoLongerConfigured(): void
    {
        $this->services->get(RuntimeConfig::class)['sso_providers'] = ['other' => ['type' => 'oidc']];

        $this->assertEqualsCanonicalizing(
            ['first_name', 'last_name', 'email', 'password'],
            $this->ops->managedKeys($this->linked)
        );
    }

    public function testTheFormShowsTheNoticeAndNoPasswordSection(): void
    {
        $html = $this->renderSettingsFor(self::LINKED);

        $this->assertStringContainsString('Acme SSO', $html);
        $this->assertStringContainsString(_t('USERSETTINGS_MANAGED_BY_PROVIDER', ['provider' => 'Acme SSO']), $html);
        $this->assertStringNotContainsString('name="oldpass"', $html);
        $this->assertStringNotContainsString('value="changepass"', $html);
        $this->assertMatchesRegularExpression('/name="email"[^>]*disabled/', $html);
    }

    public function testTheFormOfALocalAccountIsUnchanged(): void
    {
        $html = $this->renderSettingsFor(self::LOCAL);

        $this->assertStringNotContainsString('Acme SSO', $html);
        $this->assertStringContainsString('name="oldpass"', $html);
        $this->assertDoesNotMatchRegularExpression('/name="email"[^>]*disabled/', $html);
    }

    public function testTheSettingsActionRefusesAPasswordChangeOnALinkedAccount(): void
    {
        $action = new \YesWiki\Identity\Action\UserSettingsAction();
        $action->setServices($this->services);
        $reflection = new \ReflectionClass($action);
        $reflection->getProperty('userLoggedIn')->setValue($action, true);
        $reflection->getMethod('getServices')->invoke($action);
        $reflection->getMethod('changePassword')->invoke($action, $this->linked, ['oldpass' => 'x', 'password' => 'Aa1!brandNewPassword']);

        $this->assertSame('', $this->users->getOneByName(self::LINKED)->getPassword());
    }

    private function renderSettingsFor(string $tag): string
    {
        $this->services->get(CurrentRequest::class)->replace(Request::create('/'));
        $user = $this->users->getOneByName($tag);
        $this->assertNotNull($user);
        $authentication = $this->services->get(AuthenticationService::class);
        $authentication->login($user);
        try {
            return $this->services->get(MarkdownFormatterService::class)->format('{{usersettings}}');
        } finally {
            $authentication->logout();
        }
    }
}
