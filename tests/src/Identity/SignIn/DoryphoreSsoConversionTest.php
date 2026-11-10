<?php

namespace YesWiki\Test\Identity\SignIn;

use PHPUnit\Framework\TestCase;
use YesWiki\Identity\Service\SignIn\DoryphoreSsoConversion;
use YesWiki\Identity\SignIn\SsoConversionResult;

/** Doryphore's loginsso, loginldap and logincas settings become providers, one row of spec section 11 at a time. */
class DoryphoreSsoConversionTest extends TestCase
{
    private const NOW = '2026-11-11 10:00:00';

    /**
     * @param array<string, mixed> $config
     * @param list<string>         $tools
     */
    private function convert(array $config, array $tools, string $baseUrl = 'https://w/?'): SsoConversionResult
    {
        return (new DoryphoreSsoConversion())->convert($config, $tools, $baseUrl, new \DateTimeImmutable(self::NOW));
    }

    /**
     * A loginsso provider as its documentation writes it.
     *
     * @param array<string, mixed> $over
     *
     * @return array<string, mixed>
     */
    private function ssoEntry(array $over = []): array
    {
        return array_replace([
            'auth_type' => 'oauth2',
            'auth_options' => [
                'clientId' => 'the-client',
                'clientSecret' => 'sso-client-secret-value',
                'urlAuthorize' => 'https://s/auth',
                'urlAccessToken' => 'https://s/token',
                'urlResourceOwnerDetails' => 'https://s/userinfo',
                'scopes' => ['openid', 'custom_scope'],
                'scopeSeparator' => ',',
                'addFinalEqual' => true,
            ],
            'id_sso_field' => 'sub',
            'email_sso_field' => 'mail',
            'groups_sso_field' => 'roles',
            'groups_sso_mapping' => ['g' => 'w'],
            'create_user_from' => '#[given_name] #[family_name]',
            'button_style' => ['button_label' => 'My Auth Server', 'button_class' => 'btn btn-default', 'button_icon' => 'glyphicon glyphicon-log-in'],
        ], $over);
    }

    /**
     * @param array<string, mixed> $over
     *
     * @return array<string, mixed>
     */
    private function ldap(array $over = []): array
    {
        return array_replace(['ldap_host' => 'h', 'ldap_port' => '389', 'ldap_base' => 'ou=u,dc=x'], $over);
    }

    /**
     * @param array<string, mixed> $over
     *
     * @return array<string, mixed>
     */
    private function cas(array $over = []): array
    {
        return array_replace(['cas_host' => 'h', 'cas_port' => 443, 'cas_context' => '/cas', 'cas_server_ca_cert_path' => '/etc/ssl/cas-chain.pem'], $over);
    }

    /** @return array<string, mixed> */
    private function allThree(): array
    {
        return ['sso_config' => ['providers' => [$this->ssoEntry()]]] + $this->ldap() + $this->cas();
    }

    private function convertLdap(): SsoConversionResult
    {
        return $this->convert($this->ldap(), ['loginldap']);
    }

    public function testLoginssoBecomesOidcWithExplicitEndpoints(): void
    {
        $r = $this->convert(['sso_config' => ['providers' => [$this->ssoEntry()]]], ['loginsso']);
        $p = $r->providers['my-auth-server'];
        $this->assertSame('oidc', $p['type']);
        $this->assertSame('https://s/auth', $p['connection']['authorize_url']);
        $this->assertSame('https://s/token', $p['connection']['token_url']);
        $this->assertSame('https://s/userinfo', $p['connection']['userinfo_url']);
        $this->assertSame('the-client', $p['connection']['client_id']);
        $this->assertSame('sso-client-secret-value', $p['connection']['client_secret']);
        $this->assertSame(['openid', 'custom_scope'], $p['connection']['scopes']);
        $this->assertSame(',', $p['connection']['scope_separator']);
        $this->assertArrayNotHasKey('discovery_url', $p['connection']);
        $this->assertSame('sub', $p['attributes']['id']);
        $this->assertTrue($p['match_by_email']);
        $this->assertSame([['provider' => 'g', 'wiki' => 'w']], $p['group_mapping']);
    }

