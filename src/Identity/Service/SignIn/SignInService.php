<?php

namespace YesWiki\Identity\Service\SignIn;

use YesWiki\Identity\Entity\User;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Identity\SignIn\AppliedSignIn;
use YesWiki\Identity\SignIn\AttributeReader;
use YesWiki\Identity\SignIn\Contract\FailureReason;
use YesWiki\Identity\SignIn\Contract\ProviderConfig;
use YesWiki\Identity\SignIn\Contract\ProviderFailure;
use YesWiki\Identity\SignIn\Contract\ProviderIdentity;
use YesWiki\Identity\SignIn\PlanAction;
use YesWiki\Identity\SignIn\Provider\LocalProvider;
use YesWiki\Identity\SignIn\SignInPlan;
use YesWiki\Kernel\Service\HibernationService;
use YesWiki\Kernel\Service\Lock\Locks;

/**
 * Decides everything about a sign-in and writes nothing.
 *
 * @phpstan-type Base array{0: ProviderConfig, 1: ?string, 2: array<string, mixed>, 3: array<string, array{attribute: string, present: bool, unusable?: 'binary'|'not_text'|'not_one_address'}>, 4: string}
 */
class SignInService
{
    private const LOCK_ATTEMPTS = 5;
    private const LOCK_WAIT_MICROSECONDS = 200000;

    public function __construct(
        private readonly AccountLinks $links,
        private readonly UserManager $users,
        private readonly AccountNamer $namer,
        private readonly ProfileSync $profile,
        private readonly GroupSync $groupSync,
        private readonly Locks $locks,
        private readonly HibernationService $hibernation,
        private readonly ProviderRegistry $registry,
    ) {
    }

    public function plan(ProviderConfig $config, ProviderIdentity $identity): SignInPlan
    {
        $attrs = $identity->attributes;
        $names = ['id' => $this->attributeName($config, 'id'), 'email' => $this->attributeName($config, 'email')];
        $subject = AttributeReader::one($attrs, $names['id']);
        $email = AttributeReader::one($attrs, $names['email']);
        $email = $email === null || trim($email) === '' ? null : trim($email);
        $use = $this->attributeUse($config, $attrs);
        if ($email === null && $use['email']['present']) {
            $use['email']['present'] = false;
        }
        $notOneAddress = $email !== null && preg_match('/^[^\s@,;]+@[^\s@,;]+\z/u', $email) !== 1;
        if ($notOneAddress) {
            $use['email'] = ['attribute' => $use['email']['attribute'], 'present' => false, 'unusable' => 'not_one_address'];
        }
        $who = $email ?? $subject ?? '';
        $base = [$config, $subject, $attrs, $use, $who];

        if ($this->registry->checksTheWikisOwnAccounts($config->type)) {
            return $this->planLocal($base);
        }

        if ($subject === null) {
            return $this->refuse($base, FailureReason::MissingAttribute, $this->missingDetail($names['id'], $attrs));
        }

        if ($notOneAddress) {
            return $this->refuse($base, FailureReason::MissingAttribute, sprintf('the attribute "%s" is not one email address: "%s"', $names['email'], $email));
        }

        $linked = $this->links->find($config->id, $subject);
        if ($linked !== null) {
            if ($email === null && !$config->isUpgradedDirectory()) {
                return $this->refuse($base, FailureReason::MissingAttribute, $this->missingDetail($names['email'], $attrs));
            }

            return $this->accept($base, PlanAction::UseLinked, $linked, $email);
        }

        if ($this->nameMatchOpen($config)) {
            $named = $this->users->getOneByName($subject);
            if ($named !== null && $named->provider() === null) {
                return $this->accept($base, PlanAction::LinkExisting, $named, $email);
            }
        }

        if ($email === null) {
            $detail = $this->missingDetail($names['email'], $attrs);

            return $this->refuse($base, FailureReason::MissingAttribute, $detail);
        }

        $owner = $this->links->findByEmail($email);
        if ($owner === null) {
            if (!$config->createAccounts) {
                return $this->refuse($base, FailureReason::NoAccount, 'no account has this email and account creation is off');
            }
            $tag = $this->namer->suggest($config, $attrs);

            return $this->build($base, PlanAction::Create, $tag, $this->profile($config, $attrs, $email), [], []);
        }

        $provider = $owner->provider();
        if ($provider !== null && $provider !== $config->id) {
            return $this->refuse($base, FailureReason::LinkedElsewhere, "the account belongs to provider \"$provider\"");
        }
        if (!$config->matchByEmail || $this->isUnverified($attrs)) {
            $detail = !$config->matchByEmail ? 'match by email is off' : 'the provider does not vouch for this email';

            return $this->refuse($base, FailureReason::EmailConflict, "an account already uses this email: $detail");
        }

        $action = $provider === null ? PlanAction::LinkExisting : PlanAction::ReplaceSubject;

        return $this->accept($base, $action, $owner, $email);
    }

