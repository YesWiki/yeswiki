<?php

namespace YesWiki\HelloWorld\Service;

use Symfony\Component\DependencyInjection\Attribute\Exclude;
use YesWiki\Identity\SignIn\Contract\FailureReason;
use YesWiki\Identity\SignIn\Contract\PasswordProvider;
use YesWiki\Identity\SignIn\Contract\ProviderConfig;
use YesWiki\Identity\SignIn\Contract\ProviderFailure;
use YesWiki\Identity\SignIn\Contract\ProviderIdentity;

/**
 * Anyone signs in with the one password of the connection settings, whatever the login: a toy, to show what a provider has to do.
 * `#[Exclude]` because every class of `services/` is autowired, and this one is built by the type with a ProviderConfig.
 */
#[Exclude]
final class HelloPasswordProvider implements PasswordProvider
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
        $expected = $this->config->connection('password');
        $login = trim($login);
        // No password configured, or none typed: nothing signs in, so the sample does nothing until it is set up.
        if (!is_string($expected) || $expected === '' || $password === '' || $login === '' || !hash_equals($expected, $password)) {
            throw new ProviderFailure(FailureReason::WrongCredentials, 'wrong login or password');
        }

        return new ProviderIdentity([
            $this->config->attributes['id'] ?? 'uid' => $login,
            $this->config->attributes['email'] ?? 'mail' => $login . '@example.invalid',
        ]);
    }
}
