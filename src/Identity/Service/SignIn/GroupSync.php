<?php

namespace YesWiki\Identity\Service\SignIn;

use YesWiki\Identity\Entity\User;
use YesWiki\Identity\Service\GroupManager;

/** Moves an account between the wiki groups a provider's mapping names. A failure is a warning, never a refusal. */
class GroupSync
{
    /** What is said when the guard keeps an account in "admins", by the plan and by apply() alike. */
    public const LAST_ADMIN = ['kind' => 'last_admin', 'message' => 'the account stays in "admins": it is the last member'];

    public function __construct(private readonly GroupManager $groups)
    {
    }

    /**
     * @param list<string> $add
     * @param list<string> $remove
     *
     * @return list<array{kind: string, message: string}>
     */
    public function apply(User $user, array $add, array $remove): array
    {
        $name = $user->getName();
        $warnings = [];

        foreach ($add as $group) {
            try {
                if ($this->groups->groupExists($group)) {
                    $this->groups->addMembers($group, [$name]);
                } else {
                    $this->groups->create($group, [$name]);
                }
            } catch (\Throwable $e) {
                $warnings[] = ['kind' => 'group_failed', 'message' => "could not add the account to \"$group\": " . $e->getMessage()];
            }
        }

        foreach ($remove as $group) {
            try {
                // a plan can be stale: the guard is asked again here.
                if ($group === ADMIN_GROUP && $this->isLastAdmin($name)) {
                    $warnings[] = self::LAST_ADMIN;
                    continue;
                }
                $this->groups->removeMembers($group, [$name]);
            } catch (\Throwable $e) {
                $warnings[] = ['kind' => 'group_failed', 'message' => "could not remove the account from \"$group\": " . $e->getMessage()];
            }
        }

        return $warnings;
    }

    /** Whether $name is the only member of "admins", which the wiki must never be left without. */
    public function isLastAdmin(string $name): bool
    {
        $members = $this->groups->getMembers(ADMIN_GROUP);

        return in_array($name, $members, true) && array_diff($members, [$name]) === [];
    }
}