    /**
     * The local form has already checked the password: the plan names the account and nothing else changes.
     *
     * @param Base $base
     */
    private function planLocal(array $base): SignInPlan
    {
        [$config, , $attrs, $use] = $base;
        $tag = AttributeReader::one($attrs, LocalProvider::ACCOUNT_ATTRIBUTE);
        if ($tag === null) {
            return $this->refuse($base, FailureReason::MissingAttribute, $this->missingDetail(LocalProvider::ACCOUNT_ATTRIBUTE, $attrs));
        }

        return new SignInPlan($config->id, $tag, PlanAction::UseLocal, $tag, null, '', [], [], [], [], [], $use, AttributeReader::forLog($attrs), $tag);
    }

    /**
     * Carries a plan out and writes only what changed.
     *
     * @throws ProviderFailure
     */
    public function apply(SignInPlan $plan): AppliedSignIn
    {
        if ($plan->action === PlanAction::Refuse || $plan->refusal !== null) {
            throw new ProviderFailure($plan->refusal ?? FailureReason::InternalError, $plan->refusalDetail, null, $plan->attributes);
        }
        $hibernated = $this->hibernation->isWikiHibernated();
        $lockedActions = [PlanAction::Create, PlanAction::LinkExisting, PlanAction::ReplaceSubject];

        if ($plan->action === PlanAction::UseLinked) {
            $user = $this->users->getOneByName((string)$plan->accountTag);
            if ($user === null) {
                throw new ProviderFailure(FailureReason::InternalError, 'the linked account is gone', null, $plan->attributes);
            }
            if ($hibernated) {
                return new AppliedSignIn($user, $plan->warnings);
            }

            return $this->refresh($plan, $user);
        }
        if ($plan->action === PlanAction::UseLocal) {
            $user = $this->users->getOneByName((string)$plan->accountTag);
            if ($user === null || $user->provider() !== null) {
                throw new ProviderFailure(FailureReason::InternalError, 'the local account is gone or belongs to a provider', null, $plan->attributes);
            }

            return new AppliedSignIn($user, []);
        }
        if (!in_array($plan->action, $lockedActions, true) || $plan->subject === null) {
            throw new ProviderFailure(FailureReason::InternalError, 'the plan cannot be carried out', null, $plan->attributes);
        }
        if ($hibernated) {
            throw new ProviderFailure(FailureReason::AccountCreationFailed, 'the wiki is in hibernation: no account can be created or linked', null, $plan->attributes);
        }

        $lock = 'sign-in:' . $plan->providerId . ':' . hash('sha256', $plan->subject);
        for ($attempt = 1; $attempt <= self::LOCK_ATTEMPTS; $attempt++) {
            if ($this->locks->tryAcquire($lock)) {
                try {
                    $winner = $this->links->find($plan->providerId, $plan->subject);
                    if ($winner !== null) {
                        return $this->refresh($plan, $winner);
                    }

                    return $this->refresh($plan, $this->createOrLink($plan));
                } finally {
                    $this->locks->release($lock);
                }
            }
            if ($attempt < self::LOCK_ATTEMPTS) {
                usleep(self::LOCK_WAIT_MICROSECONDS);
            }
            $winner = $this->links->find($plan->providerId, $plan->subject);
            if ($winner !== null) {
                return $this->refresh($plan, $winner);
            }
        }

        throw new ProviderFailure(FailureReason::AccountCreationFailed, 'another sign-in of this account did not finish in time', null, $plan->attributes);
    }