    public function testLoginssoDefaultsAreKept(): void
    {
        $entry = $this->ssoEntry();
        unset($entry['auth_options']['scopes'], $entry['auth_options']['scopeSeparator'], $entry['id_sso_field'], $entry['email_sso_field'], $entry['groups_sso_field']);
        $p = $this->convert(['sso_config' => ['providers' => [$entry]]], ['loginsso'])->providers['my-auth-server'];

        $this->assertSame(['openid'], $p['connection']['scopes'], 'loginsso asked for openid alone when no scope was set');
        $this->assertArrayNotHasKey('scope_separator', $p['connection']);
        $this->assertSame('id', $p['attributes']['id']);
        $this->assertSame('email', $p['attributes']['email']);
        $this->assertArrayNotHasKey('groups', $p['attributes']);
    }

    public function testFinalEqualKeepsTheRegisteredCallback(): void
    {
        $with = $this->convert(['sso_config' => ['providers' => [$this->ssoEntry()]]], ['loginsso']);
        $this->assertSame('https://w/?api/auth_sso/callback=', $with->providers['my-auth-server']['connection']['legacy_callback']);

        $entry = $this->ssoEntry();
        unset($entry['auth_options']['addFinalEqual']);
        $unset = $this->convert(['sso_config' => ['providers' => [$entry]]], ['loginsso'], 'https://w/index.php?');
        $this->assertSame('https://w/?api/auth_sso/callback=', $unset->providers['my-auth-server']['connection']['legacy_callback'], 'no addFinalEqual meant true');

        $entry['auth_options']['addFinalEqual'] = false;
        $without = $this->convert(['sso_config' => ['providers' => [$entry]]], ['loginsso'], 'https://w/sub/?');
        $this->assertSame('https://w/sub/?api/auth_sso/callback', $without->providers['my-auth-server']['connection']['legacy_callback']);
    }

    public function testTheSsoFieldsBecomeTheAttributes(): void
    {
        $p = $this->convert(['sso_config' => ['providers' => [$this->ssoEntry()]]], ['loginsso'])->providers['my-auth-server'];

        $this->assertSame('sub', $p['attributes']['id']);
        $this->assertSame('mail', $p['attributes']['email']);
        $this->assertSame('roles', $p['attributes']['groups']);
    }

    public function testCreateUserFromBecomesTheTemplate(): void
    {
        $p = $this->convert(['sso_config' => ['providers' => [$this->ssoEntry()]]], ['loginsso'])->providers['my-auth-server'];

        $this->assertTrue($p['create_accounts']);
        $this->assertSame('#[given_name] #[family_name]', $p['account_name_template']);
    }

    public function testNoCreateUserFromTurnsCreationOff(): void
    {
        $entry = $this->ssoEntry();
        unset($entry['create_user_from']);
        $p = $this->convert(['sso_config' => ['providers' => [$entry]]], ['loginsso'])->providers['my-auth-server'];

        $this->assertFalse($p['create_accounts']);
        $this->assertArrayNotHasKey('account_name_template', $p);
    }

    public function testNamesComeFromBazarMappingElseStandardClaims(): void
    {
        $mapped = $this->ssoEntry(['bazar_mapping' => ['fields' => ['bf_prenom' => 'first', 'bf_nom' => 'last', 'bf_email' => 'mail']]]);
        $p = $this->convert(['sso_config' => ['providers' => [$mapped]]], ['loginsso'])->providers['my-auth-server'];
        $this->assertSame('first', $p['attributes']['first_name']);
        $this->assertSame('last', $p['attributes']['last_name']);

        $p = $this->convert(['sso_config' => ['providers' => [$this->ssoEntry()]]], ['loginsso'])->providers['my-auth-server'];
        $this->assertSame('given_name', $p['attributes']['first_name']);
        $this->assertSame('family_name', $p['attributes']['last_name']);
    }

