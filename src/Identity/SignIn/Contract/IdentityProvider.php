<?php

namespace YesWiki\Identity\SignIn\Contract;

/** A way to sign in, configured once under a key of `sso_providers`. */
interface IdentityProvider
{
    public function id(): string;

    public function label(): string;

    /** The name of the ProviderType that created it. */
    public function type(): string;
}
