<?php

namespace YesWiki\Identity\Exception;

use YesWiki\Identity\Entity\User;

/** A password asked of an account a provider is linked to: only the provider signs it in, so it has none to set or recover. */
class PasswordManagedByProviderException extends \RuntimeException
{
    public function __construct(public readonly string $providerId)
    {
        parent::__construct("the account's password is managed by the provider \"$providerId\"");
    }

    /** @throws self when a provider is linked to $user */
    public static function refuseLinked(User $user): void
    {
        $providerId = $user->provider();
        if ($providerId !== null) {
            throw new self($providerId);
        }
    }
}
