<?php

namespace YesWiki\Identity\SignIn;

/** How far a sign-in got before it was refused, for the administrator who has to find out why. */
final class SignInTrace
{
    public const SENT_TO_PROVIDER = 'sent_to_provider';
    public const PROVIDER_ANSWERED = 'provider_answered';
    public const ATTRIBUTES_READ = 'attributes_read';
    public const ACCOUNT_MATCHED = 'account_matched';
    public const GROUPS = 'groups';

    /**
     * @param array<string, mixed>        $attributes   what the provider sent
     * @param array<string, string>       $attributeUse which attribute fed which field
     * @param list<string>                $groups       the groups the provider sent
     * @param string|null                 $account      the account the sign-in matched, if any
     */
    public function __construct(
        public readonly string $lastStep = self::SENT_TO_PROVIDER,
        public readonly array $attributes = [],
        public readonly array $attributeUse = [],
        public readonly array $groups = [],
        public readonly ?string $account = null,
        public readonly string $error = '',
    ) {
    }
}
