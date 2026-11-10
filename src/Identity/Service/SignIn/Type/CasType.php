<?php

namespace YesWiki\Identity\Service\SignIn\Type;

use YesWiki\Identity\SignIn\Contract\IdentityProvider;
use YesWiki\Identity\SignIn\Contract\ProviderConfig;
use YesWiki\Identity\SignIn\Contract\ProviderType;
use YesWiki\Identity\SignIn\Provider\CasProvider;
use YesWiki\Kernel\Component\Setting;
use YesWiki\Kernel\Service\SessionRequirement;
use YesWiki\Kernel\Service\UrlFormatter;

/**
 * CAS 2.0 and 3.0. The service the server is told about is the provider's callback, which a server restricting its services must allow.
 */
final class CasType implements ProviderType
{
    public const NAME = 'cas';

    public function __construct(
        private readonly SessionRequirement $sessions,
        private readonly UrlFormatter $urls,
    ) {
    }

    public function name(): string
    {
        return self::NAME;
    }

    public function label(): string
    {
        return _t('SIGN_IN_TYPE_CAS');
    }

    public function connectionSettings(): array
    {
        return [
            Setting::url('server_url')->label(_t('SIGN_IN_CAS_SERVER_URL'))->hint(_t('SIGN_IN_CAS_SERVER_URL_HINT'))->required(),
            Setting::choice('version', ['3.0' => 'CAS 3.0', '2.0' => 'CAS 2.0'])->label(_t('SIGN_IN_CAS_VERSION'))->default('3.0'),
            Setting::text('ca_cert')->label(_t('SIGN_IN_CAS_CA_CERT'))->hint(_t('SIGN_IN_CAS_CA_CERT_HINT')),
        ];
    }

    public function defaultAttributes(): array
    {
        return ['id' => 'user', 'email' => 'mail', 'first_name' => 'givenName', 'last_name' => 'sn', 'groups' => 'groups'];
    }

    public function create(ProviderConfig $config): IdentityProvider
    {
        return new CasProvider($config, $this->sessions, $this->urls->getBaseUrl() . '/');
    }
}