    public function testEveryGroupRowIsKept(): void
    {
        $entry = $this->ssoEntry(['groups_sso_mapping' => ['staff' => 'Editors', 'boss' => 'admins', 'both' => ['A', 'B']]]);
        $p = $this->convert(['sso_config' => ['providers' => [$entry]]], ['loginsso'])->providers['my-auth-server'];

        $this->assertSame([
            ['provider' => 'staff', 'wiki' => 'Editors'],
            ['provider' => 'boss', 'wiki' => 'admins'],
            ['provider' => 'both', 'wiki' => 'A'],
            ['provider' => 'both', 'wiki' => 'B'],
        ], $p['group_mapping']);
    }

    public function testTheButtonLabelIsTheLabelAndItsStyleIsDropped(): void
    {
        $p = $this->convert(['sso_config' => ['providers' => [$this->ssoEntry()]]], ['loginsso'])->providers['my-auth-server'];

        $this->assertSame('My Auth Server', $p['label']);
        $this->assertArrayNotHasKey('button_style', $p);
        $this->assertStringNotContainsString('glyphicon', var_export($p, true));
    }

    public function testIdentifiersNeverCollide(): void
    {
        $config = ['sso_config' => ['providers' => [
            $this->ssoEntry(['button_style' => ['button_label' => 'Local']]),
            $this->ssoEntry(['button_style' => ['button_label' => '***']]),
            $this->ssoEntry(['button_style' => []]),
            $this->ssoEntry(['button_style' => ['button_label' => 'LDAP']]),
            $this->ssoEntry(['button_style' => ['button_label' => 'Local']]),
        ]]] + $this->ldap();
        $r = $this->convert($config, ['loginsso', 'loginldap']);

        $this->assertSame(['local', 'local-2', 'oidc', 'oidc-2', 'ldap-2', 'local-3', 'ldap'], array_keys($r->providers));
        $this->assertSame('local', $r->providers['local']['type']);
        $this->assertSame('ldap', $r->providers['ldap']['type']);
        $this->assertSame(['local-2', 'oidc', 'oidc-2', 'ldap-2', 'local-3'], $r->loginssoIds);
    }

    public function testLdapWithBase(): void
    {
        $p = $this->convertLdap()->providers['ldap'];

        $this->assertSame('ldap', $p['type']);
        $this->assertSame('ldap://h:389', $p['connection']['uri']);
        $this->assertSame('uid={login},ou=u,dc=x', $p['connection']['bind_dn_template']);
    }

    public function testLdapWithOrganisationAndGroup(): void
    {
        $config = ['ldap_host' => 'h', 'ldap_port' => '389', 'ldap_organisation' => 'o', 'ldap_group' => 'g'];
        $p = $this->convert($config, ['loginldap'])->providers['ldap'];

        $this->assertSame('uid={login},ou=g,o=o', $p['connection']['bind_dn_template']);
    }

    public function testLdapFullUriIsKept(): void
    {
        $p = $this->convert(['ldap_host' => 'ldaps://h:636', 'ldap_port' => '636'], ['loginldap'])->providers['ldap'];
        $this->assertSame('ldaps://h:636', $p['connection']['uri']);

        $p = $this->convert(['ldap_host' => 'ldaps://h', 'ldap_port' => '636'], ['loginldap'])->providers['ldap'];
        $this->assertSame('ldaps://h:636', $p['connection']['uri'], 'a scheme with no port gets the configured one');
    }

    public function testLdapReadsTheDirectoryAttributesAndNoGroups(): void
    {
        $p = $this->convertLdap()->providers['ldap'];

        $this->assertSame(['id' => 'uid', 'email' => 'mail', 'first_name' => 'givenName', 'last_name' => 'sn'], $p['attributes']);
        $this->assertSame([], $p['group_mapping']);
    }

