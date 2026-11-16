<?php

namespace YesWiki\Test\Identity\SignIn;

use PHPUnit\Framework\TestCase;
use YesWiki\Identity\SignIn\Contract\FailureReason;
use YesWiki\Identity\SignIn\Contract\Handshake;
use YesWiki\Identity\SignIn\Contract\HandshakeMode;
use YesWiki\Identity\SignIn\Contract\ProviderConfig;
use YesWiki\Kernel\Component\Setting;

class ContractTest extends TestCase
{
    public function testTheTwelveReasons(): void
    {
        $this->assertSame(
            ['provider_unreachable', 'provider_rejected', 'invalid_response', 'configuration_invalid',
                'missing_attribute', 'email_conflict', 'linked_elsewhere', 'no_account', 'provider_disabled',
                'account_creation_failed', 'wrong_credentials', 'internal_error'],
            array_column(FailureReason::cases(), 'value')
        );
    }

    public function testConfigAppliesDefaultsAndTypeAttributes(): void
    {
        $c = ProviderConfig::fromArray('acme', ['type' => 'oidc', 'attributes' => ['email' => 'mail']], ['id' => 'sub', 'email' => 'email']);
        $this->assertSame('acme', $c->label);
        $this->assertTrue($c->enabled);
        $this->assertTrue($c->createAccounts);
        $this->assertFalse($c->matchByEmail);
        $this->assertSame(['id' => 'sub', 'email' => 'mail'], $c->attributes);
        $this->assertFalse($c->matchByName);
        $this->assertTrue(ProviderConfig::fromArray('x', ['type' => 'ldap', 'match_by_name' => true])->matchByName);
    }

    /** A flag written as text is read as the screen reads it: "false" is not true, which would turn match by email on. */
    public function testFlagsWrittenAsTextAreReadAsWritten(): void
    {
        foreach (['false', '0', 'no', 'off', '', 0, false] as $off) {
            $c = ProviderConfig::fromArray('acme', ['type' => 'oidc', 'enabled' => $off, 'create_accounts' => $off, 'match_by_email' => $off, 'match_by_name' => $off]);
            $said = var_export($off, true);
            $this->assertFalse($c->enabled, "enabled $said");
            $this->assertFalse($c->createAccounts, "create_accounts $said");
            $this->assertFalse($c->matchByEmail, "match_by_email $said");
            $this->assertFalse($c->matchByName, "match_by_name $said");
        }
        foreach (['true', '1', 'yes', 'on', 1, true] as $on) {
            $c = ProviderConfig::fromArray('acme', ['type' => 'oidc', 'enabled' => $on, 'create_accounts' => $on, 'match_by_email' => $on]);
            $this->assertTrue($c->enabled && $c->createAccounts && $c->matchByEmail, var_export($on, true));
        }
        $c = ProviderConfig::fromArray('acme', ['type' => 'oidc', 'match_by_email' => 'perhaps', 'enabled' => null]);
        $this->assertFalse($c->matchByEmail, 'what is not a flag does not open anything');
        $this->assertTrue($c->enabled, 'null is as good as absent');
    }

    /** A hand-written attribute or extra field that is not a name is left out, rather than reaching a provider as one. */
    public function testWhatIsNotANameIsLeftOut(): void
    {
        $c = ProviderConfig::fromArray('acme', [
            'type' => 'ldap',
            'attributes' => ['email' => ['mail'], 'name' => 'cn'],
            'extra_fields' => ['bf_phone' => 'telephoneNumber', 'bf_team' => 42],
        ], ['email' => 'mail']);

        $this->assertSame(['email' => 'mail', 'name' => 'cn'], $c->attributes, 'the type default stays when the written one is no name');
        $this->assertSame(['bf_phone' => 'telephoneNumber'], $c->extraFields);
    }

    public function testHandshakeRoundTripsItsValues(): void
    {
        $h = new Handshake('acme', '/back', 'https://w/cb', HandshakeMode::Test, 1000);
        $h->set('state', 'abc');
        $again = Handshake::fromPayload($h->toPayload());
        $this->assertNotNull($again);
        $this->assertSame('abc', $again->get('state'));
        $this->assertSame(HandshakeMode::Test, $again->mode);
        $this->assertNull(Handshake::fromPayload(['p' => 'only']));
    }

    public function testSecretSettingIsMarked(): void
    {
        $this->assertSame('secret', Setting::secret('api_key')->toArray()['type']);
    }

    public function testAMultilineSettingKeepsItsKind(): void
    {
        $this->assertSame(['type' => 'text', 'multiline' => true], Setting::text('metadata_xml')->multiline()->toArray());
        $this->assertSame(['type' => 'secret', 'multiline' => true], Setting::secret('private_key')->multiline()->toArray());
        $this->assertArrayNotHasKey('multiline', Setting::text('name')->toArray());
    }
}
