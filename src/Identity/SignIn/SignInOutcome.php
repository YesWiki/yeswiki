<?php

namespace YesWiki\Identity\SignIn;

use YesWiki\Identity\SignIn\Contract\FailureReason;
use YesWiki\Identity\SignIn\Contract\ProviderConfig;

/**
 * How a sign-in ended, for whoever has to answer the browser.
 * `detail` is for an administrator and is never shown to the person; `message` is what the person may read in place of the translated one.
 */
final class SignInOutcome
{
    public const SIGNED_IN = 'signed_in';
    public const REFUSED = 'refused';
    public const FORM_ERROR = 'form_error';
    public const TESTED = 'tested';

    /** @param list<array<string, mixed>> $steps */
    private function __construct(
        public readonly string $kind,
        public readonly string $url = '',
        public readonly ?ProviderConfig $config = null,
        public readonly ?FailureReason $reason = null,
        public readonly string $who = '',
        public readonly string $detail = '',
        public readonly ?SignInPlan $plan = null,
        public readonly array $steps = [],
        public readonly string $message = '',
        public readonly string $error = '',
    ) {
    }

    public static function signedIn(string $url): self
    {
        return new self(self::SIGNED_IN, url: $url);
    }

    public static function refused(?ProviderConfig $config, FailureReason $reason, string $who, string $detail = '', string $employeeMessage = ''): self
    {
        return new self(self::REFUSED, config: $config, reason: $reason, who: $who, detail: $detail, message: $employeeMessage);
    }

    public static function formError(string $message): self
    {
        return new self(self::FORM_ERROR, message: $message);
    }

    /** @param list<array<string, mixed>> $steps */
    public static function tested(ProviderConfig $config, SignInPlan $plan, array $steps, string $error): self
    {
        return new self(self::TESTED, config: $config, plan: $plan, steps: $steps, error: $error);
    }
}
