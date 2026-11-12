<?php

namespace YesWiki\Identity\SignIn\Contract;

/** A provider that checks a login and a password typed into the wiki's own form (LDAP, local). */
interface PasswordProvider extends IdentityProvider
{
    /** @throws ProviderFailure */
    public function authenticate(string $login, #[\SensitiveParameter] string $password): ProviderIdentity;
}