    private function createOrLink(SignInPlan $plan): User
    {
        $subject = (string)$plan->subject;
        try {
            if ($plan->action === PlanAction::Create) {
                $profile = $plan->profile;
                $email = (string)($profile['email'] ?? '');
                unset($profile['email']);
                $user = $this->users->createLinked((string)$plan->accountTag, $email, $profile, $plan->providerId, $subject);
            } else {
                $user = $this->users->getOneByName((string)$plan->accountTag);
                $linkedTo = $user?->provider();
                $expected = $plan->action === PlanAction::ReplaceSubject ? $plan->providerId : null;
                if ($user !== null && $linkedTo !== $expected) {
                    $detail = $linkedTo === null ? 'the account is no longer tied to this provider' : "the account belongs to provider \"$linkedTo\"";

                    throw new ProviderFailure(FailureReason::LinkedElsewhere, $detail, null, $plan->attributes);
                }
                if ($user !== null) {
                    $this->links->link($user, $plan->providerId, $subject);
                    $user = $this->users->getOneByName($user->getName());
                }
            }
        } catch (ProviderFailure $refused) {
            throw $refused;
        } catch (\Throwable $e) {
            throw new ProviderFailure(FailureReason::AccountCreationFailed, 'the account could not be written: ' . $e->getMessage(), null, $plan->attributes);
        }
        if ($user === null) {
            throw new ProviderFailure(FailureReason::AccountCreationFailed, 'the account could not be written', null, $plan->attributes);
        }

        return $user;
    }

    /** Profile and groups, then the warnings of the plan and of the group update, the guard on "admins" said once. */
    private function refresh(SignInPlan $plan, User $user): AppliedSignIn
    {
        $this->profile->apply($user, $plan->profile);
        $user = $this->users->getOneByName($user->getName()) ?? $user;
        $warnings = $plan->warnings;
        $known = array_column($warnings, 'kind');
        foreach ($this->groupSync->apply($user, $plan->groupsToAdd, $plan->groupsToRemove) as $warning) {
            if ($warning['kind'] === GroupSync::LAST_ADMIN['kind'] && in_array($warning['kind'], $known, true)) {
                continue;
            }
            $warnings[] = $warning;
        }

        return new AppliedSignIn($user, $warnings);
    }

    private function attributeName(ProviderConfig $config, string $field): string
    {
        return $config->attributes[$field] ?? $field;
    }

    private function nameMatchOpen(ProviderConfig $config): bool
    {
        $until = $config->matchByNameUntil;
        if ($until === null) {
            return false;
        }
        try {
            $limit = new \DateTimeImmutable($until);
        } catch (\Exception) {
            return false;
        }

        return new \DateTimeImmutable() < $limit;
    }

    /**
     * email_verified arrives as a boolean or as a string, depending on the provider.
     *
     * @param array<string, mixed> $attrs
     */
    private function isUnverified(array $attrs): bool
    {
        if (!array_key_exists('email_verified', $attrs)) {
            return false;
        }
        $value = $attrs['email_verified'];
        if (is_string($value)) {
            $value = strtolower(trim($value));

            return in_array($value, ['false', '0', 'no', ''], true);
        }

        return $value === false || $value === 0 || $value === null;
    }

    /**
     * Why an attribute could not be read.
     *
     * @param array<string, mixed> $attrs
     */
    private function missingDetail(string $expected, array $attrs): string
    {
        $received = array_map('strval', array_keys($attrs));
        $unusable = AttributeReader::unusable($attrs, $expected);
        if ($unusable !== null) {
            return sprintf('attribute "%s" was sent, but its value is %s: it cannot be used, choose another attribute', $expected, $unusable === 'binary' ? 'binary, not text' : 'not a list of text values');
        }

        return sprintf('expected attribute "%s", received: %s', $expected, $received === [] ? '(nothing)' : implode(', ', $received));
    }

    /**
     * @param array<string, mixed> $attrs
     *
     * @return array<string, array{attribute: string, present: bool, unusable?: 'binary'|'not_text'|'not_one_address'}>
     */
    private function attributeUse(ProviderConfig $config, array $attrs): array
    {
        $names = [];
        foreach (['id', 'email', 'groups'] as $field) {
            $names[$field] = $this->attributeName($config, $field);
        }
        foreach ($config->extraFields as $field => $name) {
            $names[(string)$field] = (string)$name;
        }
        $use = [];
        foreach ($names as $field => $name) {
            $use[$field] = ['attribute' => $name, 'present' => AttributeReader::many($attrs, $name) !== []];
            $unusable = AttributeReader::unusable($attrs, $name);
            if ($unusable !== null) {
                $use[$field]['unusable'] = $unusable;
            }
        }

        return $use;
    }

