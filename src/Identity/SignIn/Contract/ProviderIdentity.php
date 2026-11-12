<?php

namespace YesWiki\Identity\SignIn\Contract;

/** Who the provider says the person is, as attributes named by the wiki's vocabulary (id, email, first_name...). */
final class ProviderIdentity
{
    /**
     * @param array<string, mixed> $attributes
     */
    public function __construct(public readonly array $attributes)
    {
    }
}
