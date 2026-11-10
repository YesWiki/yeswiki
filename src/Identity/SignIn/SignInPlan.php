<?php

namespace YesWiki\Identity\SignIn;

use YesWiki\Identity\SignIn\Contract\FailureReason;

/**
 * Everything decided about one sign-in, before anything is written.
 *
 * @phpstan-type Warning array{kind: string, message: string}
 */
final class SignInPlan
{
    /**
     * @param array<string, string>                                 $profile       wiki field => value to store
     * @param list<string>                                          $groupsToAdd
     * @param list<string>                                          $groupsToRemove
     * @param list<string>                                          $groupsIgnored provider groups no mapping row names
     * @param list<array{kind: string, message: string}>            $warnings
     * @param array<string, array{attribute: string, present: bool}> $attributeUse  how each wiki field was read
     * @param array<string, mixed>                                  $attributes    what the provider sent, safe to log
     */
    public function __construct(
        public readonly string $providerId,
        public readonly ?string $subject,
        public readonly PlanAction $action,
        public readonly ?string $accountTag,
        public readonly ?FailureReason $refusal,
        public readonly string $refusalDetail,
        public readonly array $profile,
        public readonly array $groupsToAdd,
        public readonly array $groupsToRemove,
        public readonly array $groupsIgnored,
        public readonly array $warnings,
        public readonly array $attributeUse,
        public readonly array $attributes,
        public readonly string $who,
    ) {
    }
}
