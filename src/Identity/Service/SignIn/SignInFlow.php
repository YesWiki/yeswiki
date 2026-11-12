<?php

namespace YesWiki\Identity\Service\SignIn;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use YesWiki\Identity\Exception\BadLoginException;
use YesWiki\Identity\Service\AclService;
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
use YesWiki\Identity\SignIn\PlanAction;
use YesWiki\Identity\SignIn\SignInOutcome;
use YesWiki\Identity\SignIn\SignInPlan;
use YesWiki\Identity\SignIn\SignInTrace;
use YesWiki\Kernel\Service\UrlFormatter;

/**
 * A sign-in from start to end, for every kind of provider. Whatever goes wrong ends as an outcome: nothing here reaches the visitor as a crash.
 *
 * In test mode an administrator runs the real protocol and only plan() is called: nobody is signed in, nothing is written
 * and nothing goes to the Journal (spec 8.3 and 9).
 */
class SignInFlow
{
    /** A provider sign-in lasts the remembered lifetime; only the local form has a checkbox for it. */
    private const REMEMBERED = 1;

    /** The handshake value naming the administrator who started a test: only they can finish it. */
    private const TESTER = 'tested_by';

    /** The steps of a sign-in, in the order a run goes through them. */
    private const STEPS = [SignInTrace::SENT_TO_PROVIDER, SignInTrace::PROVIDER_ANSWERED, SignInTrace::ATTRIBUTES_READ, SignInTrace::ACCOUNT_MATCHED, SignInTrace::GROUPS];

    public function __construct(
        private readonly ProviderRegistry $registry,
        private readonly SignInService $service,
        private readonly SignInJournal $journal,
        private readonly HandshakeCookie $cookie,
        private readonly AuthenticationService $authentication,
        private readonly UrlFormatter $urls,
        private readonly AclService $acl,
    ) {
    }

    public function begin(string $providerId, string $returnUrl, HandshakeMode $mode = HandshakeMode::Real): Response|SignInOutcome
    {
        $config = null;
        $testing = $mode === HandshakeMode::Test;
        try {
            $config = $this->registry->config($providerId);
            $tester = $testing ? $this->tester() : null;
            if ($testing && $tester === null) {
                return $this->notTheTester($config);
            }
            $config = $this->known($config);
            $provider = $testing ? $this->testedProvider($config) : $this->provider($config);
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
            if ($tester !== null) {
                $handshake->set(self::TESTER, $tester);
            }
            $response = $provider->begin($handshake);
            $this->cookie->issue($handshake);

            return $response;
        } catch (\Throwable $thrown) {
            return $testing
                ? $this->testFailed($config, $thrown, SignInTrace::SENT_TO_PROVIDER)
                : $this->failed($config, '', $thrown, SignInTrace::SENT_TO_PROVIDER);
        }
    }

    /** @param ?string $providerId null when the URL does not say (a legacy callback): the handshake does */
    public function callback(Request $request, ?string $providerId): Response|SignInOutcome
    {
        $config = null;
        $step = SignInTrace::SENT_TO_PROVIDER;
        $testing = false;
        try {
            $handshake = $this->cookie->read($request, time());
            $testing = $handshake?->mode === HandshakeMode::Test;
            $providerId ??= $handshake?->providerId;
            $config = $providerId === null ? null : $this->registry->config($providerId);
            if ($testing && !$this->isTheTester($handshake)) {
                $this->cookie->clear();

                return $this->notTheTester($config);
            }
            if ($handshake === null || $handshake->providerId !== $providerId) {
                throw new ProviderFailure(FailureReason::InvalidResponse, 'no valid handshake for this provider: it expired, was altered, or the sign-in started in another browser');
            }
            $this->cookie->clear();
            $config = $this->known($config);
            $provider = $testing ? $this->testedProvider($config) : $this->provider($config);
            if (!$provider instanceof RedirectProvider) {
                throw new ProviderFailure(FailureReason::ConfigurationInvalid, 'this provider does not redirect anyone');
            }
            $step = SignInTrace::PROVIDER_ANSWERED;
            $identity = $provider->complete($request, $handshake);
        } catch (\Throwable $thrown) {
            return $testing ? $this->testFailed($config, $thrown, $step) : $this->failed($config, '', $thrown, $step);
        }

        return $testing ? $this->tryOut($config, $identity) : $this->settle($config, $identity, self::REMEMBERED, $handshake->returnUrl);
    }