    public function testLdapAndCasMatchByEmailAndNameForTwelveMonths(): void
    {
        $r = $this->convert($this->ldap() + $this->cas(), ['loginldap', 'logincas']);
        foreach (['ldap', 'cas'] as $id) {
            $p = $r->providers[$id];
            $this->assertTrue($p['create_accounts'], $id);
            $this->assertTrue($p['match_by_email'], $id);
            $this->assertSame('2027-11-11', $p['match_by_name_until'], $id);
        }
        $sso = $this->convert(['sso_config' => ['providers' => [$this->ssoEntry()]]], ['loginsso'])->providers['my-auth-server'];
        $this->assertArrayNotHasKey('match_by_name_until', $sso, 'loginsso never matched by name');
    }

    public function testCasFields(): void
    {
        $p = $this->convert($this->cas(), ['logincas'])->providers['cas'];

        $this->assertSame('cas', $p['type']);
        $this->assertSame('https://h:443/cas', $p['connection']['server_url']);
        $this->assertSame('2.0', $p['connection']['version']);
        $this->assertSame('/etc/ssl/cas-chain.pem', $p['connection']['ca_cert']);
    }

    public function testCasDefaultsAreDoryphores(): void
    {
        $p = $this->convert(['cas_host' => 'cas.example.org'], ['logincas'])->providers['cas'];

        $this->assertSame('https://cas.example.org:443/cas', $p['connection']['server_url']);
        $this->assertArrayNotHasKey('ca_cert', $p['connection'], 'no path now means the system authorities');
        $this->assertSame('https://cas.example.org:8443/sso', $this->convert(['cas_host' => 'cas.example.org', 'cas_port' => '8443', 'cas_context' => 'sso'], ['logincas'])->providers['cas']['connection']['server_url']);
    }

    public function testCasAttributesAreTheOnesLogincasRead(): void
    {
        $p = $this->convert($this->cas(), ['logincas'])->providers['cas'];

        $this->assertSame('name', $p['attributes']['id']);
        $this->assertSame('mail', $p['attributes']['email']);
        $this->assertArrayNotHasKey('first_name', $p['attributes']);
    }

    public function testCasNamesFromItsBazarMapping(): void
    {
        $mapping = [['id' => '1', 'consent_question' => 'OK?', 'fields' => ['bf_nom' => 'field_last_name', 'bf_prenom' => 'field_first_name', 'bf_mail' => 'mail']]];
        $p = $this->convert($this->cas(['cas_bazar_mapping' => $mapping]), ['logincas'])->providers['cas'];

        $this->assertSame('field_first_name', $p['attributes']['first_name']);
        $this->assertSame('field_last_name', $p['attributes']['last_name']);
    }

    public function testOnlyTheOneInChargeIsOn(): void
    {
        $r = $this->convert($this->allThree(), ['loginsso', 'loginldap', 'logincas']);
        $this->assertSame('loginsso', $r->inCharge);
        $this->assertTrue($r->providers['my-auth-server']['enabled']);
        $this->assertFalse($r->providers['ldap']['enabled']);
        $this->assertFalse($r->providers['cas']['enabled']);
    }

    public function testLdapComesBeforeCas(): void
    {
        $r = $this->convert($this->allThree(), ['loginldap', 'logincas']);

        $this->assertSame('loginldap', $r->inCharge, 'loginsso is configured but was not installed');
        $this->assertFalse($r->providers['my-auth-server']['enabled']);
        $this->assertTrue($r->providers['ldap']['enabled']);
        $this->assertFalse($r->providers['cas']['enabled']);

        $this->assertSame('logincas', $this->convert($this->allThree(), ['logincas', 'tags'])->inCharge);
    }

    public function testLocalIsOffWhenAnExtensionWasInCharge(): void
    {
        $this->assertFalse($this->convertLdap()->providers['local']['enabled']);
        $this->assertFalse($this->convert($this->cas(), ['logincas'])->providers['local']['enabled']);
        $this->assertFalse($this->convert(['sso_config' => ['providers' => [$this->ssoEntry()]]], ['loginsso'])->providers['local']['enabled']);
    }

