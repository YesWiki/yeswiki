<?php

namespace YesWiki\Identity\Service\SignIn\Type;

use YesWiki\Identity\SignIn\Contract\IdentityProvider;
use YesWiki\Identity\SignIn\Contract\ProviderConfig;
use YesWiki\Identity\SignIn\Contract\ProviderType;
use YesWiki\Identity\SignIn\Provider\LocalProvider;

/** The accounts of the wiki itself, the only type that exists without any configuration. */
final class LocalType implements ProviderType
{
    public const NAME = 'local';

    public function name(): string
    {
        return self::NAME;
    }

    public function label(): string
    {
        return 'Local';
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
        return new LocalProvider($config);
    }
}
