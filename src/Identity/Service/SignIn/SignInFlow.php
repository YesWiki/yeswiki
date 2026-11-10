<?php

namespace YesWiki\Identity\Service\SignIn;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use YesWiki\Identity\Exception\BadLoginException;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Identity\Service\SignIn\Type\LocalType;
use YesWiki\Identity\SignIn\AttributeReader;
use YesWiki\Identity\SignIn\Contract\FailureReason;
use YesWiki\Identity\SignIn\Contract\Handshake;
use YesWiki\Identity\SignIn\Contract\HandshakeMode;
use YesWiki\Identity\SignIn\Contract\IdentityProvider;
use YesWiki\Identity\SignIn\Contract\PasswordProvider;
use YesWiki\Identity\SignIn\Contract\ProviderConfig;
use YesWiki\Identity\SignIn\Contract\ProviderFailure;
use YesWiki\Identity\SignIn\Contract\ProviderIdentity;
use YesWiki\Identity\SignIn\Contract\RedirectProvider;
use YesWiki\Identity\SignIn\SignInOutcome;
use YesWiki\Identity\SignIn\SignInPlan;
use YesWiki\Identity\SignIn\SignInTrace;
use YesWiki\Kernel\Service\UrlFormatter;

/** A sign-in from start to end, for every kind of provider. Whatever goes wrong ends as an outcome: nothing here reaches the visitor as a crash. */
class SignInFlow
{
    /** A provider sign-in lasts the remembered lifetime; only the local form has a checkbox for it. */
    private const REMEMBERED = 1;

    public function __construct(
        private readonly ProviderRegistry $registry,
        private readonly SignInService $service,
        private readonly SignInJournal $journal,
        private readonly AttemptLimiter $limiter,
        private readonly HandshakeCookie $cookie,
        private readonly AuthenticationService $authentication,
        private readonly UrlFormatter $urls,
    ) {
    }

    public function begin(string $providerId, string $returnUrl, HandshakeMode $mode = HandshakeMode::Real): Response|SignInOutcome
    {
        $config = null;
        try {
            $config = $this->registry->config($providerId);
            $provider = $this->provider($config);
            if (!$provider instanceof RedirectProvider) {
                throw new ProviderFailure(FailureReason::ConfigurationInvalid, 'this provider does not redirect anyone: it takes a password on the sign-in form');
            }
            $handshake = new Handshake(
                $providerId,
                $this->urls->isInternal($returnUrl) ? $returnUrl : $this->home(),
                $this->callbackUrlFor($config),
                $mode,
                time() + HandshakeCookie::LIFETIME
            );
            $response = $provider->begin($handshake);
            $this->cookie->issue($handshake);

            return $response;
        } catch (\Throwable $thrown) {
            return $this->failed($config, '', $thrown, SignInTrace::SENT_TO_PROVIDER);
        }
    }

    /** @param ?string $providerId null when the URL does not say (a legacy callback): the handshake does */
    public function callback(Request $request, ?string $providerId): Response|SignInOutcome
    {
        $config = null;
        $step = SignInTrace::SENT_TO_PROVIDER;
        try {
            $handshake = $this->cookie->read($request, time());
            $providerId ??= $handshake?->providerId;
            $config = $providerId === null ? null : $this->registry->config($providerId);
            if ($handshake === null || $handshake->providerId !== $providerId) {
                throw new ProviderFailure(FailureReason::InvalidResponse, 'no valid handshake for this provider: it expired, was altered, or the sign-in started in another browser');
            }
            $this->cookie->clear();
            $provider = $this->provider($config);
            if (!$provider instanceof RedirectProvider) {
                throw new ProviderFailure(FailureReason::ConfigurationInvalid, 'this provider does not redirect anyone');
            }
            $step = SignInTrace::PROVIDER_ANSWERED;
            $identity = $provider->complete($request, $handshake);
        } catch (\Throwable $thrown) {
            return $this->failed($config, '', $thrown, $step);
        }

        return $this->settle($config, $identity, self::REMEMBERED, $handshake->returnUrl);
    }

