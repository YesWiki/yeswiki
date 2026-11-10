<?php

namespace YesWiki\Identity\Service\SignIn;

use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Identity\SignIn\AttributeReader;
use YesWiki\Identity\SignIn\Contract\FailureReason;
use YesWiki\Identity\SignIn\Contract\ProviderConfig;
use YesWiki\Identity\SignIn\SignInTrace;
use YesWiki\Kernel\Service\Journal;

/**
 * What a refused sign-in leaves behind: one audit row per refusal, and a diagnostic that collapses per provider and reason.
 * The Journal is best-effort here: writing to it never blocks a sign-in.
 */
class SignInJournal
{
    private const SECRET_KEY = '/secret|password|private_key/i';

    public function __construct(
        private readonly Journal $journal,
        private readonly AuthenticationService $authentication,
        private readonly AttemptLimiter $limiter,
        private readonly ProviderRegistry $registry,
    ) {
    }

    public function failure(ProviderConfig $config, string $who, FailureReason $reason, SignInTrace $trace): void
    {
        try {
            if ($reason === FailureReason::TooManyAttempts) {
                $this->limiter->recordThrottled($config->id, $who);

                return;
            }

            $this->authentication->recordFailedLogin($who, $reason->value, $config->id);

            if ($reason === FailureReason::WrongCredentials) {
                return;
            }

            $this->journal->warning("Sign-in refused ({$reason->value}) by {$config->id}", [
                'action' => "login.refused.{$reason->value}.{$config->id}",
                'target' => $who,
                'last_step' => $trace->lastStep,
                'attributes' => AttributeReader::forLog($trace->attributes),
                'attribute_use' => $trace->attributeUse,
                'groups' => $trace->groups,
                'account' => $trace->account,
                'error' => $this->scrub($trace->error, $config),
            ]);
        } catch (\Throwable $unavailable) {
            // The Journal never stands between an employee and the wiki.
        }
    }

    public function warning(ProviderConfig $config, string $who, string $kind, string $message): void
    {
        try {
            $this->journal->warning($this->scrub($message, $config), [
                'action' => "login.warning.{$kind}.{$config->id}",
                'target' => $who,
            ]);
        } catch (\Throwable $unavailable) {
        }
    }

    /** Hides the provider's secrets: the type's `secret` settings, and any connection value named like one, since a provider whose type is gone must be scrubbed too. */
    public function scrub(string $text, ProviderConfig $config): string
    {
        $secrets = [];
        foreach ($config->connection as $key => $value) {
            if (preg_match(self::SECRET_KEY, (string)$key) === 1) {
                $secrets[] = $value;
            }
        }

        try {
            $type = $this->registry->types()[$config->type] ?? null;
            foreach ($type?->connectionSettings() ?? [] as $setting) {
                if ($setting->type() === 'secret') {
                    $secrets[] = $config->connection[$setting->name()] ?? null;
                }
            }
        } catch (\Throwable $unavailable) {
        }

        foreach ($secrets as $secret) {
            if (is_string($secret) && $secret !== '') {
                $text = str_replace($secret, '***', $text);
            }
        }

        return $text;
    }
}