    /**
     * What the provider says about the person; a field it did not send is left out, so the stored value stays.
     *
     * @param array<string, mixed> $attrs
     *
     * @return array<string, string>
     */
    private function profile(ProviderConfig $config, array $attrs, ?string $email): array
    {
        $profile = [];
        foreach ($config->extraFields as $field => $name) {
            $value = AttributeReader::one($attrs, (string)$name);
            if ($value !== null) {
                $profile[(string)$field] = $value;
            }
        }
        if ($email !== null) {
            $profile['email'] = $email;
        }

        return $profile;
    }

    /**
     * An existing account signs in: refresh its profile and groups, guarding the email.
     *
     * @param Base $base
     */
    private function accept(array $base, PlanAction $action, User $user, ?string $email): SignInPlan
    {
        [$config, , $attrs] = $base;
        $profile = $this->profile($config, $attrs, $email);
        $warnings = [];

        if ($email === null) {
            $warnings[] = [
                'kind' => 'email_missing_upgraded',
                'message' => 'the provider sent no email; the account keeps its stored email',
            ];
        } elseif (strcasecmp($email, $user->getEmail()) !== 0) {
            $other = $this->links->findByEmail($email);
            if ($other !== null && $other->getName() !== $user->getName()) {
                unset($profile['email']);
                $warnings[] = [
                    'kind' => 'email_collision',
                    'message' => 'the provider\'s email is used by another account; the account keeps its stored email',
                ];
            }
        }

        return $this->build($base, $action, $user->getName(), $profile, $warnings, $this->groupsOf($user));
    }

    /**
     * @param Base                                       $base
     * @param array<string, string>                      $profile
     * @param list<array{kind: string, message: string}> $warnings
     * @param list<string>                               $current  wiki groups the account is in now
     */
    private function build(array $base, PlanAction $action, ?string $tag, array $profile, array $warnings, array $current): SignInPlan
    {
        [$config, $subject, $attrs, $use, $who] = $base;
        [$add, $remove, $ignored] = $this->groupDiff($config, $attrs, $current);
        if ($tag !== null && in_array(ADMIN_GROUP, $remove, true) && $this->groupSync->isLastAdmin($tag)) {
            $remove = array_values(array_diff($remove, [ADMIN_GROUP]));
            $warnings[] = GroupSync::LAST_ADMIN;
        }

        return new SignInPlan($config->id, $subject, $action, $tag, null, '', $profile, $add, $remove, $ignored, $warnings, $use, AttributeReader::forLog($attrs), $who);
    }

    /** @param Base $base */
    private function refuse(array $base, FailureReason $reason, string $detail): SignInPlan
    {
        [$config, $subject, $attrs, $use, $who] = $base;

        return new SignInPlan($config->id, $subject, PlanAction::Refuse, null, $reason, $detail, [], [], [], [], [], $use, AttributeReader::forLog($attrs), $who);
    }

    /** @return list<string> */
    private function groupsOf(User $user): array
    {
        return array_values($this->users->groupsWhereIsMember($user));
    }

    /**
     * Only wiki groups named in the mapping are touched.
     *
     * @param array<string, mixed> $attrs
     * @param list<string>         $current
     *
     * @return array{0: list<string>, 1: list<string>, 2: list<string>} to add, to remove, provider groups ignored
     */
    private function groupDiff(ProviderConfig $config, array $attrs, array $current): array
    {
        $sent = AttributeReader::many($attrs, $this->attributeName($config, 'groups'));
        $mapped = [];
        $wanted = [];
        $known = [];
        foreach ($config->groupMapping as $row) {
            $provider = (string)($row['provider'] ?? '');
            $wiki = (string)($row['wiki'] ?? '');
            if ($provider === '' || $wiki === '') {
                continue;
            }
            $known[$provider] = true;
            $mapped[$wiki] = true;
            if (in_array($provider, $sent, true)) {
                $wanted[$wiki] = true;
            }
        }
        $add = array_values(array_diff(array_keys($wanted), $current));
        $remove = array_values(array_diff(array_intersect(array_keys($mapped), $current), array_keys($wanted)));
        $ignored = array_values(array_filter($sent, fn (string $group): bool => !isset($known[$group])));

        return [$add, $remove, array_values(array_unique($ignored))];
    }
}
