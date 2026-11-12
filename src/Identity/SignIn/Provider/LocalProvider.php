<?php

namespace YesWiki\Identity\SignIn\Provider;

use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Identity\SignIn\Contract\FailureReason;
use YesWiki\Identity\SignIn\Contract\PasswordProvider;
use YesWiki\Identity\SignIn\Contract\ProviderConfig;
use YesWiki\Identity\SignIn\Contract\ProviderFailure;
use YesWiki\Identity\SignIn\Contract\ProviderIdentity;

/** The wiki's own accounts: the name or the email, and the password the wiki stores. */
final class LocalProvider implements PasswordProvider
{
    /** The attribute that carries the account's tag, which SignInService::plan() maps to PlanAction::UseLocal. */
    public const ACCOUNT_ATTRIBUTE = 'local_account';

    public function __construct(
        private readonly ProviderConfig $config,
        private readonly AuthenticationService $auth,
        private readonly UserManager $users,
    ) {
    }

    public function id(): string
    {
        return $this->config->id;
    }

    /** The label of the entry, or the translated "Wiki account" while the entry has none of its own. */
    public function label(): string
    {
        return $this->config->label === $this->config->id ? _t('SIGN_IN_LOCAL_LABEL') : $this->config->label;
    }

    public function type(): string
    {
        return $this->config->type;
    }

    public function authenticate(string $login, #[\SensitiveParameter] string $password): ProviderIdentity
    {
        $login = trim($login);
        $user = $login === '' ? null : $this->users->getOneByName($login);
        if ($user === null && filter_var($login, FILTER_VALIDATE_EMAIL)) {
            $user = $this->users->getOneByEmail($login);
        }
        if ($user === null) {
            throw new ProviderFailure(FailureReason::WrongCredentials, 'no account has this name or email', _t('LOGIN_WRONG_USER'));
        }
        if ($user->provider() !== null) {
            throw new ProviderFailure(FailureReason::LinkedElsewhere, 'the account belongs to provider "' . $user->provider() . '"');
        }
        if (!$this->auth->checkPassword($password, $user)) {
            if ($this->auth->requiresPasswordReset($user)) {
                throw new ProviderFailure(FailureReason::WrongCredentials, 'the stored password is in an obsolete format', $this->auth->obsoletePasswordMessage());
            }

            throw new ProviderFailure(FailureReason::WrongCredentials, 'wrong password', _t('LOGIN_WRONG_PASSWORD'));
        }

        return new ProviderIdentity([self::ACCOUNT_ATTRIBUTE => $user->getName()]);
    }
}
