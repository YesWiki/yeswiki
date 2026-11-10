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
    public function testTheThirteenReasons(): void
    {
        $this->assertSame(
            ['provider_unreachable', 'provider_rejected', 'invalid_response', 'configuration_invalid',
                'missing_attribute', 'email_conflict', 'linked_elsewhere', 'no_account', 'provider_disabled',
                'account_creation_failed', 'wrong_credentials', 'too_many_attempts', 'internal_error'],
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
        $this->assertFalse($c->isUpgradedDirectory());
        $this->assertTrue(ProviderConfig::fromArray('x', ['type' => 'ldap', 'match_by_name_until' => '2027-11-10'])->isUpgradedDirectory());
    }

    public function testHandshakeRoundTripsItsValues(): void
    {
        $h = new Handshake('acme', '/back', 'https://w/cb', HandshakeMode::Test, 1000);
        $h->set('state', 'abc');
        $again = Handshake::fromPayload($h->toPayload());
        $this->assertSame('abc', $again->get('state'));
        $this->assertSame(HandshakeMode::Test, $again->mode);
        $this->assertNull(Handshake::fromPayload(['p' => 'only']));
    }

    public function testSecretSettingIsMarked(): void
    {
        $this->assertSame('secret', Setting::secret('api_key')->toArray()['type']);
    }
}
