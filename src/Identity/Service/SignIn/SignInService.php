<?php

namespace YesWiki\Identity\Service\SignIn;

use YesWiki\Identity\Entity\User;
use YesWiki\Identity\Service\GroupManager;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Identity\SignIn\AttributeReader;
use YesWiki\Identity\SignIn\Contract\FailureReason;
use YesWiki\Identity\SignIn\Contract\ProviderConfig;
use YesWiki\Identity\SignIn\Contract\ProviderIdentity;
use YesWiki\Identity\SignIn\PlanAction;
use YesWiki\Identity\SignIn\SignInPlan;

/**
 * Decides everything about a sign-in and writes nothing. Admin test mode calls only plan(), so it cannot disagree with a real sign-in.
 */
class SignInService
{
    /** Wiki fields refreshed from the provider at each sign-in, read through the provider's attribute mapping. */
    private const PROFILE_FIELDS = ['first_name', 'last_name'];

    public function __construct(
        private readonly AccountLinks $links,
        private readonly UserManager $users,
        private readonly GroupManager $groups,
        private readonly AccountNamer $namer
    ) {
    }

    public function plan(ProviderConfig $config, ProviderIdentity $identity): SignInPlan
    {
        $attrs = $identity->attributes;
        $names = ['id' => $this->attributeName($config, 'id'), 'email' => $this->attributeName($config, 'email')];
        $subject = AttributeReader::one($attrs, $names['id']);
        $email = AttributeReader::one($attrs, $names['email']);
        $use = $this->attributeUse($config, $attrs);
        $who = $email ?? $subject ?? '';
        $base = [$config, $subject, $attrs, $use, $who];

        if ($subject === null) {
            return $this->refuse($base, FailureReason::MissingAttribute, $this->missingDetail($names['id'], $attrs));
        }

        // Link exists for this provider and user ID.
        $linked = $this->links->find($config->id, $subject);
        if ($linked !== null) {
            if ($email === null && !$config->isUpgradedDirectory()) {
                return $this->refuse($base, FailureReason::MissingAttribute, $this->missingDetail($names['email'], $attrs));
            }

            return $this->accept($base, PlanAction::UseLinked, $linked, $email);
        }

        // Upgrade-only: an unlinked account named after the user ID, within the time limit.
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

    private function attributeName(ProviderConfig $config, string $field): string
    {
        return $config->attributes[$field] ?? $field;
    }

    private function nameMatchOpen(ProviderConfig $config): bool
    {
        if (!$config->isUpgradedDirectory()) {
            return false;
        }
        try {
            $limit = new \DateTimeImmutable($config->matchByNameUntil);
        } catch (\Exception) {
            return false;
        }

        return new \DateTimeImmutable() < $limit;
    }

    /** email_verified arrives as a boolean or as a string, depending on the provider. */
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

    /** @param array<string, mixed> $attrs */
    private function missingDetail(string $expected, array $attrs): string
    {
        $received = array_map('strval', array_keys($attrs));

        return sprintf('expected attribute "%s", received: %s', $expected, $received === [] ? '(nothing)' : implode(', ', $received));
    }

    /**
     * @param array<string, mixed> $attrs
     *
     * @return array<string, array{attribute: string, present: bool}>
     */
    private function attributeUse(ProviderConfig $config, array $attrs): array
    {
        $use = [];
        foreach (array_merge(['id', 'email', 'groups'], self::PROFILE_FIELDS) as $field) {
            $name = $this->attributeName($config, $field);
            $use[$field] = ['attribute' => $name, 'present' => AttributeReader::many($attrs, $name) !== []];
        }
        foreach ($config->extraFields as $field => $name) {
            $use[(string)$field] = ['attribute' => (string)$name, 'present' => AttributeReader::many($attrs, (string)$name) !== []];
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
        foreach (self::PROFILE_FIELDS as $field) {
            $value = AttributeReader::one($attrs, $this->attributeName($config, $field));
            if ($value !== null) {
                $profile[$field] = $value;
            }
        }
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

    /** An existing account signs in: refresh its profile and groups, guarding the email. */
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
     * @param list<array{kind: string, message: string}> $warnings
     * @param list<string>                               $current  wiki groups the account is in now
     */
    private function build(array $base, PlanAction $action, ?string $tag, array $profile, array $warnings, array $current): SignInPlan
    {
        [$config, $subject, $attrs, $use, $who] = $base;
        [$add, $remove, $ignored] = $this->groupDiff($config, $attrs, $current);
        if (in_array('admins', $remove, true) && array_diff($this->groups->getMembers('admins'), [$tag]) === []) {
            $remove = array_values(array_diff($remove, ['admins']));
            $warnings[] = ['kind' => 'last_admin', 'message' => 'the account stays in "admins": it is the last member'];
        }

        return new SignInPlan($config->id, $subject, $action, $tag, null, '', $profile, $add, $remove, $ignored, $warnings, $use, AttributeReader::forLog($attrs), $who);
    }

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
