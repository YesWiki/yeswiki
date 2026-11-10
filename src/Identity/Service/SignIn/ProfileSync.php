<?php

namespace YesWiki\Identity\Service\SignIn;

use YesWiki\Content\Service\PageManager;
use YesWiki\Identity\Entity\User;
use YesWiki\Identity\Service\UserManager;

/** Brings an account's profile in line with what the provider says, saving a revision only when a value differs. */
class ProfileSync
{
    public function __construct(
        private readonly UserManager $users,
        private readonly PageManager $pages
    ) {
    }

    /**
     * @param array<string, string> $profile wiki field => value from the provider
     *
     * @return bool whether the account was written
     */
    public function apply(User $user, array $profile): bool
    {
        $page = $this->pages->getOne($user->getName(), null, true, true);
        $stored = is_array($page) && is_array($page['body'] ?? null) ? $page['body'] : [];

        $changed = [];
        foreach ($profile as $field => $value) {
            $current = is_scalar($stored[$field] ?? null) ? (string)$stored[$field] : '';
            $same = $field === 'email' ? strcasecmp($current, $value) === 0 : $current === $value;
            if (!$same) {
                $changed[$field] = $value;
            }
        }
        if ($changed === []) {
            return false;
        }

        // the internal write path: the user-facing update refuses the fields a provider owns.
        return $this->users->update($user, $changed);
    }
}
