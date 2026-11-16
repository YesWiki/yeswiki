<?php

namespace YesWiki\Identity\SignIn;

use YesWiki\Identity\Entity\User;

/** What a carried-out sign-in leaves: the account, and what went wrong without stopping it. */
final class AppliedSignIn
{
    /** @param list<array{kind: string, message: string}> $warnings */
    public function __construct(
        public readonly User $user,
        public readonly array $warnings
    ) {
    }
}
