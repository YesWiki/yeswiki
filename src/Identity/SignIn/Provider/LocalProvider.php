<?php

namespace YesWiki\Identity\SignIn\Provider;

use YesWiki\Identity\SignIn\Contract\FailureReason;
use YesWiki\Identity\SignIn\Contract\PasswordProvider;
use YesWiki\Identity\SignIn\Contract\ProviderConfig;
use YesWiki\Identity\SignIn\Contract\ProviderFailure;
use YesWiki\Identity\SignIn\Contract\ProviderIdentity;

/** The wiki's own accounts. */
final class LocalProvider implements PasswordProvider
{
    public function __construct(private readonly ProviderConfig $config)
    {
    }

    public function id(): string
    {
        return $this->config->id;
    }

    public function label(): string
    {
        return $this->config->label;
    }

    public function type(): string
    {
        return $this->config->type;
    }

    public function authenticate(string $login, #[\SensitiveParameter] string $password): ProviderIdentity
    {
        throw new ProviderFailure(FailureReason::InternalError, 'local sign-in is not wired to this provider yet');
    }
}
