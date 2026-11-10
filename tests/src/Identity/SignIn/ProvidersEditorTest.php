<?php

namespace YesWiki\Test\Identity\SignIn;

use YesWiki\Identity\Controller\AdminSignInController;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Identity\Service\SignIn\AccountLinks;
use YesWiki\Identity\Service\SignIn\ProvidersEditor;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Kernel\Service\CurrentRequest;
use YesWiki\Render\Service\MarkdownFormatterService;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';
require_once __DIR__ . '/FakeProviderTypes.php';

/** The administration screen of the providers (spec 8.3). It writes a temporary configuration file, never the developer's. */
class ProvidersEditorTest extends YesWikiTestCase
{
    use FakeProviderTypes;

    private const USER = 'SsoEditorUsed';
    private const SECRET = 'sup3r-s3cret-value';

    private string $config;
    private ProvidersEditor $editor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->getWiki();
        $this->config = sys_get_temp_dir() . '/providers-editor-' . bin2hex(random_bytes(4)) . '.php';
        file_put_contents($this->config, "<?php\n\n\$yeswikiConfig = ['wakka_name' => 'test'];\n");
        putenv('YESWIKI_CONFIG_FILE=' . $this->config);
        $this->editor = $this->getWiki()->services->get(ProvidersEditor::class);
    }

    protected function tearDown(): void
    {
        self::actAs(null);
        $users = $this->getWiki()->services->get(UserManager::class);
        if ($user = $users->getOneByName(self::USER)) {
            $users->delete($user);
        }
        $this->restoreProviders();
        putenv('YESWIKI_CONFIG_FILE');
        @unlink($this->config);
        parent::tearDown();
    }

    /** @param array<string, array<string, mixed>> $section */
    private function seed(array $section): void
    {
        file_put_contents($this->config, "<?php\n\n\$yeswikiConfig = " . var_export(['wakka_name' => 'test', 'sso_providers' => $section], true) . ";\n");
    }

    /** @return array<string, array<string, mixed>> */
    private function written(): array
    {
        return $this->editor->all();
    }

    /** @return array<string, mixed> */
    private function oidc(array $more = []): array
    {
        return $more + [
            'type' => 'oidc',
            'label' => 'Acme',
            'enabled' => true,
            'connection' => ['discovery_url' => 'https://a/x', 'client_id' => 'c', 'client_secret' => self::SECRET],
        ];
    }

    public function testCreatingGeneratesAStableIdFromTheLabel(): void
    {
        $posted = ['label' => 'Acme SSO', 'connection' => ['discovery_url' => 'https://a/x', 'client_id' => 'c', 'client_secret' => 's']];
        $this->assertSame('acme-sso', $this->editor->create('oidc', $posted));
        $this->assertSame('acme-sso-2', $this->editor->create('oidc', $posted));
        $this->assertSame(['local', 'acme-sso', 'acme-sso-2'], array_keys($this->written()));
    }

    public function testTheFirstSaveWritesLocalExplicitly(): void
    {
        $this->assertStringNotContainsString('sso_providers', (string)file_get_contents($this->config));
        $this->assertSame(['local'], array_keys($this->editor->all()), 'with no section, local is there');

        $this->editor->create('oidc', ['label' => 'Acme', 'connection' => ['client_id' => 'c', 'client_secret' => 's']]);

        $this->assertStringContainsString("'local' =>", (string)file_get_contents($this->config));
        $this->assertTrue($this->written()['local']['enabled']);
    }

    public function testAMissingRequiredSettingIsAnError(): void
    {
        $errors = $this->editor->create('oidc', ['label' => 'X', 'connection' => []]);
        $this->assertIsArray($errors);
        $this->assertNotEmpty($errors);
        $this->assertStringNotContainsString('sso_providers', (string)file_get_contents($this->config), 'nothing was written');
    }

    public function testABlankSecretKeepsTheStoredOne(): void
    {
        $this->seed(['local' => ['type' => 'local'], 'acme' => $this->oidc()]);
        $this->assertSame([], $this->editor->update('acme', ['label' => 'Acme 2', 'connection' => ['client_id' => 'c2', 'client_secret' => '']]));

        $connection = $this->written()['acme']['connection'];
        $this->assertSame(self::SECRET, $connection['client_secret']);
        $this->assertSame('c2', $connection['client_id']);
        $this->assertSame('Acme 2', $this->written()['acme']['label']);
    }

    public function testAFilledSecretReplacesIt(): void
    {
        $this->seed(['local' => ['type' => 'local'], 'acme' => $this->oidc()]);
        $this->assertSame([], $this->editor->update('acme', ['connection' => ['client_secret' => 'brand-new']]));
        $this->assertSame('brand-new', $this->written()['acme']['connection']['client_secret']);
    }

    public function testKeysTheScreenDoesNotShowSurvive(): void
    {
        $acme = $this->oidc([
            'account_name_template' => '{first_name}.{last_name}',
            'match_by_name_until' => '2027-11-10',
            'custom_unknown_key' => ['kept' => true],
        ]);
        $acme['connection'] += ['legacy_callback' => 'https://old.example/callback', 'scope_separator' => ',', 'unknown_to_the_type' => 'x'];
        $this->seed(['local' => ['type' => 'local'], 'acme' => $acme]);

        $this->assertSame([], $this->editor->update('acme', [
            'label' => 'Renamed',
            'enabled' => '1',
            'connection' => ['discovery_url' => 'https://b/y', 'client_id' => 'c', 'client_secret' => ''],
            'attributes' => ['email' => 'mail'],
            'create_accounts' => '0',
            'match_by_email' => '1',
            'group_mapping' => [['provider' => 'a', 'wiki' => 'B']],
            'extra_fields' => [['field' => 'department', 'attribute' => 'service']],
        ]));

        $saved = $this->written()['acme'];
        $this->assertSame('{first_name}.{last_name}', $saved['account_name_template']);
        $this->assertSame('2027-11-10', $saved['match_by_name_until']);
        $this->assertSame(['kept' => true], $saved['custom_unknown_key']);
        $this->assertSame('https://old.example/callback', $saved['connection']['legacy_callback']);
        $this->assertSame(',', $saved['connection']['scope_separator']);
        $this->assertSame('x', $saved['connection']['unknown_to_the_type']);
        $this->assertSame('https://b/y', $saved['connection']['discovery_url']);
        $this->assertSame('Renamed', $saved['label']);
        $this->assertFalse($saved['create_accounts']);
        $this->assertTrue($saved['match_by_email']);
        $this->assertSame(['email' => 'mail'], $saved['attributes']);
        $this->assertSame([['provider' => 'a', 'wiki' => 'B']], $saved['group_mapping']);
        $this->assertSame(['department' => 'service'], $saved['extra_fields']);
    }

    public function testRemovingEveryRowOfATableEmptiesIt(): void
    {
        $acme = $this->oidc(['group_mapping' => [['provider' => 'a', 'wiki' => 'B']], 'extra_fields' => ['department' => 'service']]);
        $this->seed(['local' => ['type' => 'local'], 'acme' => $acme]);

        $this->assertSame([], $this->editor->update('acme', ['label' => 'Acme']), 'no flag: the tables are left as they are');
        $this->assertSame([['provider' => 'a', 'wiki' => 'B']], $this->written()['acme']['group_mapping']);
        $this->assertSame(['department' => 'service'], $this->written()['acme']['extra_fields']);

        $this->assertSame([], $this->editor->update('acme', ['group_mapping_present' => '1', 'extra_fields_present' => '1']));
        $this->assertSame([], $this->written()['acme']['group_mapping']);
        $this->assertSame([], $this->written()['acme']['extra_fields']);
    }

    public function testTheEditFormPostsAPresenceFlagForEachTable(): void
    {
        $this->seed(['local' => ['type' => 'local'], 'acme' => $this->oidc()]);
        $html = (string)$this->getWiki()->services->get(AdminSignInController::class)->edit('acme')->getContent();
        $this->assertStringContainsString('name="group_mapping_present"', $html);
        $this->assertStringContainsString('name="extra_fields_present"', $html);
    }
    public function testTheTypeCannotChange(): void
    {
        $this->seed(['local' => ['type' => 'local'], 'acme' => $this->oidc()]);
        $this->assertSame([], $this->editor->update('acme', ['type' => 'local', 'label' => 'Still oidc']));
        $this->assertSame('oidc', $this->written()['acme']['type']);
    }

    public function testTheLastEnabledProviderCannotBeSwitchedOff(): void
    {
        $this->assertNotNull($this->editor->setEnabled('local', false));

        $this->seed(['local' => ['type' => 'local'], 'acme' => $this->oidc()]);
        $this->assertNull($this->editor->setEnabled('local', false), 'another one is on');
        $this->assertNotNull($this->editor->setEnabled('acme', false), 'now it is the last');
        $this->assertTrue($this->written()['acme']['enabled']);
        $this->assertNull($this->editor->setEnabled('local', true));
        $this->assertNull($this->editor->setEnabled('acme', false));
        $this->assertNotEmpty($this->editor->update('local', ['enabled' => '0']), 'the edit form cannot do it either');
    }

    public function testAProviderWithAccountsCannotBeDeleted(): void
    {
        $this->seed(['local' => ['type' => 'local'], 'used' => $this->oidc(), 'free' => $this->oidc()]);
        $services = $this->getWiki()->services;
        $user = $services->get(UserManager::class)->create(self::USER, 'ssoeditorused@example.com', 'Aa1!aaaaRegression');
        $services->get(AccountLinks::class)->link($user, 'used', 'sub-1');

        $this->assertNotNull($this->editor->delete('used'));
        $this->assertArrayHasKey('used', $this->written());
        $this->assertNull($this->editor->delete('free'));
        $this->assertArrayNotHasKey('free', $this->written());
    }

    public function testLocalCannotBeDeleted(): void
    {
        $this->seed(['local' => ['type' => 'local'], 'acme' => $this->oidc()]);
        $this->assertNotNull($this->editor->delete('local'));
        $this->assertArrayHasKey('local', $this->written());
    }

    public function testAnUnavailableProviderIsLeftUntouchedByASaveOfAnother(): void
    {
        $gone = ['type' => 'gone-extension', 'label' => 'Gone', 'enabled' => true, 'connection' => ['k' => 'v'], 'weird' => [1, 2]];
        $this->seed(['gone' => $gone, 'local' => ['type' => 'local'], 'acme' => $this->oidc()]);

        $this->assertSame([], $this->editor->update('acme', ['label' => 'Acme 3']));
        $this->assertNull($this->editor->setEnabled('local', false));
        $this->assertNotNull($this->editor->delete('local'));

        $this->assertSame($gone, $this->written()['gone']);
        $this->assertSame(['gone', 'local', 'acme'], array_keys($this->written()), 'the order is kept');
        $this->assertNotNull($this->editor->setEnabled('gone', true), 'it cannot be switched on without its type');
        $this->assertNotEmpty($this->editor->update('gone', ['label' => 'x']));
        $this->assertSame($gone, $this->written()['gone']);
    }

    public function testAnUnavailableProviderDoesNotCountAsEnabled(): void
    {
        $this->seed(['gone' => ['type' => 'gone-extension', 'enabled' => true], 'local' => ['type' => 'local']]);
        $this->assertNotNull($this->editor->setEnabled('local', false));
    }

    public function testTheEditPageNeverContainsTheStoredSecret(): void
    {
        $this->seed(['local' => ['type' => 'local'], 'acme' => $this->oidc()]);
        $html = (string)$this->getWiki()->services->get(AdminSignInController::class)->edit('acme')->getContent();

        $this->assertStringNotContainsString(self::SECRET, $html);
        $this->assertMatchesRegularExpression('#<input[^>]*type="password"[^>]*name="connection\[client_secret\]"|<input[^>]*name="connection\[client_secret\]"[^>]*type="password"#', $html);
        $this->assertStringContainsString('user/sign-in/acme/callback', $html);
        $this->assertStringContainsString('https://a/x', $html, 'the other settings are shown');
    }

    public function testTheIndexListsEveryRegisteredType(): void
    {
        $this->seed(['local' => ['type' => 'local'], 'acme' => $this->oidc(), 'gone' => ['type' => 'gone-extension']]);
        $services = $this->getWiki()->services;
        $html = (string)$services->get(AdminSignInController::class)->index()->getContent();

        $types = $services->get(\YesWiki\Identity\Service\SignIn\ProviderRegistry::class)->types();
        $this->assertArrayHasKey('oidc', $types);
        $this->assertStringNotContainsString('/new/local', $html, 'local exists already and cannot be added');
        foreach ($types as $name => $type) {
            if ($name === 'local') {
                continue;
            }
            $this->assertStringContainsString('/new/' . $name, $html, "adding a $name provider is offered");
            $this->assertStringContainsString(htmlspecialchars($type->label(), ENT_QUOTES), $html);
        }
        $this->assertStringContainsString('Acme', $html);
        $this->assertStringContainsString(htmlspecialchars(_t('SIGN_IN_ADMIN_UNAVAILABLE'), ENT_QUOTES), $html);
        $this->assertStringNotContainsString(self::SECRET, $html);
    }

    public function testTheUsersTableShowsTheProviderOfALinkedAccount(): void
    {
        $this->installProviders(['acme' => ['type' => 'fakeredirect', 'label' => 'Acme Single Sign-On'], 'local' => ['type' => 'local']]);
        $services = $this->getWiki()->services;
        $user = $services->get(UserManager::class)->create(self::USER, 'ssoeditorused@example.com', 'Aa1!aaaaRegression');
        $services->get(AccountLinks::class)->link($user, 'acme', 'sub-1');

        self::actAs('WikiAdmin');
        $html = (string)$services->get(MarkdownFormatterService::class)->format('{{userstable}}');

        $this->assertStringContainsString(self::USER, $html);
        $this->assertStringContainsString('Acme Single Sign-On', $html);
    }
}
