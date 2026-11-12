<?php

namespace YesWiki\Identity\Service\SignIn\Type;

use YesWiki\Identity\SignIn\Contract\IdentityProvider;
use YesWiki\Identity\SignIn\Contract\ProviderConfig;
use YesWiki\Identity\SignIn\Contract\ProviderType;
use YesWiki\Identity\SignIn\Provider\SamlProvider;
use YesWiki\Kernel\Component\Setting;
use YesWiki\Kernel\Service\UrlFormatter;

/**
 * SAML 2.0, the wiki being the service provider.
 * Each provider is its own service provider: its entity ID is the address of its metadata, and its callback is where responses are posted.
 */
final class SamlType implements ProviderType
{
    public const NAME = 'saml';

    public function __construct(private readonly UrlFormatter $urls)
    {
    }

    public function name(): string
    {
        return self::NAME;
    }

    public function label(): string
    {
        return _t('SIGN_IN_TYPE_SAML');
    }

    public function connectionSettings(): array
    {
        return [
            Setting::url('metadata_url')->label(_t('SIGN_IN_SAML_METADATA_URL'))->hint(_t('SIGN_IN_SAML_METADATA_URL_HINT')),
            Setting::text('metadata_xml')->multiline()->label(_t('SIGN_IN_SAML_METADATA_XML')),
            Setting::text('idp_entity_id')->label(_t('SIGN_IN_SAML_IDP_ENTITY_ID'))->hint(_t('SIGN_IN_SAML_IDP_HINT')),
            Setting::url('idp_sso_url')->label(_t('SIGN_IN_SAML_IDP_SSO_URL')),
            Setting::text('idp_certificate')->multiline()->label(_t('SIGN_IN_SAML_IDP_CERTIFICATE')),
            Setting::text('sp_certificate')->multiline()->label(_t('SIGN_IN_SAML_SP_CERTIFICATE'))->hint(_t('SIGN_IN_SAML_SP_CERTIFICATE_HINT')),
            Setting::secret('sp_private_key')->multiline()->label(_t('SIGN_IN_SAML_SP_PRIVATE_KEY')),
        ];
    }

    public function defaultAttributes(): array
    {
        return ['id' => 'name_id', 'email' => 'mail', 'first_name' => 'givenName', 'last_name' => 'sn', 'groups' => 'groups'];
    }

    public function create(ProviderConfig $config): IdentityProvider
    {
        $address = 'user/sign-in/' . rawurlencode($config->id) . '/';

        return new SamlProvider(
            $config,
            $this->urls->href('', $address . 'metadata'),
            $this->urls->href('', $address . 'callback'),
            $this->urls->getBaseUrl() . '/',
        );
    }
}
