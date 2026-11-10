<?php

namespace YesWiki\Identity\Service\SignIn\Type;

use YesWiki\Identity\SignIn\Contract\IdentityProvider;
use YesWiki\Identity\SignIn\Contract\ProviderConfig;
use YesWiki\Identity\SignIn\Contract\ProviderType;
use YesWiki\Identity\SignIn\Provider\LdapProvider;
use YesWiki\Identity\SignIn\Provider\NativeLdapConnection;
use YesWiki\Kernel\Component\Setting;

/**
 * A company directory, asked over LDAP with the login and password typed into the wiki's form.
 * The address is not a web address, so it is a text setting: the edit screen warns when it is ldap:// without StartTLS.
 */
final class LdapType implements ProviderType
{
    public const NAME = 'ldap';

    public function name(): string
    {
        return self::NAME;
    }

    public function label(): string
    {
        return _t('SIGN_IN_TYPE_LDAP');
    }

    public function connectionSettings(): array
    {
        return [
            Setting::text('uri')->label(_t('SIGN_IN_LDAP_URI'))->hint(_t('SIGN_IN_LDAP_URI_HINT'))->required(),
            Setting::checkbox('start_tls')->label(_t('SIGN_IN_LDAP_START_TLS'))->hint(_t('SIGN_IN_LDAP_START_TLS_HINT')),
            Setting::text('bind_dn_template')->label(_t('SIGN_IN_LDAP_BIND_DN_TEMPLATE'))->hint(_t('SIGN_IN_LDAP_BIND_DN_TEMPLATE_HINT')),
            Setting::text('base_dn')->label(_t('SIGN_IN_LDAP_BASE_DN'))->hint(_t('SIGN_IN_LDAP_BASE_DN_HINT')),
            Setting::text('user_filter')->label(_t('SIGN_IN_LDAP_USER_FILTER'))->default(LdapProvider::DEFAULT_USER_FILTER),
            Setting::text('search_dn')->label(_t('SIGN_IN_LDAP_SEARCH_DN'))->hint(_t('SIGN_IN_LDAP_SEARCH_DN_HINT')),
            Setting::secret('search_password')->label(_t('SIGN_IN_LDAP_SEARCH_PASSWORD')),
            Setting::text('group_base')->label(_t('SIGN_IN_LDAP_GROUP_BASE'))->hint(_t('SIGN_IN_LDAP_GROUP_BASE_HINT')),
            Setting::text('group_filter')->label(_t('SIGN_IN_LDAP_GROUP_FILTER'))->default(LdapProvider::DEFAULT_GROUP_FILTER),
        ];
    }

    public function defaultAttributes(): array
    {
        return ['id' => 'uid', 'email' => 'mail', 'first_name' => 'givenName', 'last_name' => 'sn', 'groups' => 'memberOf'];
    }

    public function create(ProviderConfig $config): IdentityProvider
    {
        return new LdapProvider($config, new NativeLdapConnection());
    }
}