    public function password(string $providerId, string $login, #[\SensitiveParameter] string $password, bool $remember, HandshakeMode $mode = HandshakeMode::Real): SignInOutcome
    {
        $who = AttemptLimiter::normalize($login);
        $config = null;
        try {
            $config = $this->registry->config($providerId);
            $provider = $this->provider($config);
            if (!$provider instanceof PasswordProvider) {
                throw new ProviderFailure(FailureReason::ConfigurationInvalid, 'this provider takes no password: it redirects to its own sign-in');
            }
            if ($this->limiter->isBlocked($who)) {
                throw new ProviderFailure(FailureReason::TooManyAttempts);
            }
            $identity = $provider->authenticate($login, $password);
        } catch (\Throwable $thrown) {
            $outcome = $this->failed($config, $who, $thrown, SignInTrace::SENT_TO_PROVIDER);

            return $outcome->reason === FailureReason::WrongCredentials
                ? SignInOutcome::formError($outcome->message !== '' ? $outcome->message : _t('SIGN_IN_REFUSED_WRONG_CREDENTIALS'))
                : $outcome;
        }

        return $this->settle($config, $identity, $config->type === LocalType::NAME ? $remember : self::REMEMBERED, $this->home(), $who);
    }

    /** What the provider must be told to send the person back to. */
    public function callbackUrlFor(ProviderConfig $config): string
    {
        $legacy = $config->connection('legacy_callback');
        if (is_string($legacy) && $legacy !== '') {
            return $legacy;
        }

        return $this->urls->href('', 'user/sign-in/' . rawurlencode($config->id) . '/callback');
    }

    /** The provider, when it can be used right now. */
    private function provider(?ProviderConfig $config): IdentityProvider
    {
        $provider = $config !== null && $this->registry->isEnabled($config->id) ? $this->registry->get($config->id) : null;
        if ($provider === null) {
            throw new ProviderFailure(FailureReason::ProviderDisabled, 'the provider is switched off, unknown, or its type is not installed');
        }

        return $provider;
    }

    /**
     * The provider has said who this is: decide, apply, sign in.
     *
     * @param int|bool $remember what AuthenticationService::login() takes
     * @param ?string  $login    what a failure is recorded under, when it must be the login that the attempt limit counts
     */
    private function settle(ProviderConfig $config, ProviderIdentity $identity, int|bool $remember, string $url, ?string $login = null): SignInOutcome
    {
        $plan = null;
        $step = SignInTrace::ATTRIBUTES_READ;
        try {
            $plan = $this->service->plan($config, $identity);
            if ($plan->refusal === null) {
                $step = SignInTrace::ACCOUNT_MATCHED;
            }
            $applied = $this->service->apply($plan);
            $step = SignInTrace::GROUPS;
            $this->authentication->login($applied->user, $remember);
            foreach ($applied->warnings as $warning) {
                $this->journal->warning($config, $plan->who, $warning['kind'], $warning['message']);
            }

            return SignInOutcome::signedIn($url);
        } catch (BadLoginException $notActivated) {
            // the account is not activated yet: the person is told on the form, with the words the wiki always used.
            return SignInOutcome::formError($notActivated->getMessage());
        } catch (\Throwable $thrown) {
            return $this->failed($config, $login ?? $plan?->who ?? '', $thrown, $step, $identity, $plan);
        }
    }

    private function failed(?ProviderConfig $config, string $who, \Throwable $thrown, string $step, ?ProviderIdentity $identity = null, ?SignInPlan $plan = null): SignInOutcome
    {
        $failure = $thrown instanceof ProviderFailure
            ? $thrown
            : new ProviderFailure(FailureReason::InternalError, $thrown::class . ': ' . $thrown->getMessage());
        if ($config !== null) {
            $this->journal->failure($config, $who, $failure->reason, $this->trace($config, $failure, $step, $identity, $plan));
        }

        return SignInOutcome::refused($config, $failure->reason, $who, $failure->adminDetail, $failure->employeeMessage ?? '');
    }

    private function trace(ProviderConfig $config, ProviderFailure $failure, string $step, ?ProviderIdentity $identity, ?SignInPlan $plan): SignInTrace
    {
        $attributes = $identity?->attributes ?? $failure->attributes;
        $used = array_filter($plan?->attributeUse ?? [], fn (array $use): bool => $use['present']);

        return new SignInTrace(
            $step,
            $attributes,
            array_map(fn (array $use): string => $use['attribute'], $used),
            AttributeReader::many($attributes, $config->attributes['groups'] ?? 'groups'),
            $plan?->accountTag,
            $failure->adminDetail,
        );
    }

    /** Where to go when nothing better was asked for: the wiki's root sends on to its home page. */
    private function home(): string
    {
        return $this->urls->getBaseUrl() . '/';
    }
}