    public function password(string $providerId, string $login, #[\SensitiveParameter] string $password, bool $remember, HandshakeMode $mode = HandshakeMode::Real): SignInOutcome
    {
        if ($mode === HandshakeMode::Test) {
            return $this->testPassword($providerId, $login, $password);
        }
        $who = mb_strtolower(trim($login));
        $config = null;
        try {
            $config = $this->known($this->registry->config($providerId));
            $provider = $this->provider($config);
            if (!$provider instanceof PasswordProvider) {
                throw new ProviderFailure(FailureReason::ConfigurationInvalid, 'this provider takes no password: it redirects to its own sign-in');
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

    /** The configuration of a provider the section has; an unknown one ends the sign-in like a switched-off one. */
    private function known(?ProviderConfig $config): ProviderConfig
    {
        return $config ?? throw new ProviderFailure(FailureReason::ProviderDisabled, 'no provider has this identifier');
    }

    /** The provider, when it can be used right now. */
    private function provider(ProviderConfig $config): IdentityProvider
    {
        $provider = $this->registry->isEnabled($config->id) ? $this->registry->get($config->id) : null;
        if ($provider === null) {
            throw new ProviderFailure(FailureReason::ProviderDisabled, 'the provider is switched off, or its type is not installed');
        }

        return $provider;
    }

    /** A test needs the provider's type, not the provider switched on: testing it before switching it on is the point. */
    private function testedProvider(ProviderConfig $config): IdentityProvider
    {
        if ($config->type === LocalType::NAME) {
            // checking a local password can rewrite its stored hash, and a test changes nothing.
            throw new ProviderFailure(FailureReason::ConfigurationInvalid, 'the wiki\'s own accounts are not tested here');
        }
        $provider = $this->registry->get($config->id);
        if ($provider === null) {
            throw new ProviderFailure(FailureReason::ProviderDisabled, sprintf('the type "%s" of this provider is not installed', $config->type));
        }

        return $provider;
    }

    /** The signed-in administrator, who alone may test; null for anyone else. */
    private function tester(): ?string
    {
        $name = $this->authentication->signedInName();

        return $name !== '' && $this->acl->isAdmin($name) ? $name : null;
    }

    /** Whether the one coming back from the provider is the administrator who started this test, and still an administrator. */
    private function isTheTester(Handshake $handshake): bool
    {
        $tester = $this->tester();

        return $tester !== null && $handshake->get(self::TESTER) === $tester;
    }

    /** Someone else than the administrator who started the test: refused, and nothing is written. */
    private function notTheTester(?ProviderConfig $config): SignInOutcome
    {
        return SignInOutcome::refused($config, FailureReason::InvalidResponse, '', 'a test is finished only by the administrator who started it, still signed in', '', $this->labelOf($config));
    }

    private function testPassword(string $providerId, string $login, #[\SensitiveParameter] string $password): SignInOutcome
    {
        $config = null;
        $step = SignInTrace::SENT_TO_PROVIDER;
        try {
            if ($this->tester() === null) {
                return $this->notTheTester($this->registry->config($providerId));
            }
            $config = $this->known($this->registry->config($providerId));
            $provider = $this->testedProvider($config);
            if (!$provider instanceof PasswordProvider) {
                throw new ProviderFailure(FailureReason::ConfigurationInvalid, 'this provider takes no password: it redirects to its own sign-in');
            }
            $step = SignInTrace::PROVIDER_ANSWERED;
            $identity = $provider->authenticate($login, $password);
        } catch (\Throwable $thrown) {
            $unreachable = $thrown instanceof ProviderFailure && $thrown->reason === FailureReason::ProviderUnreachable;

            return $this->testFailed($config, $thrown, $unreachable ? SignInTrace::SENT_TO_PROVIDER : $step);
        }

        return $this->tryOut($config, $identity);
    }

    /** The provider has said who this is, in a test: only plan(), whose decision the screen shows. */
    private function tryOut(ProviderConfig $config, ProviderIdentity $identity): SignInOutcome
    {
        try {
            $plan = $this->service->plan($config, $identity);
        } catch (\Throwable $thrown) {
            return $this->testFailed($config, $thrown, SignInTrace::ATTRIBUTES_READ, $identity);
        }
        if ($plan->refusal === null) {
            return SignInOutcome::tested($config, $plan, $this->steps(SignInTrace::GROUPS, false), '');
        }
        $refusedAt = $plan->refusal === FailureReason::MissingAttribute ? SignInTrace::ATTRIBUTES_READ : SignInTrace::ACCOUNT_MATCHED;

        return SignInOutcome::tested($config, $plan, $this->steps($refusedAt, true), $this->journal->scrub($plan->refusalDetail, $config));
    }

    /** A test that did not get as far as a plan: what a real sign-in would do is be refused, for this reason. Nothing is recorded. */
    private function testFailed(?ProviderConfig $config, \Throwable $thrown, string $step, ?ProviderIdentity $identity = null): SignInOutcome
    {
        $failure = $this->asFailure($thrown);
        if ($config === null) {
            return SignInOutcome::refused(null, $failure->reason, '', $failure->adminDetail);
        }
        $error = $this->journal->scrub($failure->adminDetail, $config);
        $attributes = AttributeReader::forLog($identity->attributes ?? $failure->attributes);
        $plan = new SignInPlan($config->id, null, PlanAction::Refuse, null, $failure->reason, $error, [], [], [], [], [], [], $attributes, '');

        return SignInOutcome::tested($config, $plan, $this->steps($step, true), $error);
    }

    /**
     * Every step, done up to $reached, which failed when $failed, and not reached after it.
     *
     * @return list<array{step: string, state: 'done'|'failed'|'not_reached'}>
     */
    private function steps(string $reached, bool $failed): array
    {
        $at = (int)array_search($reached, self::STEPS, true);
        $steps = [];
        foreach (self::STEPS as $i => $step) {
            $state = $i < $at ? 'done' : ($i > $at ? 'not_reached' : ($failed ? 'failed' : 'done'));
            $steps[] = ['step' => $step, 'state' => $state];
        }

        return $steps;
    }

    /**
     * The provider has said who this is: decide, apply, sign in.
     *
     * @param int|bool $remember what AuthenticationService::login() takes
     * @param ?string  $login    what a failure is recorded under, when it must be the login typed rather than the person's email
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
            return $this->failed($config, $login ?? $plan->who ?? '', $thrown, $step, $identity, $plan);
        }
    }

    private function failed(?ProviderConfig $config, string $who, \Throwable $thrown, string $step, ?ProviderIdentity $identity = null, ?SignInPlan $plan = null): SignInOutcome
    {
        $failure = $this->asFailure($thrown);
        if ($config !== null) {
            $this->journal->failure($config, $who, $failure->reason, $this->trace($config, $failure, $step, $identity, $plan));
        }

        return SignInOutcome::refused($config, $failure->reason, $who, $failure->adminDetail, $failure->employeeMessage ?? $this->missingAttributeMessage($config, $failure, $plan), $this->labelOf($config));
    }

    /** The provider's name as people read it; '' when there is no provider, or its name cannot be had. */
    private function labelOf(?ProviderConfig $config): string
    {
        try {
            return $config === null ? '' : $this->registry->labelOf($config);
        } catch (\Throwable) {
            return '';
        }
    }

    private function asFailure(\Throwable $thrown): ProviderFailure
    {
        return $thrown instanceof ProviderFailure
            ? $thrown
            : new ProviderFailure(FailureReason::InternalError, $thrown::class . ': ' . $thrown->getMessage());
    }

    /** A plan refused for lack of the user ID or the email names the attribute it looked for, so the person can pass it on. */
    private function missingAttributeMessage(?ProviderConfig $config, ProviderFailure $failure, ?SignInPlan $plan): string
    {
        if ($config === null || $plan === null || $failure->reason !== FailureReason::MissingAttribute) {
            return '';
        }
        foreach (['id', 'email'] as $field) {
            $use = $plan->attributeUse[$field] ?? null;
            if ($use !== null && !$use['present']) {
                return _t('SIGN_IN_REFUSED_MISSING_NAMED_ATTRIBUTE', ['provider' => $this->labelOf($config) ?: $config->label, 'attribute' => $use['attribute']]);
            }
        }

        return '';
    }

    private function trace(ProviderConfig $config, ProviderFailure $failure, string $step, ?ProviderIdentity $identity, ?SignInPlan $plan): SignInTrace
    {
        $attributes = $identity->attributes ?? $failure->attributes;
        $used = array_filter($plan->attributeUse ?? [], fn (array $use): bool => $use['present']);

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
