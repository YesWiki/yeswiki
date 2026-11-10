<?php

namespace YesWiki\Identity\Service\SignIn\Type;

use YesWiki\Identity\Service\SignIn\OidcDiscovery;
use YesWiki\Identity\SignIn\Contract\IdentityProvider;
use YesWiki\Identity\SignIn\Contract\ProviderConfig;
use YesWiki\Identity\SignIn\Contract\ProviderType;
use YesWiki\Identity\SignIn\Provider\OidcProvider;
use YesWiki\Kernel\Component\Setting;

/**
 * OpenID Connect, and plain OAuth2 with explicit endpoints.
 * `scope_separator` and `legacy_callback` are read too, but only an upgrade from loginsso writes them: the screen does not offer them.
 */
final class OidcType implements ProviderType
{
    public const NAME = 'oidc';

    public function __construct(private readonly OidcDiscovery $discovery)
    {
    }

    public function name(): string
    {
        return self::NAME;
    }

    public function label(): string
    {
        return _t('SIGN_IN_TYPE_OIDC');
    }

    public function connectionSettings(): array
    {
        return [
            Setting::url('discovery_url')->label(_t('SIGN_IN_OIDC_DISCOVERY_URL'))->hint(_t('SIGN_IN_OIDC_DISCOVERY_URL_HINT')),
            Setting::url('authorize_url')->label(_t('SIGN_IN_OIDC_AUTHORIZE_URL')),
            Setting::url('token_url')->label(_t('SIGN_IN_OIDC_TOKEN_URL')),
            Setting::url('userinfo_url')->label(_t('SIGN_IN_OIDC_USERINFO_URL')),
            Setting::text('issuer')->label(_t('SIGN_IN_OIDC_ISSUER'))->hint(_t('SIGN_IN_OIDC_ISSUER_HINT')),
            Setting::url('jwks_uri')->label(_t('SIGN_IN_OIDC_JWKS_URI')),
            Setting::text('client_id')->label(_t('SIGN_IN_OIDC_CLIENT_ID')),
            Setting::secret('client_secret')->label(_t('SIGN_IN_OIDC_CLIENT_SECRET')),
            Setting::text('scopes')->label(_t('SIGN_IN_OIDC_SCOPES'))->default('openid email profile'),
        ];
    }

    public function defaultAttributes(): array
    {
        return ['id' => 'sub', 'email' => 'email', 'first_name' => 'given_name', 'last_name' => 'family_name', 'groups' => 'groups'];
    }

    public function create(ProviderConfig $config): IdentityProvider
    {
        return new OidcProvider($config, $this->discovery);
    }
}
