<?php

namespace YesWiki\Identity\SignIn\Contract;

/** Thrown by a provider when a sign-in cannot go through. */
final class ProviderFailure extends \RuntimeException
{
    /**
     * @param string               $adminDetail     what an administrator may read; never a secret
     * @param ?string              $employeeMessage what the person signing in may read, when it differs from the default
     * @param array<string, mixed> $attributes      what the provider did return, to help an administrator diagnose
     */
    public function __construct(
        public readonly FailureReason $reason,
        public readonly string $adminDetail = '',
        public readonly ?string $employeeMessage = null,
        public readonly array $attributes = [],
    ) {
        parent::__construct($adminDetail !== '' ? $adminDetail : $reason->value);
    }
}