    public function testLocalStaysOnWhenNothingConverts(): void
    {
        $this->assertTrue($this->convert([], [])->providers['local']['enabled'] ?? true);
        $nothing = $this->convert(['wakka_name' => 'x', 'sso_config' => ['providers' => []]], ['loginsso', 'loginldap', 'logincas']);
        $this->assertSame([], $nothing->providers);
        $this->assertNull($nothing->inCharge);
    }

    public function testLocalStaysOnWhenNoConvertedExtensionWasInstalled(): void
    {
        $r = $this->convert($this->ldap(), []);

        $this->assertNull($r->inCharge);
        $this->assertTrue($r->providers['local']['enabled']);
        $this->assertFalse($r->providers['ldap']['enabled']);
    }

    public function testAnExistingSectionIsKept(): void
    {
        $existing = ['local' => ['type' => 'local', 'enabled' => true], 'ldap' => ['type' => 'ldap', 'enabled' => false, 'connection' => ['uri' => 'ldap://other']]];
        $r = $this->convert(['sso_providers' => $existing] + $this->ldap(), ['loginldap']);

        $this->assertSame($existing['local'], $r->providers['local']);
        $this->assertSame($existing['ldap'], $r->providers['ldap']);
        $this->assertSame('ldap://h:389', $r->providers['ldap-2']['connection']['uri']);
        $this->assertFalse($r->providers['ldap-2']['enabled'], 'what the administrator set up already is in charge');
        $this->assertNull($r->inCharge);
    }

    public function testKeysAreRemovedAndDropsAreSaid(): void
    {
        $config = [
            'wakka_name' => 'kept',
            'sso_config' => [
                'bazar_user_entry_id' => 1000,
                'login_username_initials' => false,
                'providers' => [$this->ssoEntry(['bazar_mapping' => ['fields' => ['bf_nom' => 'family_name'], 'fields_transformed' => []]])],
            ],
        ] + $this->ldap(['ldap_group' => 'g', 'ldap_organisation' => 'o']) + $this->cas(['cas_server_ca_cert_path' => '', 'cas_bazar_mapping' => [['id' => '1', 'fields' => ['bf_nom' => 'sn']]]]);
        $r = $this->convert($config, ['loginsso', 'loginldap', 'logincas']);

        $this->assertSame([
            'sso_config', 'ldap_host', 'ldap_port', 'ldap_base', 'ldap_group', 'ldap_organisation',
            'cas_host', 'cas_port', 'cas_context', 'cas_server_ca_cert_path', 'cas_bazar_mapping',
        ], $r->removedKeys);
        $said = implode("\n", $r->messages);
        $this->assertStringContainsString('bazar_mapping', $said);
        $this->assertStringContainsString('cas_bazar_mapping', $said);
        $this->assertStringContainsString('login_username_initials', $said);
        $this->assertStringContainsString('bazar_user_entry_id', $said);
        $this->assertStringContainsString('fields_transformed', $said);
        $this->assertStringContainsString('/api/participant', $said);
        $this->assertStringContainsString('ca_cert', $said);
        $this->assertStringContainsString('my-auth-server', $said);
        $this->assertStringContainsString('local', $said);
    }

    public function testTheCaNoteIsOnlySaidWhenThePathWasEmpty(): void
    {
        $this->assertStringNotContainsString('system', implode("\n", $this->convert($this->cas(), ['logincas'])->messages));
        $this->assertStringContainsString('system', implode("\n", $this->convert($this->cas(['cas_server_ca_cert_path' => '']), ['logincas'])->messages));
    }

    public function testNothingSecretIsSaid(): void
    {
        $said = implode("\n", $this->convert($this->allThree(), ['loginsso', 'loginldap', 'logincas'])->messages);

        $this->assertStringNotContainsString('sso-client-secret-value', $said);
        $this->assertStringNotContainsString('the-client', $said);
    }

    public function testLoginssoIdsNameEveryLoginssoProvider(): void
    {
        $this->assertSame(['my-auth-server'], $this->convert($this->allThree(), ['loginsso'])->loginssoIds);
        $this->assertSame([], $this->convertLdap()->loginssoIds);
    }
}
