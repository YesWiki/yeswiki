<?php

namespace YesWiki\Test\Identity\SignIn;

use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use YesWiki\Identity\Controller\AdminSignInController;
use YesWiki\Identity\Service\CsrfTokenChecker;
use YesWiki\Identity\Service\SignIn\AccountLinks;
use YesWiki\Identity\Service\SignIn\ProvidersEditor;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Identity\SignIn\Contract\IdentityProvider;
use YesWiki\Identity\SignIn\Contract\ProviderConfig;
use YesWiki\Identity\SignIn\Contract\ProviderType;
use YesWiki\Identity\SignIn\Contract\PublishesMetadata;
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

    /** @var array{0: bool, 1: ?object}|null the token checker the container held before a test replaced it */
    private ?array $checkerWas = null;

    private ?Request $requestWas = null;
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
        $this->restorePosting();
        $users = $this->getWiki()->services->get(UserManager::class);
        if ($user = $users->getOneByName(self::USER)) {
            $users->delete($user);
        }
        $this->restoreProviders();
        putenv('YESWIKI_CONFIG_FILE');
        @unlink($this->config);
        parent::tearDown();
    }

    /**
     * The admin controller as a form posted to it sees it. filter_input() reads the SAPI's input, which a CLI run has none of,
     * so the token checker is replaced in the container's table (the seam FakeProviderTypes uses): the token is not what is tested here.
     *
     * @param array<string, mixed> $form
     */
    private function posted(array $form): AdminSignInController
    {
        $services = $this->getWiki()->services;
        $current = $services->get(CurrentRequest::class);
        $this->requestWas ??= $current->get();
        $current->replace(Request::create('/?admin/sign-in', 'POST', $form));
        $table = new \ReflectionProperty(Container::class, 'services');
        $built = $table->getValue($services);
        $this->checkerWas ??= [array_key_exists(CsrfTokenChecker::class, $built), $built[CsrfTokenChecker::class] ?? null];
        $built[CsrfTokenChecker::class] = $this->createStub(CsrfTokenChecker::class);
        $table->setValue($services, $built);
        self::actAs('WikiAdmin');

        return $services->get(AdminSignInController::class);
    }

    private function restorePosting(): void
    {
        $services = $this->getWiki()->services;
        if ($this->requestWas !== null) {
            $services->get(CurrentRequest::class)->replace($this->requestWas);
            $this->requestWas = null;
        }
        if ($this->checkerWas !== null) {
            $table = new \ReflectionProperty(Container::class, 'services');
            $built = $table->getValue($services);
            unset($built[CsrfTokenChecker::class]);
            if ($this->checkerWas[0]) {
                $built[CsrfTokenChecker::class] = $this->checkerWas[1];
            }
            $table->setValue($services, $built);
            $this->checkerWas = null;
        }
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

    /**
     * @param array<string, mixed> $more
     *
     * @return array<string, mixed>
     */
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

    public function testTheEditFormOffersSaveAndTest(): void
    {
        $this->seed(['local' => ['type' => 'local'], 'acme' => $this->oidc()]);
        $html = (string)$this->getWiki()->services->get(AdminSignInController::class)->edit('acme')->getContent();

        $this->assertMatchesRegularExpression('#<button[^>]*type="submit"[^>]*name="then"[^>]*value="test"#', $html);
        $this->assertStringContainsString(htmlspecialchars(_t('SIGN_IN_ADMIN_SAVE_AND_TEST'), ENT_QUOTES), $html);
    }

    public function testSaveAndTestSavesThenOpensTheTest(): void
    {
        $this->seed(['local' => ['type' => 'local'], 'acme' => $this->oidc()]);

        $response = $this->posted(['label' => 'Acme renamed', 'enabled' => '1', 'connection' => ['discovery_url' => 'https://a/x', 'client_id' => 'c'], 'then' => 'test'])->edit('acme');

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertStringEndsWith('admin/sign-in/acme/test', (string)$response->headers->get('Location'));
        $this->assertSame('Acme renamed', $this->written()['acme']['label'], 'saved exactly like Save');
        $this->assertSame(self::SECRET, $this->written()['acme']['connection']['client_secret']);
        $this->assertArrayNotHasKey('then', $this->written()['acme']);
    }

    public function testSaveAndTestThatCannotSaveStaysOnTheForm(): void
    {
        $this->seed(['local' => ['type' => 'local'], 'acme' => $this->oidc()]);

        $response = $this->posted(['label' => '', 'enabled' => '1', 'connection' => ['discovery_url' => 'https://a/x', 'client_id' => 'c'], 'then' => 'test'])->edit('acme');

        $this->assertNotInstanceOf(RedirectResponse::class, $response);
        $this->assertStringContainsString(htmlspecialchars(_t('SIGN_IN_ADMIN_LABEL_REQUIRED'), ENT_QUOTES), (string)$response->getContent());
        $this->assertSame('Acme', $this->written()['acme']['label'], 'nothing was saved');
    }

    public function testSaveAloneStillGoesBackToTheList(): void
    {
        $this->seed(['local' => ['type' => 'local'], 'acme' => $this->oidc()]);

        $response = $this->posted(['label' => 'Acme renamed', 'enabled' => '1', 'connection' => ['discovery_url' => 'https://a/x', 'client_id' => 'c']])->edit('acme');

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertStringEndsWith('admin/sign-in', (string)$response->headers->get('Location'));
    }

    public function testSaveAndTestOnANewProviderOpensItsTest(): void
    {
        $this->seed(['local' => ['type' => 'local']]);

        $response = $this->posted(['label' => 'Brand New', 'enabled' => '1', 'connection' => ['discovery_url' => 'https://a/x', 'client_id' => 'c', 'client_secret' => 's'], 'then' => 'test'])->add('oidc');

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertStringEndsWith('admin/sign-in/brand-new/test', (string)$response->headers->get('Location'));
        $this->assertArrayHasKey('brand-new', $this->written());
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
        $this->assertNotNull($user);
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

    /**
     * @param array<string, mixed> $connection
     *
     * @return array<string, mixed>
     */
    private static function saml(array $connection = []): array
    {
        return ['type' => 'saml', 'label' => 'Acme SAML', 'enabled' => true, 'connection' => $connection + ['metadata_url' => 'https://idp.example/saml/metadata']];
    }

    private function editPage(string $id): string
    {
        $response = $this->getWiki()->services->get(AdminSignInController::class)->edit($id);
        $this->assertSame(200, $response->getStatusCode());

        return (string)$response->getContent();
    }

    /** @return list<string> the values offered to copy, as the page holds them */
    private static function copyable(string $html): array
    {
        preg_match_all('#<input[^>]*class="yw-input yw-sign-in-copy"[^>]*value="([^"]*)"#', $html, $found);

        return $found[1];
    }

    public function testASamlEditPageShowsItsValuesToCopy(): void
    {
        $this->seed(['local' => ['type' => 'local'], 'saml-a' => self::saml()]);

        $html = $this->editPage('saml-a');

        $copyable = self::copyable($html);
        $this->assertCount(2, $copyable);
        $this->assertStringEndsWith('user/sign-in/saml-a/metadata', $copyable[0], 'the entity ID');
        $this->assertStringEndsWith('user/sign-in/saml-a/callback', $copyable[1], 'the ACS URL');
        $this->assertStringContainsString(htmlspecialchars(_t('SIGN_IN_SAML_SP_ENTITY_ID'), ENT_QUOTES), $html);
        $this->assertStringContainsString(htmlspecialchars(_t('SIGN_IN_SAML_ACS_URL'), ENT_QUOTES), $html);
        $this->assertMatchesRegularExpression('#<input[^>]*yw-sign-in-copy[^>]*readonly[^>]*onfocus="this.select\(\)"#', $html, 'each can be selected to copy');
    }

    public function testANonPublishingTypeHasNothingToCopy(): void
    {
        $this->seed(['local' => ['type' => 'local'], 'acme' => $this->oidc()]);

        $this->assertSame([], self::copyable($this->editPage('acme')));
    }

    public function testCopyableValuesAreEscaped(): void
    {
        $this->installProviders([], self::publishingType('fakemeta', false));
        $this->seed(['local' => ['type' => 'local'], 'meta' => ['type' => 'fakemeta', 'label' => 'Meta']]);

        $html = $this->editPage('meta');

        $this->assertSame(['a&quot;&lt;b&gt;&amp;c'], self::copyable($html));
        $this->assertStringContainsString('Key &lt;i&gt;one&lt;/i&gt;', $html);
        $this->assertStringNotContainsString('<i>one</i>', $html);
    }

    public function testAProviderThatCannotBeBuiltStillHasItsEditPage(): void
    {
        $this->installProviders([], self::publishingType('fakebroken', true));
        $this->seed(['local' => ['type' => 'local'], 'broken' => ['type' => 'fakebroken', 'label' => 'Broken']]);

        $html = $this->editPage('broken');

        $this->assertSame([], self::copyable($html));
        $this->assertStringContainsString('name="label"', $html, 'the form is there to fix it');
    }

    public function testMultilineSettingsAreTextareas(): void
    {
        $xml = "<md:EntityDescriptor>\n  <md:IDPSSODescriptor/>\n</md:EntityDescriptor>";
        $this->seed(['local' => ['type' => 'local'], 'saml-a' => self::saml(['metadata_xml' => $xml, 'idp_certificate' => "-----BEGIN CERTIFICATE-----\nMIIB\n-----END CERTIFICATE-----"])]);

        $html = $this->editPage('saml-a');

        $this->assertMatchesRegularExpression('#<textarea[^>]*name="connection\[metadata_xml\]"[^>]*>' . preg_quote(htmlspecialchars($xml), '#') . '</textarea>#', $html);
        $this->assertMatchesRegularExpression('#<textarea[^>]*name="connection\[idp_certificate\]"[^>]*>-----BEGIN CERTIFICATE-----\nMIIB\n#', $html);
        $this->assertMatchesRegularExpression('#<textarea[^>]*name="connection\[sp_certificate\]"#', $html);
        $this->assertMatchesRegularExpression('#<input[^>]*name="connection\[idp_entity_id\]"#', $html, 'a one-line setting keeps its input');
    }

    public function testAPostedMultilineValueKeepsItsLineBreaks(): void
    {
        $this->seed(['local' => ['type' => 'local'], 'saml-a' => self::saml()]);
        $key = "-----BEGIN PRIVATE KEY-----\r\nMIIEvQ\r\nAAAA\r\n-----END PRIVATE KEY-----\r\n";

        $this->assertSame([], $this->editor->update('saml-a', ['connection' => ['metadata_xml' => "<a>\r\n  <b/>\r\n</a>\r\n", 'sp_private_key' => $key]]));

        $connection = $this->written()['saml-a']['connection'];
        $this->assertSame("<a>\n  <b/>\n</a>", $connection['metadata_xml'], 'what a browser posts (CRLF) is stored with plain line breaks');
        $this->assertSame("-----BEGIN PRIVATE KEY-----\nMIIEvQ\nAAAA\n-----END PRIVATE KEY-----", $connection['sp_private_key']);
    }

    public function testTheSecretTextareaNeverContainsTheStoredKey(): void
    {
        $key = "-----BEGIN PRIVATE KEY-----\nMIIEvQstoredkeybody\n-----END PRIVATE KEY-----";
        $this->seed(['local' => ['type' => 'local'], 'saml-a' => self::saml(['sp_private_key' => $key])]);

        $html = $this->editPage('saml-a');

        $this->assertStringNotContainsString('MIIEvQstoredkeybody', $html);
        $this->assertMatchesRegularExpression('#<textarea[^>]*name="connection\[sp_private_key\]"[^>]*placeholder="' . preg_quote(htmlspecialchars(_t('SIGN_IN_ADMIN_SECRET_KEPT'), ENT_QUOTES), '#') . '"[^>]*></textarea>#', $html);

        $this->assertSame([], $this->editor->update('saml-a', ['connection' => ['sp_private_key' => '']]));
        $this->assertSame($key, $this->written()['saml-a']['connection']['sp_private_key'], 'a blank one keeps it');
    }

    public function testAnUnencryptedLdapConnectionIsWarnedOnTheEditScreen(): void
    {
        $ldap = fn (array $connection): array => ['type' => 'ldap', 'label' => 'Directory', 'enabled' => true, 'connection' => $connection + ['bind_dn_template' => 'uid={login},dc=acme']];
        $this->seed([
            'local' => ['type' => 'local'],
            'plain' => $ldap(['uri' => ' LDAP://directory.acme:389']),
            'tls' => $ldap(['uri' => 'ldap://directory.acme:389', 'start_tls' => true]),
            'ldaps' => $ldap(['uri' => 'ldaps://directory.acme:636']),
        ]);
        $warning = '#<div class="yw-alert yw-alert--warning yw-sign-in-admin__unencrypted"[^>]*>#';

        $html = $this->editPage('plain');
        $this->assertMatchesRegularExpression($warning, $html, 'ldap:// without StartTLS sends passwords in clear');
        $this->assertStringContainsString(htmlspecialchars(_t('SIGN_IN_LDAP_UNENCRYPTED'), ENT_QUOTES), $html);
        $this->assertDoesNotMatchRegularExpression($warning, $this->editPage('tls'), 'StartTLS encrypts it');
        $this->assertDoesNotMatchRegularExpression($warning, $this->editPage('ldaps'), 'ldaps:// encrypts it');
        $this->assertMatchesRegularExpression('#<input[^>]*type="text"[^>]*name="connection\[uri\]"#', $html, 'an ldap:// address is not a web address');
        $this->assertStringNotContainsString('user/sign-in/plain/callback', $html, 'a directory sends nobody back: there is no callback to give it');
        $this->assertMatchesRegularExpression('#<input[^>]*name="connection\[search_password\]"[^>]*type="password"|<input[^>]*type="password"[^>]*name="connection\[search_password\]"#', $html);
    }

    public function testAnLdapAddressIsSavedAsTyped(): void
    {
        $this->seed(['local' => ['type' => 'local'], 'dir' => ['type' => 'ldap', 'label' => 'Directory', 'connection' => ['uri' => 'ldaps://old', 'bind_dn_template' => 'uid={login},dc=acme']]]);

        $this->assertSame([], $this->editor->update('dir', ['connection' => ['uri' => 'ldap://yeswiki-ldap:1389', 'start_tls' => '1']]));

        $this->assertSame('ldap://yeswiki-ldap:1389', $this->written()['dir']['connection']['uri']);
        $this->assertTrue($this->written()['dir']['connection']['start_tls']);
    }

    /** A type whose providers publish one value to copy, with characters that must be escaped; or one whose providers cannot be built. */
    private static function publishingType(string $name, bool $broken): ProviderType
    {
        return new class($name, $broken) implements ProviderType {
            public function __construct(private string $name, private bool $broken)
            {
            }

            public function name(): string
            {
                return $this->name;
            }

            public function label(): string
            {
                return $this->name;
            }

            public function connectionSettings(): array
            {
                return [];
            }

            public function defaultAttributes(): array
            {
                return [];
            }

            public function create(ProviderConfig $config): IdentityProvider
            {
                if ($this->broken) {
                    throw new \RuntimeException('half configured');
                }

                return new class($config) extends FakeIdentity implements PublishesMetadata {
                    public function metadataResponse(): Response
                    {
                        return new Response('<m/>');
                    }

                    public function copyableValues(): array
                    {
                        return ['Key <i>one</i>' => 'a"<b>&c'];
                    }
                };
            }
        };
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
        $this->assertNotNull($user);
        $services->get(AccountLinks::class)->link($user, 'acme', 'sub-1');

        self::actAs('WikiAdmin');
        $html = (string)$services->get(MarkdownFormatterService::class)->format('{{userstable}}');

        $this->assertStringContainsString(self::USER, $html);
        $this->assertStringContainsString('Acme Single Sign-On', $html);
    }
}
