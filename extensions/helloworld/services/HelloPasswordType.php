<?php

namespace YesWiki\HelloWorld\Service;

use YesWiki\Identity\SignIn\Contract\IdentityProvider;
use YesWiki\Identity\SignIn\Contract\ProviderConfig;
use YesWiki\Identity\SignIn\Contract\ProviderType;
use YesWiki\Kernel\Component\Setting;

/**
 * How an extension adds a sign-in provider type: one service implementing ProviderType, and nothing else to declare.
 * Core then lists the type on the administration screen, draws its settings, applies the account rules, the attempt limit and the Journal.
 */
class HelloPasswordType implements ProviderType
{
    public function name(): string
    {
        return 'hello-password';
    }

    public function label(): string
    {
        return _t('HELLOWORLD_SIGN_IN_TYPE');
    }

    public function connectionSettings(): array
    {
        // No default: until an administrator sets a password, nobody can sign in with this provider.
        return [Setting::secret('password')->label(_t('HELLOWORLD_SIGN_IN_PASSWORD'))];
    }

    public function defaultAttributes(): array
    {
        return ['id' => 'uid', 'email' => 'mail'];
    }

    public function create(ProviderConfig $config): IdentityProvider
    {
        return new HelloPasswordProvider($config);
    }
}
